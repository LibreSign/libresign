<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use DateTime;
use OCA\Libresign\Db\CrlMapper;
use OCP\DB\Exception as DBException;
use Psr\Log\LoggerInterface;

class SerialNumberService {
	private const MAX_RETRY_ATTEMPTS = 10;
	private const MIN_SERIAL = 1000000;
	private const MAX_32BIT_SERIAL = 2147483647;

	public function __construct(
		private CrlMapper $crlMapper,
		private LoggerInterface $logger,
	) {
	}

	public function generateUniqueSerial(?string $certificateOwner = null, ?DateTime $expiresAt = null): int {
		for ($attempts = 0; $attempts < self::MAX_RETRY_ATTEMPTS; $attempts++) {
			$serial = random_int(self::MIN_SERIAL, self::MAX_32BIT_SERIAL);

			try {
				$this->crlMapper->createCertificate(
					$serial,
					$certificateOwner ?? 'Unknown',
					new DateTime(),
					$expiresAt
				);
				return $serial;

			} catch (DBException $e) {
				if ($e->getReason() === DBException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
					continue;
				}
				throw $e;
			}
		}

		throw new \RuntimeException(
			'Failed to generate unique serial number after ' . self::MAX_RETRY_ATTEMPTS . ' attempts'
		);
	}
}
