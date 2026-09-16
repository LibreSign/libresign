<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\GeoIp;

use OCA\Libresign\Enum\SignerIpGeolocationUnavailableReason;
use OCA\Libresign\Vendor\GeoIp2\Database\Reader;
use OCA\Libresign\Vendor\GeoIp2\Exception\AddressNotFoundException;
use Psr\Log\LoggerInterface;

/**
 * Looks up approximate location from a local MaxMind City database.
 *
 * MaxMind-specific access stays behind this service. An opened reader is reused
 * for the lifetime of this request-scoped service instance.
 */
class GeoIpLookupService {
	private ?Reader $reader = null;
	private ?string $openedPath = null;

	public function __construct(
		private GeoIpConfigService $geoIpConfigService,
		private GeoIpDatabaseStatusService $geoIpDatabaseStatusService,
		private GeoIpLookupResultNormalizer $normalizer,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @return array<string, mixed>
	 */
	public function lookup(?string $ipAddress): array {
		$sourceIp = is_string($ipAddress) ? trim($ipAddress) : '';
		$sourceIp = $sourceIp === '' ? null : $sourceIp;

		if ($sourceIp === null) {
			return $this->normalizer->normalizeUnavailable(
				SignerIpGeolocationUnavailableReason::ADDRESS_UNAVAILABLE,
				null,
			);
		}

		if (!$this->geoIpDatabaseStatusService->isReady()) {
			return $this->normalizer->normalizeUnavailable(
				SignerIpGeolocationUnavailableReason::DATABASE_NOT_READY,
				$sourceIp,
			);
		}

		try {
			$reader = $this->getReader();
			if ($reader === null) {
				return $this->normalizer->normalizeUnavailable(
					SignerIpGeolocationUnavailableReason::DATABASE_NOT_READY,
					$sourceIp,
				);
			}

			$city = $reader->city($sourceIp);
			return $this->normalizer->normalizeResolved($city, $sourceIp);
		} catch (AddressNotFoundException) {
			return $this->normalizer->normalizeNotFound($sourceIp);
		} catch (\Throwable $exception) {
			$this->logger->warning('GeoIP lookup failed', [
				'exception' => $exception::class,
			]);
			return $this->normalizer->normalizeUnavailable(
				SignerIpGeolocationUnavailableReason::LOOKUP_FAILED,
				$sourceIp,
			);
		}
	}

	private function getReader(): ?Reader {
		$path = $this->geoIpConfigService->getDatabasePath();
		if ($path === null) {
			$this->closeReader();
			return null;
		}

		if ($this->reader !== null && $this->openedPath === $path) {
			return $this->reader;
		}

		$this->closeReader();

		try {
			$this->reader = new Reader($path);
			$this->openedPath = $path;
			return $this->reader;
		} catch (\Throwable $exception) {
			$this->logger->warning('GeoIP database reader could not be opened', [
				'exception' => $exception::class,
			]);
			return null;
		}
	}

	public function __destruct() {
		$this->closeReader();
	}

	private function closeReader(): void {
		if ($this->reader !== null) {
			try {
				$this->reader->close();
			} catch (\Throwable) {
				// Ignore close failures on request shutdown.
			}
		}
		$this->reader = null;
		$this->openedPath = null;
	}
}
