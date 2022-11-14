<?php

namespace wabisoft\qa\services;
use wabisoft\qa\records\BrokenLinksRecord;
use wabisoft\qa\records\InlineLinksRecord;
use wabisoft\qa\records\RunsRecord;

class getReports
{
    public static function getBrokenElements($runId = null) {
        if(!$runId) {
            return BrokenLinksRecord::find()->all();
        }
        return BrokenLinksRecord::find()->where(['runId' => $runId])->all();
    }

    public static function getBrokenElementsCount($runId = null) {
        $count = count(self::getBrokenElements($runId));
        if($count < 1) {
            return null;
        }
        return $count;
    }

    public static function getBrokenInline($runId = null) {
        if(!$runId) {
            return InlineLinksRecord::find()->where(['broken' => true])->all();
        }
        return InlineLinksRecord::find()->where(['broken' => true, 'runId' => $runId])->all();
    }
    public static function getBrokenInlineCount($runId = null) {
        $count = count(self::getBrokenInline($runId));
        if($count < 1) {
            return null;
        }
        return $count;
    }

    public static function getRuns() {
        return RunsRecord::find()->all();
    }

    public static function mostRecentInternalRun() {
        return RunsRecord::find()->where(['type'=>'internal'])->orderBy('dateUpdated DESC')->one();
    }
    public static function mostRecentInlineRun() {
        return RunsRecord::find()->where(['type' => 'inline'])->orderBy('dateUpdated DESC')->one();
    }

}
