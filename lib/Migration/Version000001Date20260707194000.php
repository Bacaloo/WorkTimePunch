<?php

declare(strict_types=1);

namespace OCA\WorkTimePunch\Migration;

use Closure;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000001Date20260707194000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var Schema $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('wt_break')) {
			$table = $schema->createTable('wt_break');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('employee_id', Types::INTEGER, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'length' => 64,
				'notnull' => true,
			]);
			$table->addColumn('work_date', Types::DATE_MUTABLE, [
				'notnull' => true,
			]);
			$table->addColumn('state', Types::STRING, [
				'length' => 20,
				'notnull' => true,
				'default' => 'working',
			]);
			$table->addColumn('started_at', Types::DATETIME_MUTABLE, [
				'notnull' => true,
			]);
			$table->addColumn('segment_started_at', Types::DATETIME_MUTABLE, [
				'notnull' => false,
			]);
			$table->addColumn('break_started_at', Types::DATETIME_MUTABLE, [
				'notnull' => false,
			]);
			$table->addColumn('created_at', Types::DATETIME_MUTABLE, [
				'notnull' => true,
			]);
			$table->addColumn('updated_at', Types::DATETIME_MUTABLE, [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['employee_id'], 'wt_break_employee_idx');
			$table->addIndex(['user_id'], 'wt_break_user_idx');
			$table->addIndex(['work_date'], 'wt_break_date_idx');
		}

		return $schema;
	}
}

