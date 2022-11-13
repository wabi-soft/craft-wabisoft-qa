<?php

namespace wabisoft\qa\jobs;
use Craft;
use craft\queue\BaseJob;
use craft\helpers\App;
use wabisoft\qa\services\InlineLinks;

class CheckInlineLinksJob extends BaseJob
{
    public string $name = 'Checking Inline Links';
    public array|null $elements;
    public int $currentBatch;
    public int $runId;
    public int $totalBatches;

    public function execute($queue) : void {
        App::maxPowerCaptain();
        $elements = $this->elements;
        $runId = $this->runId;
        $totalUrls = count($this->elements);
        foreach ($elements as $i => $elementId) {
            $this->setProgress(
                $queue,
                $i / $totalUrls,
                \Craft::t('wabisoft-qa', '{step, number} of {total, number}', [
                    'step' => $i + 1,
                    'total' => $totalUrls,
                ])
            );
            InlineLinks::checkElementById($elementId, $runId);
        }
    }

    protected function defaultDescription(): string
    {
        if($this->totalBatches > 1) {
            return Craft::t('wabisoft-qa', $this->currentBatch . ' of ' . $this->totalBatches . ' Checking Inline URLs');
        }
        return Craft::t('wabisoft-qa', 'Checking Inline URLs');
    }
}
