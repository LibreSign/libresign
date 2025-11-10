<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\Service\CrlService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class CrlController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private CrlService $crlService,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get Certificate Revocation List in DER format (RFC 5280 compliant)
	 *
	 * @param string $instanceId Instance identifier
	 * @param int $generation Generation identifier
	 * @param string $engineType Engine type identifier
	 * @return DataDownloadResponse<Http::STATUS_OK, string, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string, message: string}, array{}>
	 *
	 * 200: CRL retrieved successfully in DER format
	 * 500: Failed to generate CRL
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[FrontpageRoute(verb: 'GET', url: '/crl/libresign_{instanceId}_{generation}_{engineType}.crl')]
	public function getRevocationList(string $instanceId, int $generation, string $engineType): DataDownloadResponse|DataResponse {
		try {
			$crlDer = $this->crlService->generateCrlDer($instanceId, $generation, $engineType);

			return new DataDownloadResponse(
				$crlDer,
				'libresign_' . $instanceId . '_' . $generation . '_' . $engineType . '.crl',
				'application/pkix-crl'
			);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to generate CRL', ['exception' => $e]);

			return new DataResponse([
				'error' => 'CRL generation failed',
				'message' => $e->getMessage()
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Check certificate revocation status
	 *
	 * @param string $serialNumber Certificate serial number to check
	 * @return DataResponse<Http::STATUS_OK, array{serial_number: string, status: string, checked_at: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string, message: string}, array{}>
	 *
	 * 200: Certificate status retrieved successfully
	 * 400: Invalid serial number format
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[FrontpageRoute(verb: 'GET', url: '/crl/check/{serialNumber}')]
	public function checkCertificateStatus(string $serialNumber): DataResponse {
		if (!$this->isValidSerial($serialNumber)) {
			return new DataResponse(
				['error' => 'Invalid serial number', 'message' => 'Serial number must be numeric (decimal or hex format, no 0x prefix)'],
				Http::STATUS_BAD_REQUEST
			);
		}

		return new DataResponse($this->crlService->getCertificateStatusResponse($serialNumber));
	}

	private function isValidSerial(string $serialNumber): bool {
		$serialNumber = trim($serialNumber);

		if ($serialNumber === '') {
			return false;
		}

		if (str_starts_with(strtolower($serialNumber), '0x')) {
			return false;
		}

		if (ctype_digit($serialNumber)) {
			return true;
		}

		return ctype_xdigit($serialNumber);
	}
}
