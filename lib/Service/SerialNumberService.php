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
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

class SerialNumberService {
	private const MAX_RETRY_ATTEMPTS = 10;
	private const SERIAL_MAX_VALUE = 9223372036854775807;

	public function __construct(
		private CrlMapper $crlMapper,
		private LoggerInterface $logger,
		private ISecureRandom $secureRandom,
	) {
	}

	public function generateUniqueSerial(string $certificateOwner, string $instanceId, DateTime $expiresAt, string $engineName): string {
		for ($attempts = 0; $attempts < self::MAX_RETRY_ATTEMPTS; $attempts++) {
			$serialInt = random_int(1, self::SERIAL_MAX_VALUE);

			$serialString = (string)$serialInt;

			try {
				$this->crlMapper->createCertificate(
					$serialString,
					$certificateOwner ?? 'Unknown',
					$engineName,
					$instanceId,
					new DateTime(),
					$expiresAt
				);

				return $serialString;

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
