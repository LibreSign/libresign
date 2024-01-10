<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\FileElementService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
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
			$this->validateHelper->validateUserIsOwnerOfPdfVisibleElement($elementId, $this->userSession->getUser()->getUID());
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
