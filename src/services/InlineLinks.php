<?php

namespace wabisoft\qa\services;

use Craft;
use craft\elements\Category;
use craft\elements\Entry;
use craft\helpers\StringHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use simplehtmldom\HtmlWeb;

use wabisoft\qa\jobs\CheckInlineLinksJob;
use wabisoft\qa\records\ElementUrlsRecord;
use wabisoft\qa\records\InlineLinksRecord;
use wabisoft\qa\records\RunsRecord;

use wabisoft\framework\services\Logging;
use yii\db\StaleObjectException;

use craft\helpers\Console;

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


    public static function checkConsole() {
        $thisRun = new RunsRecord();
        $thisRun->type = 'inline';
        $thisRun->complete = false;
        $thisRun->save();
        $ids = self::getAllIds();

        /*
         * Find all elements with URIs and record those
         */
        $start = microtime(true);
        Console::output('========================');
        Console::output('Generate Element URLs');
        foreach ($ids as $id) {
            self::checkElementById($id, $thisRun->id);
        }
        $end = microtime(true);
        $findElementsTime = ceil($end - $start);
        Console::output('Fetched Element URls in ' . $findElementsTime . ' seconds');

        /*
         * Loop through Elements and find all a tags
         */
        $elementRecords = ElementUrlsRecord::find()->all();
        Console::output('========================');
        Console::output('Find Inline URLs for ' . count($elementRecords) . ' elements.');
        $start = microtime(true);
        $count = 0;
        foreach ($elementRecords as $record) {
           $count++;
            $prefix = $count . '/' . count($elementRecords);
            self::getElementInlineLinks($record, $thisRun->id, $prefix);
        }
        $end = microtime(true);
        $findInlineTime = ceil($end - $start);
        Console::output('Finished in finding element links in ' . $findInlineTime . ' seconds');

        /*
         * Check those links
         */
        $start = microtime(true);
        Console::output('========================');
        Console::output('Checking Inline Links. This may take awhile');
        $end = microtime(true);
        $checkInlineTime = ceil($end - $start);
        Console::output('Finished in checking inline links in ' . $checkInlineTime . ' seconds');
        self::checkInlineLinks($thisRun->id);

        $totalTime = $findElementsTime + $findInlineTime + $checkInlineTime;

        $thisRun->timeToComplete = ceil($totalTime);
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
            $response = $client->request('GET', $url, ['timeout' => 5]);
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
        if(str_starts_with($url, 'tel:')) {
            return false;
        }
        if(str_starts_with($url, '#')) {
            return false;
        }

        return true;
    }


    private static function checkInlineLinks($runId) {
        $records = InlineLinksRecord::find()->where(['runId' => $runId])->all();
        $count = 0;
        $totalLinks = count($records);
        Console::output($totalLinks . ' Links');
        Console::output('========================');
        foreach ($records as $record) {
            // see if we already checked this URL during
            // this run and avoid too many requests
            $count++;
            $checkedRecord = self::alreadyChecked($runId, $record);
            if($checkedRecord) {
                Console::output('→ ' . $count . '/' . $totalLinks . 'Already checked ' . $record->url . ' in this run');
                $record->broken = $checkedRecord->broken;
                $record->status = $checkedRecord->status;
                $record->runId = $checkedRecord->runId;
            } else {
                $status = self::checkPossibleLinks($record);
                if(self::isBroken($status)) {
                    Console::output('→ ' . $count . '/' . $totalLinks . ' ' . $record->url . ' | ' . $record->elementId . ': ERROR');
                } else {
                    Console::output('→ ' . $count . '/' . $totalLinks . ' ' . $record->url . ' | ' . $record->elementId . ': ' . $status);
                }

                $record->broken = self::isBroken($status);
                $record->status = $status;
                $record->checked = true;
                $record->runId = $runId;
            }
            $record->save();
        }
    }


    private static function checkPossibleLinks($record) {
        $url = $record->url;
        $found = $record->foundOn;
        $status = self::checkLinkStatus($url);
        // in case it's absolute link with TLD
        if(self::isBroken($status)) {
            $status = self::checkLinkStatus(self::getTLD($found) . $record->url);
        }
        // in case it's a relative link
        if(self::isBroken($status)) {
            $status = self::checkLinkStatus($found . '/' . $record->url);
        }
        return $status;
    }

    private static function getTLD($url) {
        $url_info = parse_url($url);
        $domain = $url_info['scheme'] . '://' . $url_info['host'];
        $domain = trim($domain);
        $domain = rtrim($domain);
        return $domain . '/';
    }

    private static function alreadyChecked($runId, $thisRecord): bool|array|\yii\db\ActiveRecord
    {
        $checkedRecord = InlineLinksRecord::find()->where([
            'url' => $thisRecord->url,
            'runId' => $runId,
            'checked' => true
        ])->one();
        if(!$checkedRecord) {
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

//    private static function getContents($url) {
//        $client = new Client();
//        try {
//            $response = $client->request('GET', $url);
//            return $response->getBody();
//        }
//        catch (GuzzleException $e) {
//            return false;
//        }
//    }

    private static function getElementInlineLinks($record, $runId, $prefix = null) {
        $craftElement = self::getElement($record->elementId);

        $client = new HtmlWeb();
        $html = $client->load($record->url);
        if(!$html) return false;

        $hrefs = $html->find('a');
        $inlineLinks = [];

        foreach ($hrefs as $href) {
            $inlineLinks[] = [
                'element' => $href->outertext,
                'url' => $href->href,
            ];
        }

        if(count($inlineLinks) < 1) {
            return false;
        }
        $elementId = $record->elementId;
        Console::output('→ ' . $prefix . ' - ' . count($inlineLinks) . ' links found on ' . $craftElement->uri);
        foreach ($inlineLinks as $link) {
            if(self::shouldCheckLink($link['url'])) {
                $markup = StringHelper::safeTruncate($link['element'], 1000);
                $markup = StringHelper::encodeMb4($markup);
                $record = InlineLinksRecord::find()->where(['elementId' => $elementId, 'url' => $link['url']])->one();
                if(!$record) {
                    $record = new InlineLinksRecord();
                }
                $record->elementId = $elementId;
                $record->runId = $runId;
                $record->checked = false;
                $record->foundOn = $craftElement->url;
                $record->url = $link['url'];
                $record->markup = $markup;
                $record->save();
            }
        }
    }
}
