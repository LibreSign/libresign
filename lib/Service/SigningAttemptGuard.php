<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

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

	/**
	 * Return false for an already completed or unsafe-to-repeat operation.
	 */
	public function execute(array $argument, string $jobId, callable $run): bool {
		$id = $argument['signRequestId'] ?? null;
		if (!is_int($id) || $id <= 0) {
			// Let the coordinator report malformed jobs through its usual error handler.
			return $run() !== false;
		}

		$key = 'libresign:sign-attempt:' . $id;
		$this->lockingProvider->acquireLock($key, ILockingProvider::LOCK_EXCLUSIVE);
		try {
			$request = $this->signRequestMapper->getByIdUncached($id);
			if ($request->getSignedHash() || $request->getSigned() !== null) {
				return false;
			}

			$metadata = $request->getMetadata() ?? [];
			$previous = $metadata[self::METADATA_KEY] ?? null;
			if (is_array($previous)) {
				$phase = $previous['phase'] ?? null;
				if ($phase === 'running' || $phase === 'uncertain') {
					$previousJobId = $previous['jobId'] ?? null;
					if ($phase === 'running' && is_string($previousJobId)
						&& $previousJobId !== $jobId && $this->jobList->getDetailsById($previousJobId) !== null) {
						// The first attempt still owns a persistent job. This is a
						// duplicate request, not evidence that signing has stopped.
						return false;
					}
					// Do not repeat an operation that may have already written a PDF.
					$metadata[self::METADATA_KEY]['phase'] = 'uncertain';
					$request->setMetadata($metadata);
					$this->signRequestMapper->update($request);
					$this->logger->warning('Interrupted signing attempt needs review', ['signRequestId' => $id]);
					$this->errorReporter->error('An earlier signing attempt did not finish safely', [
						'signRequestId' => $id,
						'signRequestUuid' => $request->getUuid(),
						'fileId' => $argument['fileId'] ?? null,
						'exception' => new \RuntimeException('The previous signing attempt may have finished. The document needs review before another signature.'),
					]);
					return false;
				}
				if ($phase === 'finished' && ($previous['jobId'] ?? null) === $jobId) {
					return false;
				}
			}

			$metadata[self::METADATA_KEY] = [
				'jobId' => $jobId,
				'phase' => 'running',
				'startedAt' => time(),
			];
			$request->setMetadata($metadata);
			$this->signRequestMapper->update($request);
		} finally {
			$this->lockingProvider->releaseLock($key, ILockingProvider::LOCK_EXCLUSIVE);
		}

		// Never hold a database or application lock while the PDF engine signs.
		// A crashed process leaves 'running' for safe reconciliation.
		$completed = $run() !== false;

		$this->lockingProvider->acquireLock($key, ILockingProvider::LOCK_EXCLUSIVE);
		try {
			$request = $this->signRequestMapper->getByIdUncached($id);
			$metadata = $request->getMetadata() ?? [];
			if (($metadata[self::METADATA_KEY]['jobId'] ?? null) === $jobId) {
				$metadata[self::METADATA_KEY]['phase'] = $completed ? 'finished' : 'uncertain';
				$metadata[self::METADATA_KEY]['finishedAt'] = time();
				$request->setMetadata($metadata);
				$this->signRequestMapper->update($request);
			}
		} finally {
			$this->lockingProvider->releaseLock($key, ILockingProvider::LOCK_EXCLUSIVE);
		}
		return $completed;
	}

	/** A normal job return may safely reset a stale, unfinished file after timeout. */
	public function hasFinishedAttempt(int $signRequestId): bool {
		$request = $this->signRequestMapper->getByIdUncached($signRequestId);
		$attempt = ($request->getMetadata() ?? [])[self::METADATA_KEY] ?? null;
		return is_array($attempt) && ($attempt['phase'] ?? null) === 'finished';
	}

	/** Whether resetting this file could let a user repeat an uncertain signature. */
	public function hasUncertainAttempt(int $signRequestId): bool {
		$request = $this->signRequestMapper->getByIdUncached($signRequestId);
		$attempt = ($request->getMetadata() ?? [])[self::METADATA_KEY] ?? null;
		return is_array($attempt) && in_array($attempt['phase'] ?? null, ['running', 'uncertain'], true);
	}
}
