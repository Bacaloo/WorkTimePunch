<?php

declare(strict_types=1);

namespace OCA\WorkTimePunch\BackgroundJob;

use OCA\WorkTimePunch\Service\PunchService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;

class SynchronizeSessionsJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private PunchService $punchService,
	) {
		parent::__construct($time);
		$this->setInterval(60);
		$this->setTimeSensitivity(IJob::TIME_SENSITIVE);
	}

	protected function run($argument): void {
		$this->punchService->synchronizeSessions();
	}
}
