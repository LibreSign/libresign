<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\Enum\CRLReason;
use OCA\Libresign\Service\CrlService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class CrlApiController extends AEnvironmentAwareController {
	public function __construct(
		string $appName,
		IRequest $request,
		private CrlService $crlService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List CRL entries with pagination and filters
	 *
	 * @param int|null $page Page number (1-based)
	 * @param int|null $length Number of items per page
	 * @param string|null $status Filter by status (issued, revoked, expired)
	 * @param string|null $engine Filter by engine type
	 * @param string|null $instanceId Filter by instance ID
	 * @param int|null $generation Filter by generation
	 * @param string|null $owner Filter by owner
	 * @param string|null $serialNumber Filter by serial number (partial match)
	 * @param string|null $revokedBy Filter by who revoked the certificate
	 * @param string|null $sortBy Sort field (e.g., 'revoked_at', 'issued_at', 'serial_number')
	 * @param string|null $sortOrder Sort order (ASC or DESC)
	 * @return DataResponse<Http::STATUS_OK, array{data: array<string, mixed>, total: int, page: int, length: int}, array{}>
	 *
	 * 200: CRL entries retrieved successfully
	 */
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/crl/list', requirements: ['apiVersion' => '(v1)'])]
	public function list(
		?int $page = null,
		?int $length = null,
		?string $status = null,
		?string $engine = null,
		?string $instanceId = null,
		?int $generation = null,
		?string $owner = null,
		?string $serialNumber = null,
		?string $revokedBy = null,
		?string $sortBy = null,
		?string $sortOrder = null,
	): DataResponse {
		$filter = array_filter([
			'status' => $status,
			'engine' => $engine,
			'instance_id' => $instanceId,
			'generation' => $generation,
			'owner' => $owner,
			'serial_number' => $serialNumber,
			'revoked_by' => $revokedBy,
		], fn ($value) => $value !== null);

		$sort = [];
		if ($sortBy !== null) {
			$sort[$sortBy] = $sortOrder ?? 'DESC';
		}

		$result = $this->crlService->listCrlEntries($page, $length, $filter, $sort);

		return new DataResponse($result);
	}

	/**
	 * Revoke a certificate by serial number
	 *
	 * @param string $serialNumber Certificate serial number to revoke
	 * @param int|null $reasonCode Revocation reason code (0-10, see RFC 5280)
	 * @param string|null $reasonText Optional text describing the reason
	 * @return DataResponse<Http::STATUS_OK, array{success: bool, message: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{success: bool, message: string}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{success: bool, message: string}, array{}>
	 *
	 * 200: Certificate revoked successfully
	 * 400: Invalid parameters
	 * 404: Certificate not found
	 */
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/crl/revoke', requirements: ['apiVersion' => '(v1)'])]
	public function revoke(
		string $serialNumber,
		?int $reasonCode = null,
		?string $reasonText = null,
	): DataResponse {
		if (empty($serialNumber)) {
			return new DataResponse([
				'success' => false,
				'message' => 'Serial number is required',
			], Http::STATUS_BAD_REQUEST);
		}

		$reason = CRLReason::tryFrom($reasonCode);
		if ($reason === null) {
			return new DataResponse([
				'success' => false,
				'message' => "Invalid reason code: {$reasonCode}. Must be between 0-10 (excluding 7).",
			], Http::STATUS_BAD_REQUEST);
		}

		$user = $this->userSession->getUser();
		$revokedBy = $user ? $user->getUID() : 'system';

		try {
			$success = $this->crlService->revokeCertificate(
				$serialNumber,
				$reason->value,
				$reasonText,
				$revokedBy
			);

			if ($success) {
				return new DataResponse([
					'success' => true,
					'message' => 'Certificate revoked successfully',
				]);
			} else {
				return new DataResponse([
					'success' => false,
					'message' => 'Failed to revoke certificate. It may not exist or already be revoked.',
				], Http::STATUS_NOT_FOUND);
			}
		} catch (\InvalidArgumentException $e) {
			return new DataResponse([
				'success' => false,
				'message' => $e->getMessage(),
			], Http::STATUS_BAD_REQUEST);
		} catch (\Exception $e) {
			return new DataResponse([
				'success' => false,
				'message' => 'An error occurred while revoking the certificate',
			], Http::STATUS_BAD_REQUEST);
		}
	}
}
