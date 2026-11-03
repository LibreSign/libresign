<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Service\SignRequest\Error\SignRequestErrorReporter;
use OCP\BackgroundJob\IJobList;
use OCP\Lock\ILockingProvider;
use Psr\Log\LoggerInterface;

/**
 * Durable execution receipt: prevent a recovered job from signing twice.
 * A crash after the signing engine starts is ambiguous until manually reconciled.
 */
class SigningAttemptGuard {
	private const string METADATA_KEY = 'async_signing_attempt';

	public function __construct(
		private SignRequestMapper $signRequestMapper,
		private ILockingProvider $lockingProvider,
		private IJobList $jobList,
		private SignRequestErrorReporter $errorReporter,
		private LoggerInterface $logger,
	) {
	}

	/** Return false for an already completed or unsafe-to-repeat operation. */
	public function execute(array $argument, string $jobId, callable $run): bool {
		$id = $argument['signRequestId'] ?? null;
		if (!is_int($id) || $id <= 0) {
			// Malformed arguments belong to the coordinator's error handler.
			return $run() !== false;
		}
		if (!$this->beginAttempt($id, $jobId, $argument)) {
			return false;
		}

		// Do not hold a lock during PDF signing. A crash deliberately leaves
		// a running receipt so the operation cannot be repeated blindly.
		$completed = $run() !== false;
		$this->completeAttempt($id, $jobId, $completed);
		return $completed;
	}

	private function beginAttempt(int $id, string $jobId, array $argument): bool {
		$key = 'libresign:sign-attempt:' . $id;
		$this->lockingProvider->acquireLock($key, ILockingProvider::LOCK_EXCLUSIVE);
		try {
			$request = $this->signRequestMapper->getByIdUncached($id);
			if ($request->getSignedHash() || $request->getSigned() !== null
				|| $this->shouldRejectPriorAttempt($request, $jobId, $argument)) {
				return false;
			}
			$metadata = $request->getMetadata() ?? [];
			$metadata[self::METADATA_KEY] = [
				'jobId' => $jobId,
				'phase' => 'running',
				'startedAt' => time(),
			];
			$request->setMetadata($metadata);
			$this->signRequestMapper->update($request);
			return true;
		} finally {
			$this->lockingProvider->releaseLock($key, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}

	private function shouldRejectPriorAttempt(SignRequest $request, string $jobId, array $argument): bool {
		$metadata = $request->getMetadata() ?? [];
		$previous = $metadata[self::METADATA_KEY] ?? null;
		if (!is_array($previous)) {
			return false;
		}
		$phase = $previous['phase'] ?? null;
		if ($phase === 'finished') {
			return true;
		}
		if ($phase !== 'running' && $phase !== 'uncertain') {
			return false;
		}
		$previousJobId = $previous['jobId'] ?? null;
		if ($phase === 'running' && is_string($previousJobId) && ctype_digit($previousJobId)
			&& $previousJobId !== $jobId && $this->jobList->getDetailsById($previousJobId) !== null) {
			// Another queued job still owns the running attempt.
			return true;
		}
		$metadata[self::METADATA_KEY]['phase'] = 'uncertain';
		$request->setMetadata($metadata);
		$this->signRequestMapper->update($request);
		$this->logger->warning('Interrupted signing attempt needs review', ['signRequestId' => $request->getId()]);
		$this->errorReporter->error('An earlier signing attempt did not finish safely', [
			'signRequestId' => $request->getId(),
			'signRequestUuid' => $request->getUuid(),
			'fileId' => $argument['fileId'] ?? null,
			'exception' => new \RuntimeException('The previous signing attempt may have finished. The document needs review before another signature.'),
		]);
		return true;
	}

	private function completeAttempt(int $id, string $jobId, bool $completed): void {
		$key = 'libresign:sign-attempt:' . $id;
		$this->lockingProvider->acquireLock($key, ILockingProvider::LOCK_EXCLUSIVE);
		try {
			$request = $this->signRequestMapper->getByIdUncached($id);
			$metadata = $request->getMetadata() ?? [];
			if (($metadata[self::METADATA_KEY]['jobId'] ?? null) !== $jobId) {
				return;
			}
			$metadata[self::METADATA_KEY]['phase'] = $completed ? 'finished' : 'uncertain';
			$metadata[self::METADATA_KEY]['finishedAt'] = time();
			$request->setMetadata($metadata);
			$this->signRequestMapper->update($request);
		} finally {
			$this->lockingProvider->releaseLock($key, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}
}
