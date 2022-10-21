<?php

namespace wabisoft\qa\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\helpers\App;
use wabisoft\qa\services\InternalLinks;

class CheckLinksJob extends BaseJob
{
    public string $name = 'Checking Links';
    public array|null $urls;
    public int $currentBatch;
    public int $runId;
    public int $totalBatches;

    public function execute($queue) : void {
        App::maxPowerCaptain();
        $urls = $this->urls;
        $runId = $this->runId;
        $totalUrls = count($this->urls);
        foreach ($urls as $i => $url) {
            $this->setProgress(
                $queue,
                $i / $totalUrls,
                \Craft::t('wabisoft-qa', '{step, number} of {total, number}', [
                    'step' => $i + 1,
                    'total' => $totalUrls,
                ])
            );
            InternalLinks::checkLink($url, $runId);
        }
    }

    protected function defaultDescription(): string
    {
        if($this->totalBatches > 1) {
            return Craft::t('wabisoft-qa', $this->currentBatch . ' of ' . $this->totalBatches . ' Checking URLs');
        }
        return Craft::t('wabisoft-qa', 'Checking URLs');
    }

}
