<?php

namespace OCA\ChurchToolsIntegration\Jobs;

use OCP\BackgroundJob\TimedJob;


class UpdateCron extends TimedJob
{
    public function __construct(\OCP\AppFramework\Utility\ITimeFactory $time) {
        parent::__construct($time);

        $this->setInterval(3600);
        $this->setTimeSensitivity(self::TIME_INSENSITIVE);
    }

    protected function run($arguments) {
        Update::dispatch();
    }
}