<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\BackgroundJob;

use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Enum\CRLReason;
use OCA\Libresign\Service\CrlService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;

use Psr\Log\LoggerInterface;

class UserDeleted extends QueuedJob {
	public function __construct(
		protected FileMapper $fileMapper,
		protected IdentifyMethodMapper $identifyMethodMapper,
		protected UserElementMapper $userElementMapper,
		protected CrlService $crlService,
		protected ITimeFactory $time,
		protected LoggerInterface $logger,
	) {
		parent::__construct($time);
	}

	/**
	 * @param array $argument
	 */
	#[\Override]
	public function run($argument): void {
		if (!isset($argument['user_id'])) {
			return;
		}
		$userId = $argument['user_id'];
		$displayName = $argument['display_name'];
		$this->logger->info('Neutralizing data for deleted user {user}', [
			'user' => $userId
		]);

		$this->revokeCertificates($userId);
		$this->neutralizeUserData($userId, $displayName);
	}

	private function revokeCertificates(string $userId): void {
		try {
			$revokedCount = $this->crlService->revokeUserCertificates(
				$userId,
				CRLReason::CESSATION_OF_OPERATION,
				'User account deleted',
				'system'
			);

			if ($revokedCount > 0) {
				$this->logger->info('Revoked {count} certificate(s) for deleted user {user}', [
					'count' => $revokedCount,
					'user' => $userId
				]);
			}
		} catch (\Exception $e) {
			$this->logger->error('Failed to revoke certificates for deleted user {user}: {error}', [
				'user' => $userId,
				'error' => $e->getMessage()
			]);
		}
	}

	private function neutralizeUserData(string $userId, string $displayName): void {
		$this->fileMapper->neutralizeDeletedUser($userId, $displayName);
		$this->identifyMethodMapper->neutralizeDeletedUser($userId, $displayName);
		$this->userElementMapper->neutralizeDeletedUser($userId, $displayName);
	}
}
