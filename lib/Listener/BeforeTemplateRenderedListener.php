<?php

declare(strict_types=1);

namespace OCA\WorkTimePunch\Listener;

use OCA\WorkTimePunch\AppInfo\Application;
use OCA\WorkTimePunch\Service\DependencyGuardService;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/** @template-implements IEventListener<BeforeTemplateRenderedEvent> */
class BeforeTemplateRenderedListener implements IEventListener {
	public function __construct(
		private DependencyGuardService $guard,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof BeforeTemplateRenderedEvent || !$event->isLoggedIn()) {
			return;
		}

		if (!$this->guard->isWorkTimeEnabled()) {
			return;
		}

		Util::addStyle(Application::APP_ID, 'topbar');
		Util::addScript(Application::APP_ID, 'topbar');
	}
}

