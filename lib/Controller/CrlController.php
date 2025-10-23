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
	 * @return DataDownloadResponse<Http::STATUS_OK, string, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string, message: string}, array{}>
	 *
	 * 200: CRL retrieved successfully in DER format
	 * 500: Failed to generate CRL
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[FrontpageRoute(verb: 'GET', url: '/crl')]
	public function getRevocationList(): DataDownloadResponse|DataResponse {
		try {
			$crlDer = $this->crlService->generateCrlDer();

			return new DataDownloadResponse(
				$crlDer,
				'crl.crl',
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
		if (!is_numeric($serialNumber) || (int)$serialNumber <= 0) {
			return new DataResponse(
				['error' => 'Invalid serial number', 'message' => 'Serial number must be a positive integer'],
				Http::STATUS_BAD_REQUEST
			);
		}

		return new DataResponse($this->crlService->getCertificateStatusResponse((int)$serialNumber));
	}
}
