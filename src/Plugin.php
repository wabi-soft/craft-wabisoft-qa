<?php

namespace wabisoft\qa;
use Craft;

class Plugin extends craft\base\Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = false;
    public bool $hasCpSection = false;

    public function init(): void
    {
        parent::init();
    }
}
