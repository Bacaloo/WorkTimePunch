<?php

declare(strict_types=1);

namespace OCA\WorkTimePunch\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000003Date20260912150000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if ($schema->hasTable('wt_break')) {
			$table = $schema->getTable('wt_break');
			if (!$table->hasColumn('segment_client')) {
				$table->addColumn('segment_client', Types::STRING, [
					'length' => 16,
					'notnull' => false,
				]);
			}
		}
		return $schema;
	}
}
