<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Middleware\Attribute\RequireManager;
use OCA\Libresign\Service\DirectSigningService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

final class DirectSignController extends AEnvironmentAwareController {
	public function __construct(
		IRequest $request,
		private IUserSession $userSession,
		private DirectSigningService $directSigningService,
		private LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Create and sign a PDF as the authenticated account in a single call.
	 *
	 * The authenticated user must be authorized to request signatures and
	 * have the requested account signature method enabled. This endpoint
	 * does not accept arbitrary signers, visible fields or remote URLs.
	 *
	 * Uses ordinary Nextcloud application-password authentication and
	 * OCS-APIRequest: true. This is a synchronous convenience operation.
	 * The caller is responsible for not retrying an uncertain result as a
	 * brand-new request: no additional idempotency state is introduced.
	 *
	 * No visible signature elements are requested, but an enabled PDF footer
	 * policy may still change the visual presentation of the document.
	 *
	 * @param array<string, mixed> $file One PDF: {base64}, {nodeId}, or {path}
	 * @param string $name PDF filename ending in .pdf
	 * @param string $method Account signature method: clickToSign or password
	 * @param string $token Token or certificate password if required by method
	 * @param array<string, mixed> $settings Optional request file settings
	 * @param array<string, mixed>|null $policy Authorized request-level policy overrides
	 *
	 * @return DataDownloadResponse<Http::STATUS_OK, 'application/pdf', array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_CONFLICT|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Signed PDF document
	 * 400: Invalid direct-sign payload
	 * 403: Account signing not permitted
	 * 409: Signing not completed
	 * 422: Signing validation failed
	 * 500: Unexpected signing failure
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireManager]
	#[OpenAPI(tags: ['signing'])]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/sign', requirements: ['apiVersion' => '(v1)'])]
	public function signDirect(
		array $file = [],
		string $name = '',
		string $method = 'clickToSign',
		string $token = '',
		array $settings = [],
		?array $policy = null,
	): DataDownloadResponse|DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['message' => 'Authentication required.'], Http::STATUS_FORBIDDEN);
		}

		try {
			foreach (['signers', 'visibleElements', 'files', 'callback', 'identifyValue', 'uuid'] as $unsupported) {
				if ($this->request->getParam($unsupported) !== null) {
					throw new LibresignException('Unsupported direct signing parameter: ' . $unsupported, Http::STATUS_BAD_REQUEST);
				}
			}
			$signed = $this->directSigningService->sign(
				$user,
				$file,
				$name,
				$method,
				$token,
				$settings,
				$policy,
			);
			$response = new DataDownloadResponse($signed['content'], $signed['filename'], 'application/pdf');
			$response->addHeader('X-LibreSign-File-UUID', $signed['uuid']);
			$response->addHeader('Cache-Control', 'no-store');
			return $response;
		} catch (LibresignException $e) {
			$status = in_array($e->getCode(), [
				Http::STATUS_BAD_REQUEST, Http::STATUS_FORBIDDEN, Http::STATUS_CONFLICT,
			], true) ? $e->getCode() : Http::STATUS_UNPROCESSABLE_ENTITY;
			return new DataResponse(['message' => $e->getMessage()], $status);
		} catch (\Throwable $e) {
			$this->logger->error('Direct signing failed', ['exception' => $e]);
			return new DataResponse(['message' => 'Unable to complete the direct signing request.'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
