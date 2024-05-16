<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\FileElementService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class FileElementController extends AEnvironmentAwareController {
	public function __construct(
		IRequest $request,
		private FileElementService $fileElementService,
		private IUserSession $userSession,
		private ValidateHelper $validateHelper,
		private LoggerInterface $logger
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function post(string $uuid, int $signRequestId, int $elementId = null, string $type = '', array $metadata = [], array $coordinates = []): JSONResponse {
		$visibleElement = [
			'elementId' => $elementId,
			'type' => $type,
			'signRequestId' => $signRequestId,
			'coordinates' => $coordinates,
			'metadata' => $metadata,
			'fileUuid' => $uuid,
		];
		try {
			$this->validateHelper->validateVisibleElement($visibleElement, ValidateHelper::TYPE_VISIBLE_ELEMENT_PDF);
			$this->validateHelper->validateExistingFile([
				'uuid' => $uuid,
				'userManager' => $this->userSession->getUser()
			]);
			$this->validateHelper->signerCanHaveVisibleElement($signRequestId);
			$fileElement = $this->fileElementService->saveVisibleElement($visibleElement, $uuid);
			$return = [
				'fileElementId' => $fileElement->getId(),
			];
			$statusCode = Http::STATUS_OK;
		} catch (\Throwable $th) {
			$this->logger->error($th->getMessage());
			$return = [
				'errors' => [$th->getMessage()]
			];
			$statusCode = $th->getCode() > 0 ? $th->getCode() : Http::STATUS_NOT_FOUND;
		}
		return new JSONResponse($return, $statusCode);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function patch(string $uuid, int $signRequestId, int $elementId = null, string $type = '', array $metadata = [], array $coordinates = []): JSONResponse {
		return $this->post($uuid, $signRequestId, $elementId, $type, $metadata, $coordinates);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function delete(string $uuid, int $elementId): JSONResponse {
		try {
			$this->validateHelper->validateExistingFile([
				'uuid' => $uuid,
				'userManager' => $this->userSession->getUser()
			]);
			$this->validateHelper->validateAuthenticatedUserIsOwnerOfPdfVisibleElement($elementId, $this->userSession->getUser()->getUID());
			$this->fileElementService->deleteVisibleElement($elementId);
			$return = [];
			$statusCode = Http::STATUS_OK;
		} catch (\Throwable $th) {
			$this->logger->error($th->getMessage());
			$return = [
				'errors' => [$th->getMessage()]
			];
			$statusCode = $th->getCode() > 0 ? $th->getCode() : Http::STATUS_NOT_FOUND;
		}
		return new JSONResponse($return, $statusCode);
	}
}
