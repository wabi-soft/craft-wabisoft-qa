<?php

namespace wabisoft\qa\services;

use Craft;
use craft\elements\Category;
use craft\elements\Entry;
use DOMDocument;
use DOMXpath;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

use wabisoft\qa\jobs\CheckInlineLinksJob;
use wabisoft\qa\records\ElementUrlsRecord;
use wabisoft\qa\records\InlineLinksRecord;
use wabisoft\qa\records\RunsRecord;

use wabisoft\framework\services\Logging;
use yii\db\StaleObjectException;

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

    /**
     * @throws \Throwable
     * @throws StaleObjectException
     */
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
        $client = new Client();
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
            // see if we already checked this URL during
            // this run and avoid too many requests

            $checkedRecord = self::alreadyChecked($runId, $record);
            if($checkedRecord) {
                $record->broken = $checkedRecord->broken;
                $record->status = $checkedRecord->status;
                $record->runId = $checkedRecord->runId;
            } else {
                $status = self::checkLinkStatus($record->url);
                //check to see if it's a relative URL
                if($status != 200) {
                    $status = self::checkLinkStatus($record->foundOn . '/' . $record->url);
                }
                $record->broken = self::isBroken($status);
                $record->status = $status;
                $record->runId = $runId;
            }
            $record->save();
        }
    }

    private static function alreadyChecked($runId, $thisRecord) {
        $checkedRecord = InlineLinksRecord::find()->where(['url' => $thisRecord->url, 'runId' => $runId])->one();
        if($checkedRecord) {
            return false;
        }
        // if checked, let's match all of those
        return $checkedRecord;
    }

    private static function isBroken($status) {
        if ($status == 200) return false;
        if ($status == 301) return false;
        if ($status == 302) return false;
        if ($status == 308) return false;
        return true;
    }

    private static function getContents($url) {
        $client = new Client();
        try {
            $response = $client->request('GET', $url);
            return $response->getBody();
        }
        catch (GuzzleException $e) {
            return false;
        }
    }

    private static function getElementInlineLinks($element, $runId) {
        $url = $element->url;

        $html = self::getContents($url);

        if(!$html) return false;

        $dom = new DOMDocument();
        @$dom->loadHTML($html);

        // grab all the on the page
        $xpath = new DOMXPath($dom);
        $hrefs = $xpath->evaluate("/html/body//a");

        $inlineLinks = [];

        for ($i = 0; $i < $hrefs->length; $i++) {
            $href = $hrefs->item($i);
            $url = $href->getAttribute('href');
            $inlineLinks[] = [
                'element' => $href->ownerDocument->saveHTML($href),
                'url' => $url
            ];
        }

        if(count($inlineLinks) < 1) {
            return false;
        }
        $elementId = $element->id;

        foreach ($inlineLinks as $link) {
            if(self::shouldCheckLink($link['url'])) {
                $markup = substr($link['element'],0,1000);
                $record = InlineLinksRecord::find()->where(['elementId' => $elementId, 'url' => $link['url']])->one();
                if(!$record) {
                    $record = new InlineLinksRecord();
                }
                $record->elementId = $elementId;
                $record->runId = $runId;
                $record->foundOn = $element->url;
                $record->url = $link['url'];
                $record->markup = $markup;
                $record->save();
            }
        }
    }
}
