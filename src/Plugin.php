<?php

namespace wabisoft\qa;

use wabisoft\qa\services\getReports;
use wabisoft\qa\variables\AdminHelperVariable;
use Craft;
use craft\web\twig\variables\CraftVariable;
use yii\base\Event;


/**
 *
 * @property-read array $cpNavItem
 */
class Plugin extends craft\base\Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = false;
    public bool $hasCpSection = true;
    public int $badgeCount = 10;


    public function init(): void
    {
        parent::init();
        /*
         * Register variables
         */
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $e) {
                /** @var CraftVariable $variable */
                $variable = $e->sender;
                $variable->set('wabiAdminHelper', AdminHelperVariable::class);
            }
        );
    }

    public function getCpNavItem(): array
    {
        $item = parent::getCpNavItem();
        $item['subnav'] = [
            'dashboard' => ['label' => 'Dashboard', 'url' => 'wabisoft-qa'],
            'elements' => [
                'label' => 'Broken Elements',
                'url' => 'wabisoft-qa/elements',
                'badgeCount' => getReports::getBrokenElementsCount()
            ],
            'inline' => [
                'label' => 'Inline Links',
                'url' => 'wabisoft-qa/inline',
                'badgeCount' => getReports::getBrokenInlineCount()
            ],
            'runs' => ['label' => 'Runs', 'url' => 'wabisoft-qa/runs'],
        ];
        return $item;
    }

    protected function customAdminRoutes(): array
    {
        return [
          'links' => 'wabisoft-qa/links'
        ];
    }
}
