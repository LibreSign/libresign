<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\GeoIp;

use OCA\Libresign\Enum\GeoIpDatabaseStatus;
use OCA\Libresign\Vendor\GeoIp2\Database\Reader;
use OCA\Libresign\Vendor\MaxMind\Db\Reader\InvalidDatabaseException;
use Psr\Log\LoggerInterface;

/**
 * @psalm-type GeoIpDatabaseStatusPayload = array{
 *     path: string|null,
 *     status: string,
 *     databaseType?: string,
 *     buildEpoch?: int,
 *     modifiedAt?: string,
 * }
 */
class GeoIpDatabaseStatusService {
	/** @var list<string> */
	public const SUPPORTED_DATABASE_TYPES = [
		'GeoIP2-City',
		'GeoLite2-City',
	];

	public function __construct(
		private GeoIpConfigService $geoIpConfigService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @return GeoIpDatabaseStatusPayload
	 */
	public function getStatus(): array {
		$path = $this->geoIpConfigService->getDatabasePath();
		if ($path === null) {
			return [
				'path' => null,
				'status' => GeoIpDatabaseStatus::NOT_CONFIGURED->value,
			];
		}

		$payload = [
			'path' => $path,
			'status' => GeoIpDatabaseStatus::NOT_CONFIGURED->value,
		];

		if (!file_exists($path)) {
			$payload['status'] = GeoIpDatabaseStatus::NOT_FOUND->value;
			return $payload;
		}

		if (!is_readable($path)) {
			$payload['status'] = GeoIpDatabaseStatus::NOT_READABLE->value;
			return $payload;
		}

		$modifiedAt = $this->readModifiedAt($path);
		if ($modifiedAt !== null) {
			$payload['modifiedAt'] = $modifiedAt;
		}

		try {
			$reader = new Reader($path);
			try {
				$metadata = $reader->metadata();
				$databaseType = $metadata->databaseType;
				$payload['databaseType'] = $databaseType;
				$payload['buildEpoch'] = $metadata->buildEpoch;

				if (!$this->isSupportedDatabaseType($databaseType)) {
					$payload['status'] = GeoIpDatabaseStatus::UNSUPPORTED_DATABASE->value;
					return $payload;
				}

				$payload['status'] = GeoIpDatabaseStatus::READY->value;
				return $payload;
			} finally {
				$reader->close();
			}
		} catch (InvalidDatabaseException|\Throwable $exception) {
			$this->logger->warning('GeoIP database validation failed', [
				'exception' => $exception::class,
			]);
			$payload['status'] = GeoIpDatabaseStatus::INVALID_DATABASE->value;
			return $payload;
		}
	}

	public function isReady(): bool {
		return ($this->getStatus()['status'] ?? null) === GeoIpDatabaseStatus::READY->value;
	}

	public function isSupportedDatabaseType(string $databaseType): bool {
		return in_array($databaseType, self::SUPPORTED_DATABASE_TYPES, true);
	}

	private function readModifiedAt(string $path): ?string {
		$mtime = @filemtime($path);
		if ($mtime === false) {
			return null;
		}

		try {
			return (new \DateTimeImmutable('@' . $mtime))
				->setTimezone(new \DateTimeZone('UTC'))
				->format(\DateTimeInterface::ATOM);
		} catch (\Throwable) {
			return null;
		}
	}
}
