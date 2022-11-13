<?php

namespace wabisoft\qa\services;

use Craft;
use craft\elements\Category;
use craft\elements\Entry;
use craft\records\Element;
use DOMDocument;
use DOMXpath;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

use wabisoft\qa\jobs\CheckInlineLinksJob;
use wabisoft\qa\records\ElementUrlsRecord;
use wabisoft\qa\records\InlineLinksRecord;
use wabisoft\qa\records\RunsRecord;
use wabisoft\qa\services\InternalLinks;
use yii\console\Exception;

class InlineLinks
{

    public static function checkAll() {
        $thisRun = new RunsRecord();
        $thisRun->type = 'inline';
        $thisRun->complete = false;
        $thisRun->save();

        $ids = self::getAllIds();
        $batches = array_chunk($ids, 5000);
        $totalBatches = count($batches);

        foreach ($batches as $i => $batch) {
            $result = Craft::$app->getQueue()->delay(0)->push( new CheckInlineLinksJob([
                'elements' => $batch,
                'runId' => $thisRun->id,
                'currentBatch' => $i + 1,
                'totalBatches' => $totalBatches
            ]));
        };

        $thisRun->complete = true;
        $thisRun->save();

    }

    public static function checkElementById($elementId, $runId) {
        $element = self::getElement($elementId);
        if (!$element) return false;

        $record = ElementUrlsRecord::find()->where(['elementId' => $elementId])->one();

        if(!$record) {
            $record = new ElementUrlsRecord();
        }
        $record->elementId = $element->id;
        $record->runId = $runId;
        $record->class = get_class($element) ?? null;
        $record->url = $element->url;
        $record->save();

        self::getElementInlineLinks($element, $runId);
        self::checkInlineLinks($runId);
    }

    public static function getElement($id) {
        $entry = Entry::find()->id($id)->one();
        if ($entry) return $entry;

        $category = Category::find()->id($id)->one();
        if ($category) return $category;

        return false;
    }


    public static function getAllIds(): array
    {
        return array_merge(self::getEntryIds(), self::getCategoryIds());
    }

    public static function getEntryIds() {
        $urls = [];
        $entries = Entry::find()->all();
        foreach ($entries as $entry) {
            if($entry->url) {
                $urls[] = $entry->id;
            }
        }
        return $urls;
    }
    public static function getCategoryIds() {
        $urls = [];
        $entries = Category::find()->all();
        foreach ($entries as $entry) {
            if($entry->url) {
                $urls[] = $entry->id;
            }
        }
        return $urls;
    }


    private static function checkLinkStatus($url) {

        $client = new \GuzzleHttp\Client();
        try {
            $response = $client->request('GET', $url);
            return $response->getStatusCode();
        }
        catch (GuzzleException $e) {
            return 500;
        }
    }

    private static function shouldCheckLink($url) {
        $checkParams = false;
        if(!$checkParams && str_starts_with($url, '?')) {
            return false;
        }
        return true;
    }


    private static function checkInlineLinks($runId) {
        $records = InlineLinksRecord::find()->all();
        foreach ($records as $record) {
            $status = self::checkLinkStatus($record->url);

            //check to see if it's a relative URL
            if($status != 200) {
                $status = self::checkLinkStatus($record->foundOn . '/' . $record->url);
            }
            $record->broken = self::isBroken($status);
            $record->status = $status;
            $record->runId = $runId;
            $record->save();
        }
    }

    private static function isBroken($status) {
        if ($status == 200) return false;
        if ($status == 301) return false;
        if ($status == 302) return false;
        if ($status == 308) return false;
        return true;
    }

    private static function getElementInlineLinks($element, $runId) {
        $url = $element->url;
        $html = file_get_contents($url);

        $dom = new DOMDocument();
        @$dom->loadHTML($html);

        // grab all the on the page
        $xpath = new DOMXPath($dom);
        $hrefs = $xpath->evaluate("/html/body//a");

        $inlineLinks = [];

        for ($i = 0; $i < $hrefs->length; $i++) {
            $href = $hrefs->item($i);
            $url = $href->getAttribute('href');
            $inlineLinks[] = $url;
        }

        if(count($inlineLinks) < 1) {
            return false;
        }
        $elementId = $element->id;

        foreach ($inlineLinks as $link) {
            if(self::shouldCheckLink($link)) {
                $record = InlineLinksRecord::find()->where(['elementId' => $elementId, 'url' => $link])->one();
                if(!$record) {
                    $record = new InlineLinksRecord();
                }
                $record->elementId = $elementId;
                $record->runId = $runId;
                $record->foundOn = $element->url;
                $record->url = $link;
                $record->save();
            }
        }
    }
}
