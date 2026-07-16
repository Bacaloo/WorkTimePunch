<?php

declare(strict_types=1);

namespace OCA\WorkTimePunch\Migration;

use Closure;
use OCA\WorkTimePunch\BackgroundJob\SynchronizeSessionsJob;
use OCP\BackgroundJob\IJobList;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000002Date20260716143000 extends SimpleMigrationStep {
	public function __construct(
		private IJobList $jobList,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if ($schema->hasTable('wt_break')) {
			$table = $schema->getTable('wt_break');
			if (!$table->hasColumn('worktime_audit_id')) {
				$table->addColumn('worktime_audit_id', Types::BIGINT, [
					'notnull' => false,
					'unsigned' => true,
				]);
			}
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->jobList->add(SynchronizeSessionsJob::class);
	}
}
