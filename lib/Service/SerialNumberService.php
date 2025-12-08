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

class SerialNumberService {
	private const MAX_RETRY_ATTEMPTS = 10;
	private const SERIAL_MAX_VALUE = 9223372036854775807;

	public function __construct(
		private CrlMapper $crlMapper,
	) {
	}

	public function generateUniqueSerial(
		string $certificateOwner,
		string $instanceId,
		int $generation,
		DateTime $expiresAt,
		string $engineName,
		?array $issuer = null,
		?array $subject = null,
		string $certificateType = 'leaf',
	): string {
		for ($attempts = 0; $attempts < self::MAX_RETRY_ATTEMPTS; $attempts++) {
			$serialInt = random_int(1, self::SERIAL_MAX_VALUE);

			$serialString = (string)$serialInt;

			try {
				$this->crlMapper->createCertificate(
					$serialString,
					$certificateOwner,
					$engineName,
					$instanceId,
					$generation,
					new DateTime(),
					$expiresAt,
					$issuer,
					$subject,
					$certificateType,
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
