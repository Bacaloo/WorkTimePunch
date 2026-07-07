<?php

declare(strict_types=1);

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'punch#state', 'url' => '/api/state', 'verb' => 'GET'],
		['name' => 'punch#punch', 'url' => '/api/punch/{punchAction}', 'verb' => 'POST', 'requirements' => ['punchAction' => 'kommen|pausenanfang|pausenende|gehen']],
	],
];
