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
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;

use Psr\Log\LoggerInterface;

class UserDeleted extends QueuedJob {
	public function __construct(
		protected FileMapper $fileMapper,
		protected IdentifyMethodMapper $identifyMethodMapper,
		protected UserElementMapper $userElementMapper,
		protected ITimeFactory $time,
		protected LoggerInterface $logger,
	) {
		parent::__construct($time);
	}

	/**
	 * @param array $argument
	 */
	public function run($argument): void {
		if (!isset($argument['user_id'])) {
			return;
		}
		$userId = $argument['user_id'];
		$displayName = $argument['display_name'];
		$this->logger->info('Neutralizing data for deleted user {user}', [
			'user' => $userId
		]);
		$this->fileMapper->neutralizeDeletedUser($userId, $displayName);
		$this->identifyMethodMapper->neutralizeDeletedUser($userId, $displayName);
		$this->userElementMapper->neutralizeDeletedUser($userId, $displayName);
	}
}
