<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignRequest;

use OCP\ICache;
use OCP\ICacheFactory;

class StatusCacheService {
	public const STATUS_KEY_PREFIX = 'libresign_status_';
	public const DEFAULT_TTL = 60;

	private ICache $cache;

	public function __construct(ICacheFactory $cacheFactory) {
		$this->cache = $cacheFactory->createDistributed('libresign_progress');
	}

	public function setStatus(string $fileUuid, int $status, int $ttl = self::DEFAULT_TTL): void {
		if ($fileUuid === '') {
			return;
		}
		$this->cache->set(self::STATUS_KEY_PREFIX . $fileUuid, $status, $ttl);
	}

	public function getStatus(string $fileUuid): mixed {
		if ($fileUuid === '') {
			return false;
		}
		return $this->cache->get(self::STATUS_KEY_PREFIX . $fileUuid);
	}
}
