<?php

namespace wabisoft\qa\services;
use GuzzleHttp\Exception\GuzzleException;
use yii\base\Component;
use craft\elements\Entry;
use craft\elements\Category;
use wabisoft\qa\records\RunsRecord;
use wabisoft\qa\records\BrokenLinksRecord;
use wabisoft\qa\jobs\CheckLinksJob;
use yii\db\StaleObjectException;
use Craft;

class InternalLinks extends Component
{
    /*
     * Checks all the links
     */
    public static function checkAll() {
        $thisRun = new RunsRecord();
        $thisRun->type = 'internal';
        $thisRun->complete = false;
        $thisRun->save();

        $urls = self::getAllUrls();
        $batches = array_chunk($urls, 5000);
        $totalBatches = count($batches);

        foreach ($batches as $i => $batch) {
          $result = Craft::$app->getQueue()->delay(0)->push( new CheckLinksJob([
              'urls' => $batch,
              'runId' => $thisRun->id,
              'currentBatch' => $i + 1,
              'totalBatches' => $totalBatches
          ]));
        };

        $thisRun->complete = true;
        $thisRun->save();
    }

    /*
     * Checks a single link by record ID
     */
    public static function checkLinkById($id) {
        $record = BrokenLinksRecord::find()->where(['id' => $id])->one();
        if(!$record) {
            return;
        }
        self::checkLink($record->url, $record->runId);
    }

    public static function deleteAll() {
        $runs = RunsRecord::find()->all();
        foreach ($runs as $run) {
            self::deleteByRunId($run->id);
            $run->delete();
        }
    }

    public static function getAllUrls(): array
    {
        return array_merge(self::getEntryUrls(), self::getCategoryUrls());
    }

    public static function getEntryUrls() {
        $urls = [];
        $entries = Entry::find()->all();
        foreach ($entries as $entry) {
            if($entry->url) {
                $urls[] = $entry->url;
            }
        }
        return $urls;
    }
    public static function getCategoryUrls() {
        $urls = [];
        $entries = Category::find()->all();
        foreach ($entries as $entry) {
            if($entry->url) {
                $urls[] = $entry->url;
            }
        }
        return $urls;
    }

    /**
     * Delete links by run ID
     *
     * @throws StaleObjectException
     */
    public static function deleteByRunId($id) {
       $records = BrokenLinksRecord::find()->where(['runId' => $id])->all();
       if(!$records) {
           return;
       }
       foreach ($records as $record) {
           $record->delete();
       }
   }


    public static function checkLink($url, $runId = null): bool
    {
        $client = new \GuzzleHttp\Client();
        try {
            $response = $client->request('GET', $url);
            return true;
        }  catch (GuzzleException $e) {
            $record = BrokenLinksRecord::find()->where(['url' => $url])->one();
            if(!$record) {
                $record = new BrokenLinksRecord();
            }
            $record->url = $url;
            $record->runId = $runId;
            $record->errorCode = '500';
            $record->save();
            return false;
        }
    }
}
