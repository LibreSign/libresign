<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Worker;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Lightweight readiness hint for the signing pool on supported Nextcloud 36.
 * IJobList::countByClass() includes both future and reserved jobs; the public
 * interface does not provide a read-only due-job count. Do not reserve here.
 */
class WorkerJobCounter {
	public function __construct(
		private IDBConnection $db,
		private WorkerConfiguration $configuration,
	) {
	}

	/** @return list<class-string> */
	public function jobClasses(string $pool): array {
		return match ($pool) {
			WorkerConfiguration::SIGNING => $this->configuration->getSigningJobClasses(),
			WorkerConfiguration::NOTIFICATION => $this->configuration->getNotificationJobClasses(),
			default => throw new \InvalidArgumentException('Unknown local worker pool'),
		};
	}

	public function countEligibleJobs(int $max, string $pool = WorkerConfiguration::SIGNING): int {
		if ($max <= 0) {
			return 0;
		}

		$now = time();
		$query = $this->db->getQueryBuilder();
		$query->select('id')->from('jobs')
			->where($query->expr()->in('class', $query->createNamedParameter(
				$this->jobClasses($pool), IQueryBuilder::PARAM_STR_ARRAY,
			)))
			->andWhere($query->expr()->lte('reserved_at', $query->createNamedParameter($now - 12 * 3600, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->lte('last_checked', $query->createNamedParameter($now, IQueryBuilder::PARAM_INT)))
			->setMaxResults(min($max, 32));

		$result = $query->executeQuery();
		try {
			$count = 0;
			while ($result->fetchOne() !== false) {
				$count++;
			}
			return $count;
		} finally {
			$result->closeCursor();
		}
	}
}
