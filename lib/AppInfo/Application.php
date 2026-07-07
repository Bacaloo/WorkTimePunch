<?php

declare(strict_types=1);

namespace OCA\WorkTimePunch\AppInfo;

use OCA\WorkTimePunch\Listener\AppDisableListener;
use OCA\WorkTimePunch\Listener\BeforeTemplateRenderedListener;
use OCA\WorkTimePunch\Service\DependencyGuardService;
use OCP\App\Events\AppDisableEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'worktimepunch';
	public const WORKTIME_APP_ID = 'worktime';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(AppDisableEvent::class, AppDisableListener::class);
		$context->registerEventListener(BeforeTemplateRenderedEvent::class, BeforeTemplateRenderedListener::class);
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(static function (DependencyGuardService $guard): void {
			$guard->disableSelfIfWorkTimeMissing();
		});
	}
}

