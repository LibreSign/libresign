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
use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Service\FileElementService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type LibresignCoordinate from ResponseDefinitions
 */
class FileElementController extends AEnvironmentAwareController {
	public function __construct(
		IRequest $request,
		private FileElementService $fileElementService,
		private IUserSession $userSession,
		private ValidateHelper $validateHelper,
		private LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Create visible element
	 *
	 * Create visible element of a specific file
	 *
	 * @param string $uuid UUID of sign request. The signer UUID is what the person receives via email when asked to sign. This is not the file UUID.
	 * @param integer $signRequestId Id of sign request
	 * @param integer|null $elementId ID of visible element. Each element has an ID that is returned on validation endpoints.
	 * @param string $type The type of element to create, sginature, sinitial, date, datetime, text
	 * @param array{} $metadata Metadata of visible elements to associate with the document
	 * @param LibresignCoordinate $coordinates Coortinates of a visible element on PDF
	 * @return DataResponse<Http::STATUS_OK, array{fileElementId: integer}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{errors: string[]}, array{}>
	 *
	 * 200: OK
	 * 404: Failure when create visible element
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/file-element/{uuid}', requirements: ['apiVersion' => '(v1)'])]
	public function post(string $uuid, int $signRequestId, ?int $elementId = null, string $type = '', array $metadata = [], array $coordinates = []): DataResponse {
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
			$fileElement = $this->fileElementService->saveVisibleElement($visibleElement, $uuid);
			$statusCode = Http::STATUS_OK;
			return new DataResponse([
				'fileElementId' => $fileElement->getId(),
			]);
		} catch (\Throwable $th) {
			$this->logger->error($th->getMessage());
			return new DataResponse(
				[
					'errors' => [$th->getMessage()]
				],
				Http::STATUS_NOT_FOUND
			);
		}
	}

	/**
	 * Update visible element
	 *
	 * Update visible element of a specific file
	 *
	 * @param string $uuid UUID of sign request. The signer UUID is what the person receives via email when asked to sign. This is not the file UUID.
	 * @param integer $signRequestId Id of sign request
	 * @param integer|null $elementId ID of visible element. Each element has an ID that is returned on validation endpoints.
	 * @param string $type The type of element to create, sginature, sinitial, date, datetime, text
	 * @param array{} $metadata Metadata of visible elements to associate with the document
	 * @param LibresignCoordinate $coordinates Coortinates of a visible element on PDF
	 * @return DataResponse<Http::STATUS_OK, array{fileElementId: integer}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{errors: string[]}, array{}>
	 *
	 * 200: OK
	 * 404: Failure when patch visible element
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'PATCH', url: '/api/{apiVersion}/file-element/{uuid}/{elementId}', requirements: ['apiVersion' => '(v1)'])]
	public function patch(string $uuid, int $signRequestId, ?int $elementId = null, string $type = '', array $metadata = [], array $coordinates = []): DataResponse {
		return $this->post($uuid, $signRequestId, $elementId, $type, $metadata, $coordinates);
	}

	/**
	 * Delete visible element
	 *
	 * Delete visible element of a specific file
	 *
	 * @param string $uuid UUID of sign request. The signer UUID is what the person receives via email when asked to sign. This is not the file UUID.
	 * @param integer $elementId ID of visible element. Each element has an ID that is returned on validation endpoints.
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{errors: string[]}, array{}>
	 *
	 * 200: OK
	 * 404: Failure when delete visible element or file not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/file-element/{uuid}/{elementId}', requirements: ['apiVersion' => '(v1)'])]
	public function delete(string $uuid, int $elementId): DataResponse {
		try {
			$this->validateHelper->validateExistingFile([
				'uuid' => $uuid,
				'userManager' => $this->userSession->getUser()
			]);
			$this->validateHelper->validateAuthenticatedUserIsOwnerOfPdfVisibleElement($elementId, $this->userSession->getUser()->getUID());
			$this->fileElementService->deleteVisibleElement($elementId);
			return new DataResponse();
		} catch (\Throwable $th) {
			$this->logger->error($th->getMessage());
			return new DataResponse(
				[
					'errors' => [$th->getMessage()]
				],
				Http::STATUS_NOT_FOUND
			);
		}
	}
}
