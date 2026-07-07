<?php

declare(strict_types=1);

namespace OCA\WorkTimePunch\Listener;

use OCA\WorkTimePunch\AppInfo\Application;
use OCA\WorkTimePunch\Service\DependencyGuardService;
use OCP\App\Events\AppDisableEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/** @template-implements IEventListener<AppDisableEvent> */
class AppDisableListener implements IEventListener {
	public function __construct(
		private DependencyGuardService $guard,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof AppDisableEvent) {
			return;
		}

		if ($event->getAppId() !== Application::WORKTIME_APP_ID) {
			return;
		}

		$this->guard->disableSelfBecauseWorkTimeWasDisabled();
	}
}

