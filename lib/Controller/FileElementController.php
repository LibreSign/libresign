<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\FileElementService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class FileElementController extends Controller {
	public function __construct(
		IRequest $request,
		private FileElementService $fileElementService,
		private IUserSession $userSession,
		private ValidateHelper $validateHelper,
		private LoggerInterface $logger
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @NoCSRFRequired
	 */
	public function post(string $uuid, int $fileUserId, int $elementId = null, string $type = '', array $metadata = [], array $coordinates = []): JSONResponse {
		$visibleElement = [
			'elementId' => $elementId,
			'type' => $type,
			'fileUserId' => $fileUserId,
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
			$fileElement = $this->fileElementService->saveVisibleElement($visibleElement, $uuid);
			$return = [
				'fileElementId' => $fileElement->getId(),
				'success' => true,
			];
			$statusCode = Http::STATUS_OK;
		} catch (\Throwable $th) {
			$this->logger->error($th->getMessage());
			$return = [
				'success' => false,
				'errors' => [$th->getMessage()]
			];
			$statusCode = $th->getCode() > 0 ? $th->getCode() : Http::STATUS_NOT_FOUND;
		}
		return new JSONResponse($return, $statusCode);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @NoCSRFRequired
	 */
	public function patch(string $uuid, int $fileUserId, int $elementId = null, string $type = '', array $metadata = [], array $coordinates = []): JSONResponse {
		return $this->post($uuid, $fileUserId, $elementId, $type, $metadata, $coordinates);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @NoCSRFRequired
	 */
	public function delete(string $uuid, int $elementId): JSONResponse {
		try {
			$this->validateHelper->validateExistingFile([
				'uuid' => $uuid,
				'userManager' => $this->userSession->getUser()
			]);
			$this->validateHelper->validateUserIsOwnerOfPdfVisibleElement($elementId, $this->userSession->getUser()->getUID());
			$this->fileElementService->deleteVisibleElement($elementId);
			$return = [];
			$statusCode = Http::STATUS_OK;
		} catch (\Throwable $th) {
			$this->logger->error($th->getMessage());
			$return = [
				'success' => false,
				'errors' => [$th->getMessage()]
			];
			$statusCode = $th->getCode() > 0 ? $th->getCode() : Http::STATUS_NOT_FOUND;
		}
		return new JSONResponse($return, $statusCode);
	}
}
