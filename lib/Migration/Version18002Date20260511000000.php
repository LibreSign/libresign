<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCA\Libresign\AppInfo\Application;
use OCP\Exceptions\AppConfigTypeConflictException;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version18002Date20260511000000 extends SimpleMigrationStep {
	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly IDBConnection $connection,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure
	 * @param array $options
	 */
	#[\Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->ensureArrayConfig('groups_request_sign', ['admin']);
		$this->ensureArrayConfig('approval_group', ['admin']);
	}

	/**
	 * @param list<string> $default
	 */
	private function ensureArrayConfig(string $key, array $default): void {
		$configValue = $this->getRawConfigValue($key);
		if ($configValue === null) {
			$this->appConfig->setValueString(Application::APP_ID, $key, json_encode($default, JSON_UNESCAPED_UNICODE));
			return;
		}

		$normalized = $this->normalizeConfigValue($configValue, $default);
		$this->forceStringType($key);

		try {
			$this->appConfig->setValueString(Application::APP_ID, $key, json_encode($normalized, JSON_UNESCAPED_UNICODE));
		} catch (AppConfigTypeConflictException) {
			$this->forceStringType($key);
			$this->appConfig->setValueString(Application::APP_ID, $key, json_encode($normalized, JSON_UNESCAPED_UNICODE));
		}
	}

	private function getRawConfigValue(string $key): ?string {
		$qb = $this->connection->getQueryBuilder();
		$qb->select('configvalue')
			->from('appconfig')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter(Application::APP_ID)))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter($key)))
			->setMaxResults(1);

		$result = $qb->executeQuery()->fetchOne();
		if ($result === false || $result === null) {
			return null;
		}

		return (string)$result;
	}

	private function forceStringType(string $key): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->update('appconfig')
			->set('type', $qb->createNamedParameter(IAppConfig::VALUE_STRING))
			->where($qb->expr()->eq('appid', $qb->createNamedParameter(Application::APP_ID)))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter($key)))
			->executeStatement();
	}

	/**
	 * @param list<string> $default
	 * @return list<string>
	 */
	private function normalizeConfigValue(string $raw, array $default): array {
		$decoded = json_decode($raw, true);
		if (is_array($decoded)) {
			return array_values(array_map(static fn (mixed $item): string => (string)$item, $decoded));
		}

		if ($raw === '') {
			return [];
		}

		return $default;
	}
}
