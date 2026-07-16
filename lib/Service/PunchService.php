<?php

declare(strict_types=1);

namespace OCA\WorkTimePunch\Service;

use DateTimeImmutable;
use DateTimeZone;
use OCA\WorkTimePunch\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;
use Throwable;

class PunchService {
	private const STATE_OUTSIDE = 'outside';
	private const STATE_WORKING = 'working';
	private const STATE_PAUSED = 'paused';

	public function __construct(
		private IDBConnection $db,
		private IAppManager $appManager,
		private IConfig $config,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @return array<string, mixed>
	 */
	public function stateForUser(?string $userId): array {
		if ($userId === null || $userId === '') {
			return $this->unavailableState('Nicht angemeldet.');
		}

		$this->synchronizeSessions();
		$timezone = $this->timezoneForUser($userId);
		$employee = $this->findActiveEmployee($userId, $timezone);
		if ($employee === null) {
			return $this->unavailableState('Kein aktiver WorkTime-Mitarbeiter fuer diesen Nextcloud-Benutzer.');
		}

		$session = $this->findSession((int)$employee['id']);
		return $this->buildState($employee, $session, $timezone);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function punch(?string $userId, string $action): array {
		if (!in_array($action, ['kommen', 'pausenanfang', 'pausenende', 'gehen'], true)) {
			throw new PunchException('Unbekannte WorkTimePunch-Aktion.');
		}

		if ($userId === null || $userId === '') {
			throw new PunchException('Nicht angemeldet.');
		}

		$synchronizedEmployeeIds = $this->synchronizeSessions();
		$timezone = $this->timezoneForUser($userId);
		$employee = $this->findActiveEmployee($userId, $timezone);
		if ($employee === null) {
			throw new PunchException('Kein aktiver WorkTime-Mitarbeiter fuer diesen Nextcloud-Benutzer.');
		}

		$employeeId = (int)$employee['id'];
		$session = $this->findSession($employeeId);
		if ($session === null && $action !== 'kommen' && in_array($employeeId, $synchronizedEmployeeIds, true)) {
			return $this->buildState($employee, null, $timezone);
		}
		$state = $session['state'] ?? self::STATE_OUTSIDE;
		$now = $this->now($timezone);

		match ($action) {
			'kommen' => $this->kommen($employee, $session, $state, $now),
			'pausenanfang' => $this->pausenanfang($employee, $session, $state, $now, $userId),
			'pausenende' => $this->pausenende($employee, $session, $state, $now),
			'gehen' => $this->gehen($employee, $session, $state, $now, $userId),
		};

		return $this->stateForUser($userId);
	}

	private function loadWorkTime(): void {
		if (!$this->appManager->isEnabledForAnyone(Application::WORKTIME_APP_ID)) {
			throw new PunchException('WorkTime ist nicht aktiv.');
		}

		$this->appManager->loadApp(Application::WORKTIME_APP_ID);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function findActiveEmployee(string $userId, DateTimeZone $timezone): ?array {
		$today = $this->now($timezone)->format('Y-m-d');
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('wt_employees')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('is_active', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->isNull('entry_date'),
				$qb->expr()->lte('entry_date', $qb->createNamedParameter($today)),
			))
			->andWhere($qb->expr()->orX(
				$qb->expr()->isNull('exit_date'),
				$qb->expr()->gte('exit_date', $qb->createNamedParameter($today)),
			));

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return is_array($row) ? $row : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function findSession(int $employeeId): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('wt_break')
			->where($qb->expr()->eq('employee_id', $qb->createNamedParameter($employeeId, IQueryBuilder::PARAM_INT)));

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return is_array($row) ? $row : null;
	}

	/**
	 * @param array<string, mixed> $employee
	 * @param array<string, mixed>|null $session
	 * @return array<string, mixed>
	 */
	private function buildState(array $employee, ?array $session, DateTimeZone $timezone): array {
		$state = $session['state'] ?? self::STATE_OUTSIDE;
		if (!in_array($state, [self::STATE_WORKING, self::STATE_PAUSED], true)) {
			$state = self::STATE_OUTSIDE;
		}

		return [
			'available' => true,
			'state' => $state,
			'employee' => [
				'id' => (int)$employee['id'],
				'userId' => (string)$employee['user_id'],
				'name' => trim((string)$employee['first_name'] . ' ' . (string)$employee['last_name']),
				'timeZone' => $timezone->getName(),
			],
			'session' => $session === null ? null : [
				'workDate' => $this->dateOnly((string)$session['work_date']),
				'startedAt' => $this->formatDateTime((string)$session['started_at'], $timezone),
				'segmentStartedAt' => $session['segment_started_at'] === null ? null : $this->formatDateTime((string)$session['segment_started_at'], $timezone),
				'breakStartedAt' => $session['break_started_at'] === null ? null : $this->formatDateTime((string)$session['break_started_at'], $timezone),
			],
			'buttons' => [
				'kommen' => ['enabled' => $state === self::STATE_OUTSIDE],
				'pausenanfang' => ['enabled' => $state === self::STATE_WORKING],
				'pausenende' => ['enabled' => $state === self::STATE_PAUSED],
				'gehen' => ['enabled' => $state === self::STATE_WORKING],
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function unavailableState(string $message): array {
		return [
			'available' => false,
			'state' => self::STATE_OUTSIDE,
			'message' => $message,
			'buttons' => [
				'kommen' => ['enabled' => false],
				'pausenanfang' => ['enabled' => false],
				'pausenende' => ['enabled' => false],
				'gehen' => ['enabled' => false],
			],
		];
	}

	/**
	 * @param array<string, mixed> $employee
	 * @param array<string, mixed>|null $session
	 */
	private function kommen(array $employee, ?array $session, string $state, DateTimeImmutable $now): void {
		if ($session !== null || $state !== self::STATE_OUTSIDE) {
			throw new PunchException('Kommen ist nur moeglich, wenn kein aktiver WorkTimePunch-Zustand besteht.');
		}

		$qb = $this->db->getQueryBuilder();
		$qb->insert('wt_break')
			->values([
				'employee_id' => $qb->createNamedParameter((int)$employee['id'], IQueryBuilder::PARAM_INT),
				'user_id' => $qb->createNamedParameter((string)$employee['user_id']),
				'work_date' => $qb->createNamedParameter($now->format('Y-m-d')),
				'state' => $qb->createNamedParameter(self::STATE_WORKING),
				'started_at' => $qb->createNamedParameter($this->toDbDateTime($now)),
				'segment_started_at' => $qb->createNamedParameter($this->toDbDateTime($now)),
				'break_started_at' => $qb->createNamedParameter(null),
				'created_at' => $qb->createNamedParameter($this->toDbDateTime($now)),
				'updated_at' => $qb->createNamedParameter($this->toDbDateTime($now)),
				'worktime_audit_id' => $qb->createNamedParameter($this->currentWorkTimeAuditId(), IQueryBuilder::PARAM_INT),
			])
			->executeStatement();
	}

	/**
	 * @param array<string, mixed> $employee
	 * @param array<string, mixed>|null $session
	 */
	private function pausenanfang(array $employee, ?array $session, string $state, DateTimeImmutable $now, string $currentUserId): void {
		if ($session === null || $state !== self::STATE_WORKING) {
			throw new PunchException('Pausenanfang ist nur moeglich, wenn der Mitarbeiter im Betrieb ist.');
		}

		$this->createTimeEntryForSegment($employee, $session, $now, $currentUserId);
		$this->updateSession((int)$session['id'], self::STATE_PAUSED, null, $now, $now);
	}

	/**
	 * @param array<string, mixed> $employee
	 * @param array<string, mixed>|null $session
	 */
	private function pausenende(array $employee, ?array $session, string $state, DateTimeImmutable $now): void {
		if ($session === null || $state !== self::STATE_PAUSED) {
			throw new PunchException('Pausenende ist nur moeglich, wenn gerade eine Pause laeuft.');
		}

		$this->updateSession((int)$session['id'], self::STATE_WORKING, $now, null, $now);
	}

	/**
	 * @param array<string, mixed> $employee
	 * @param array<string, mixed>|null $session
	 */
	private function gehen(array $employee, ?array $session, string $state, DateTimeImmutable $now, string $currentUserId): void {
		if ($session === null || $state !== self::STATE_WORKING) {
			throw new PunchException('Gehen ist nur moeglich, wenn der Mitarbeiter im Betrieb ist.');
		}

		$this->createTimeEntryForSegment($employee, $session, $now, $currentUserId);
		$this->deleteSession((int)$session['id']);
	}

	/**
	 * @param array<string, mixed> $employee
	 * @param array<string, mixed> $session
	 */
	private function createTimeEntryForSegment(array $employee, array $session, DateTimeImmutable $end, string $currentUserId): void {
		if ($session['segment_started_at'] === null) {
			throw new PunchException('Es gibt keinen offenen Arbeitsabschnitt.');
		}

		$timezone = $this->timezoneForUser($currentUserId);
		$start = $this->fromDbDateTime((string)$session['segment_started_at'], $timezone);
		if (($end->getTimestamp() - $start->getTimestamp()) < 60) {
			throw new PunchException('Der Arbeitsabschnitt ist noch kuerzer als eine Minute.');
		}

		try {
			/** @var \OCA\WorkTime\Service\TimeEntryService $timeEntryService */
			$timeEntryService = \OC::$server->get(\OCA\WorkTime\Service\TimeEntryService::class);
			$timeEntryService->create(
				(int)$employee['id'],
				$this->dateOnly((string)$session['work_date']),
				$start->format('H:i'),
				$end->format('H:i'),
				0,
				null,
				'WorkTimePunch',
				$currentUserId,
			);
		} catch (Throwable $e) {
			$this->logger->warning('WorkTimePunch could not create WorkTime time entry.', [
				'app' => Application::APP_ID,
				'employeeId' => (int)$employee['id'],
				'exception' => $e,
			]);
			throw new PunchException($e->getMessage(), 0, $e);
		}
	}

	private function updateSession(int $id, string $state, ?DateTimeImmutable $segmentStartedAt, ?DateTimeImmutable $breakStartedAt, DateTimeImmutable $now): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('wt_break')
			->set('state', $qb->createNamedParameter($state))
			->set('segment_started_at', $qb->createNamedParameter($segmentStartedAt === null ? null : $this->toDbDateTime($segmentStartedAt)))
			->set('break_started_at', $qb->createNamedParameter($breakStartedAt === null ? null : $this->toDbDateTime($breakStartedAt)))
			->set('updated_at', $qb->createNamedParameter($this->toDbDateTime($now)))
			->set('worktime_audit_id', $qb->createNamedParameter($this->currentWorkTimeAuditId(), IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	/**
	 * Reconciles the helper table with later changes made in WorkTime.
	 *
	 * WorkTimePunch writes a completed WorkTime entry before changing its own
	 * session. A later WorkTime create, update or delete for the same employee
	 * and day therefore means that WorkTime has become authoritative and the
	 * open helper session must be discarded.
	 *
	 * @return int[] Employee ids whose stale helper session was removed.
	 */
	public function synchronizeSessions(): array {
		$this->loadWorkTime();
		$today = $this->now(new DateTimeZone('Europe/Berlin'))->format('Y-m-d');
		$deleteIds = [];
		$synchronizedEmployeeIds = [];

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('wt_break');

		$result = $qb->executeQuery();
		while ($row = $result->fetch()) {
			if (!is_array($row)) {
				continue;
			}

			$state = (string)$row['state'];
			$employeeId = (int)$row['employee_id'];
			$userId = (string)$row['user_id'];
			$obsolete = (
				!in_array($state, [self::STATE_WORKING, self::STATE_PAUSED], true)
				|| !$this->activeEmployeeRowStillMatches($employeeId, $userId, $today)
			);
			if ($obsolete || $this->hasLaterWorkTimeChange($row)) {
				$deleteIds[] = (int)$row['id'];
				$synchronizedEmployeeIds[] = $employeeId;
			}
		}
		$result->closeCursor();

		foreach ($deleteIds as $id) {
			$this->deleteSession($id);
		}

		return array_values(array_unique($synchronizedEmployeeIds));
	}

	/**
	 * @param array<string, mixed> $session
	 */
	private function hasLaterWorkTimeChange(array $session): bool {
		$watermark = isset($session['worktime_audit_id']) ? (int)$session['worktime_audit_id'] : 0;
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'action', 'old_values', 'new_values')
			->from('wt_audit_logs')
			->where($qb->expr()->eq('entity_type', $qb->createNamedParameter('time_entry')))
			->orderBy('id', 'ASC');

		if ($watermark > 0) {
			$qb->andWhere($qb->expr()->gt('id', $qb->createNamedParameter($watermark, IQueryBuilder::PARAM_INT)));
		} else {
			$timezone = $this->timezoneForUser((string)$session['user_id']);
			$sessionUpdatedAtUtc = $this->fromDbDateTime((string)$session['updated_at'], $timezone)
				->setTimezone(new DateTimeZone('UTC'));
			$qb->andWhere($qb->expr()->gt(
				'created_at',
				$qb->createNamedParameter($this->toDbDateTime($sessionUpdatedAtUtc)),
			));
		}

		$result = $qb->executeQuery();
		$lastProcessedAuditId = $watermark;
		$hasLaterChange = false;
		while ($audit = $result->fetch()) {
			if (!is_array($audit)) {
				continue;
			}

			$lastProcessedAuditId = max($lastProcessedAuditId, (int)$audit['id']);
			if (!in_array((string)$audit['action'], ['create', 'update', 'delete'], true)) {
				continue;
			}

			if ($this->auditMatchesSession($audit, $session)) {
				$hasLaterChange = true;
				break;
			}
		}
		$result->closeCursor();

		if ($hasLaterChange) {
			$this->logger->info('WorkTimePunch removed a stale session after a later WorkTime change.', [
				'app' => Application::APP_ID,
				'employeeId' => (int)$session['employee_id'],
				'workDate' => $this->dateOnly((string)$session['work_date']),
				'auditId' => $lastProcessedAuditId,
			]);
			return true;
		}

		if ($lastProcessedAuditId > $watermark) {
			$this->updateAuditWatermark((int)$session['id'], $lastProcessedAuditId);
		}

		return false;
	}

	/**
	 * @param array<string, mixed> $audit
	 * @param array<string, mixed> $session
	 */
	private function auditMatchesSession(array $audit, array $session): bool {
		foreach (['old_values', 'new_values'] as $column) {
			if (!is_string($audit[$column]) || $audit[$column] === '') {
				continue;
			}

			$values = json_decode($audit[$column], true);
			if (!is_array($values)) {
				continue;
			}

			if (
				(int)($values['employeeId'] ?? 0) === (int)$session['employee_id']
				&& $this->dateOnly((string)($values['date'] ?? '')) === $this->dateOnly((string)$session['work_date'])
			) {
				return true;
			}
		}

		return false;
	}

	private function updateAuditWatermark(int $sessionId, int $auditId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('wt_break')
			->set('worktime_audit_id', $qb->createNamedParameter($auditId, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($sessionId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	private function currentWorkTimeAuditId(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->max('id'))
			->from('wt_audit_logs');
		$result = $qb->executeQuery();
		$maxId = (int)$result->fetchOne();
		$result->closeCursor();

		return $maxId;
	}

	private function activeEmployeeRowStillMatches(int $employeeId, string $userId, string $today): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('wt_employees')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($employeeId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('is_active', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->isNull('entry_date'),
				$qb->expr()->lte('entry_date', $qb->createNamedParameter($today)),
			))
			->andWhere($qb->expr()->orX(
				$qb->expr()->isNull('exit_date'),
				$qb->expr()->gte('exit_date', $qb->createNamedParameter($today)),
			))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$exists = $result->fetchOne() !== false;
		$result->closeCursor();

		return $exists;
	}

	private function deleteSession(int $id): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('wt_break')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	private function now(DateTimeZone $timezone): DateTimeImmutable {
		$now = new DateTimeImmutable('now', $timezone);
		return $now->setTime(
			(int)$now->format('H'),
			(int)$now->format('i'),
			0,
		);
	}

	private function toDbDateTime(DateTimeImmutable $dateTime): string {
		return $dateTime->format('Y-m-d H:i:s');
	}

	private function fromDbDateTime(string $dateTime, DateTimeZone $timezone): DateTimeImmutable {
		return new DateTimeImmutable($dateTime, $timezone);
	}

	private function formatDateTime(string $dateTime, DateTimeZone $timezone): string {
		return $this->fromDbDateTime($dateTime, $timezone)->format('c');
	}

	private function dateOnly(string $date): string {
		return substr($date, 0, 10);
	}

	private function timezoneForUser(string $userId): DateTimeZone {
		$timezoneName = trim((string)$this->config->getUserValue($userId, 'core', 'timezone', ''));
		if ($timezoneName === '') {
			$timezoneName = 'Europe/Berlin';
		}

		try {
			return new DateTimeZone($timezoneName);
		} catch (Throwable) {
			return new DateTimeZone('Europe/Berlin');
		}
	}
}
