<?php
namespace wabisoft\qa\variables;
use wabisoft\qa\services\getReports;
use wabisoft\qa\services\InlineLinks;

class AdminHelperVariable
{
    public function getBrokenElements($runId = null): array
    {
        return GetReports::getBrokenElements($runId);
    }
    public static function getElement($id) {
        return InlineLinks::getElement($id);
    }

    public function getBrokenInline($runId = null): array
    {
        return GetReports::getBrokenInline($runId);
    }

    public static function getRuns(): array
    {
        return GetReports::getRuns();
    }
    public static function getMostRecentInternalRun() {
        return GetReports::mostRecentInternalRun();
    }
    public static function getMostRecentInlineRun() {
        return GetReports::mostRecentInlineRun();
    }


}
