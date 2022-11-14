<?php

namespace wabisoft\qa\services;

use wabisoft\qa\records\ElementUrlsRecord;
use wabisoft\qa\records\RunsRecord;
use yii\db\StaleObjectException;

class Cleanup
{
    /**
     * @throws StaleObjectException
     * @throws \Throwable
     */
    public static function runs() {
        $runs = RunsRecord::find()->all();
        foreach ($runs as $run) {
            if(self::shouldDeleteRunById($run->id)) {
                $run->delete();
            }
        }
        return;
    }

    private static function shouldDeleteRunById($runId) {
        $countElements = getReports::getBrokenElementsCount($runId);
        if($countElements > 0) {
            return false;
        }
        $countInline = getReports::getBrokenInlineCount($runId);
        if($countInline > 0) {
            return false;
        }
        $elementReferences = ElementUrlsRecord::find()->where(['runId' => $runId])->all();
        if(count($elementReferences) > 0) {
            return false;
        }
        return true;
    }

}
