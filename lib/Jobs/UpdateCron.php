<?php

namespace OCA\ChurchToolsIntegration\Jobs;

use OCP\BackgroundJob\TimedJob;

/**
 * @psalm-api
 */
class UpdateCron extends TimedJob {
	public function __construct(\OCP\AppFramework\Utility\ITimeFactory $time) {
		parent::__construct($time);

		$this->setInterval(3600);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	/**
	 * @param array $argument
	 */
	protected function run($argument): void {
		Update::dispatch();
	}
}
