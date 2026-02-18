<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use OCA\Libresign\Service\NodeCleanupService;
use OCP\IDBConnection;
use OCP\Files\IRootFolder;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

class CleanupOrphanNodeIds implements IRepairStep {
	public function __construct(
		private IDBConnection $connection,
		private IRootFolder $rootFolder,
		private LoggerInterface $logger,
		private NodeCleanupService $cleanupService,
	) {
	}

	#[\Override]
	public function getName(): string {
		return 'Cleanup orphan LibreSign node ids';
	}

	#[\Override]
	public function run(IOutput $output): void {
		try {
			$processed = $this->cleanupOrphanRecords();

			if ($processed > 0) {
				$output->info(sprintf(
					'Cleaned up %d orphan LibreSign record(s).',
					$processed
				));
			}
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to cleanup orphan LibreSign records during upgrade', [
				'error' => $e->getMessage(),
				'exception' => $e,
			]);
		}
	}

	/**
	 * Cleanup orphan records using the same business logic as BeforeNodeDeletedListener.
	 */
	private function cleanupOrphanRecords(): int {
		$processed = 0;

		$processed += $this->cleanupOrphanSignedFiles();
		$processed += $this->cleanupOrphanFileNodes();
		$processed += $this->cleanupOrphanUserElements();

		return $processed;
	}

	/**
	 * Handle files where signed_node_id points to a deleted node.
	 */
	private function cleanupOrphanSignedFiles(): int {
		$qb = $this->connection->getQueryBuilder();
		$qb->select('signed_node_id')
			->from('libresign_file')
			->where($qb->expr()->isNotNull('signed_node_id'))
			->groupBy('signed_node_id');

		$result = $qb->executeQuery();
		$processed = 0;

		while ($row = $result->fetchAssociative()) {
			$signedNodeId = (int)$row['signed_node_id'];
			if (!$this->nodeExists($signedNodeId)) {
				$processed += $this->cleanupService->cleanupSignedFilesByNodeId($signedNodeId);
			}
		}
		$result->closeCursor();

		return $processed;
	}

	/**
	 * Handle files where node_id points to a deleted node.
	 */
	private function cleanupOrphanFileNodes(): int {
		$qb = $this->connection->getQueryBuilder();
		$qb->select('node_id')
			->from('libresign_file')
			->where($qb->expr()->isNotNull('node_id'))
			->groupBy('node_id');

		$result = $qb->executeQuery();
		$processed = 0;

		while ($row = $result->fetchAssociative()) {
			$nodeId = (int)$row['node_id'];
			if (!$this->nodeExists($nodeId)) {
				$this->cleanupService->deleteAllByNodeId($nodeId);
				$processed++;
			}
		}
		$result->closeCursor();

		return $processed;
	}

	/**
	 * Handle user elements where node_id points to a deleted node.
	 */
	private function cleanupOrphanUserElements(): int {
		$qb = $this->connection->getQueryBuilder();
		$qb->select('node_id')
			->from('libresign_user_element')
			->where($qb->expr()->isNotNull('node_id'))
			->groupBy('node_id');

		$result = $qb->executeQuery();
		$processed = 0;

		while ($row = $result->fetchAssociative()) {
			$nodeId = (int)$row['node_id'];
			if (!$this->nodeExists($nodeId)) {
				$this->cleanupService->deleteUserElementByNodeId($nodeId);
				$processed++;
			}
		}
		$result->closeCursor();

		return $processed;
	}

	private function nodeExists(int $nodeId): bool {
		try {
			$nodes = $this->rootFolder->getById($nodeId);
			return !empty($nodes);
		} catch (\Throwable) {
			return false;
		}
	}
}
