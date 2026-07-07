<?php

declare(strict_types=1);

namespace OCA\WorkTimePunch\Controller;

use OCA\WorkTimePunch\AppInfo\Application;
use OCA\WorkTimePunch\Service\DependencyGuardService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;

class PageController extends Controller {
	public function __construct(
		IRequest $request,
		private DependencyGuardService $guard,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	public function index(): TemplateResponse {
		return new TemplateResponse(Application::APP_ID, 'main', [
			'worktimeEnabled' => $this->guard->isWorkTimeEnabled(),
			'worktimeAppId' => Application::WORKTIME_APP_ID,
		]);
	}
}

