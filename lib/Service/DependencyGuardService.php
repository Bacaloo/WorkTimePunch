<?php

declare(strict_types=1);

namespace OCA\WorkTimePunch\Service;

use OCA\WorkTimePunch\AppInfo\Application;
use OCP\App\IAppManager;
use Psr\Log\LoggerInterface;
use Throwable;

class DependencyGuardService {
	public function __construct(
		private IAppManager $appManager,
		private LoggerInterface $logger,
	) {
	}

	public function isWorkTimeEnabled(): bool {
		if (method_exists($this->appManager, 'isEnabledForAnyone')) {
			return $this->appManager->isEnabledForAnyone(Application::WORKTIME_APP_ID);
		}

		return (bool)$this->appManager->isInstalled(Application::WORKTIME_APP_ID);
	}

	public function disableSelfIfWorkTimeMissing(): void {
		if ($this->isWorkTimeEnabled()) {
			return;
		}

		$this->disableSelf('WorkTimePunch requires WorkTime to be enabled.');
	}

	public function disableSelfBecauseWorkTimeWasDisabled(): void {
		$this->disableSelf('WorkTime was disabled, disabling WorkTimePunch as companion app.');
	}

	private function disableSelf(string $reason): void {
		try {
			$this->appManager->disableApp(Application::APP_ID, true);
			$this->logger->info($reason, ['app' => Application::APP_ID]);
		} catch (Throwable $e) {
			$this->logger->warning('Could not disable WorkTimePunch.', [
				'app' => Application::APP_ID,
				'exception' => $e,
			]);
		}
	}
}

