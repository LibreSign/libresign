<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCA\Libresign\AppInfo\Application;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Re-run the CRL metadata repair for installations where Version17004 already
 * ran with predicates that could leave one or more legacy fields incomplete.
 */
class Version18005Date20260923000000 extends SimpleMigrationStep {
	public function __construct(
		private IAppConfig $appConfig,
		private IDBConnection $connection,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[\Override]
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('libresign_crl')) {
			return;
		}

		$metadata = $this->resolveMetadataDefaults();
		if ($metadata === null) {
			$output->warning('Skipped CRL metadata repair because no deterministic metadata source was found.');
			return;
		}

		$updatedInstance = $this->backfillInstanceId($metadata['instanceId'], $metadata['generation'], $metadata['engine']);
		$updatedGeneration = $this->backfillGeneration($metadata['instanceId'], $metadata['generation'], $metadata['engine']);
		$updatedEngine = $this->backfillEngine($metadata['instanceId'], $metadata['generation'], $metadata['engine']);

		if ($updatedInstance > 0 || $updatedGeneration > 0 || $updatedEngine > 0) {
			$output->warning(sprintf(
				'Repaired incomplete CRL metadata for legacy rows (instance_id=%d, generation=%d, engine=%d).',
				$updatedInstance,
				$updatedGeneration,
				$updatedEngine,
			));
		}
	}

	/**
	 * @return array{instanceId: string, generation: int, engine: string}|null
	 */
	private function resolveMetadataDefaults(): ?array {
		$caId = $this->appConfig->getValueString(Application::APP_ID, 'ca_id', '');
		$pattern = '/^libresign-ca-id:(?P<instanceId>[a-z0-9]+)_g:(?P<generation>\\d+)_e:(?P<engineType>[oc])$/';
		if ($caId === '' || preg_match($pattern, $caId, $matches) !== 1) {
			return null;
		}

		$generation = (int)$matches['generation'];
		if ($generation !== 1) {
			// Once a CA has rotated, assigning the current generation to an old
			// incomplete row is ambiguous. Preserve the row rather than risk
			// moving a certificate into the wrong CRL scope.
			return null;
		}

		return [
			'instanceId' => $matches['instanceId'],
			'generation' => $generation,
			'engine' => $matches['engineType'] === 'c' ? 'cfssl' : 'openssl',
		];
	}

	private function backfillInstanceId(string $instanceId, int $generation, string $engine): int {
		$qb = $this->connection->getQueryBuilder();

		return $qb->update('libresign_crl')
			->set('instance_id', $qb->createNamedParameter($instanceId))
			->where(
				$qb->expr()->orX(
					$qb->expr()->isNull('instance_id'),
					$qb->expr()->eq('instance_id', $qb->createNamedParameter('')),
				)
			)
			->andWhere(
				$qb->expr()->orX(
					$qb->expr()->isNull('generation'),
					$qb->expr()->eq('generation', $qb->createNamedParameter($generation, IQueryBuilder::PARAM_INT)),
				)
			)
			->andWhere(
				$qb->expr()->orX(
					$qb->expr()->isNull('engine'),
					$qb->expr()->eq('engine', $qb->createNamedParameter('')),
					$qb->expr()->eq('engine', $qb->createNamedParameter($engine)),
				)
			)
			->executeStatement();
	}

	private function backfillGeneration(string $instanceId, int $generation, string $engine): int {
		$qb = $this->connection->getQueryBuilder();

		return $qb->update('libresign_crl')
			->set('generation', $qb->createNamedParameter($generation, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->isNull('generation'))
			->andWhere(
				$qb->expr()->orX(
					$qb->expr()->isNull('instance_id'),
					$qb->expr()->eq('instance_id', $qb->createNamedParameter('')),
					$qb->expr()->eq('instance_id', $qb->createNamedParameter($instanceId)),
				)
			)
			->andWhere(
				$qb->expr()->orX(
					$qb->expr()->isNull('engine'),
					$qb->expr()->eq('engine', $qb->createNamedParameter('')),
					$qb->expr()->eq('engine', $qb->createNamedParameter($engine)),
				)
			)
			->executeStatement();
	}

	private function backfillEngine(string $instanceId, int $generation, string $engine): int {
		$qb = $this->connection->getQueryBuilder();

		return $qb->update('libresign_crl')
			->set('engine', $qb->createNamedParameter($engine))
			->where(
				$qb->expr()->orX(
					$qb->expr()->isNull('engine'),
					$qb->expr()->eq('engine', $qb->createNamedParameter('')),
				)
			)
			->andWhere(
				$qb->expr()->orX(
					$qb->expr()->isNull('instance_id'),
					$qb->expr()->eq('instance_id', $qb->createNamedParameter('')),
					$qb->expr()->eq('instance_id', $qb->createNamedParameter($instanceId)),
				)
			)
			->andWhere(
				$qb->expr()->orX(
					$qb->expr()->isNull('generation'),
					$qb->expr()->eq('generation', $qb->createNamedParameter($generation, IQueryBuilder::PARAM_INT)),
				)
			)
			->executeStatement();
	}
}
