<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2024 Vitor Mattos <vitor@php.rio>
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
use OCA\Libresign\Middleware\Attribute\RequireSignRequestUuid;
use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Service\AccountFileService;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\SessionService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Service\SignFileService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\IL10N;
use OCP\IPreview;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;

/**
 * @psalm-import-type LibresignUserElement from ResponseDefinitions
 */
class SignatureElementsController extends AEnvironmentAwareController implements ISignatureUuid {
	use LibresignTrait;
	public function __construct(
		IRequest $request,
		protected IL10N $l10n,
		private AccountService $accountService,
		private AccountFileService $accountFileService,
		private SignerElementsService $signerElementsService,
		protected IUserSession $userSession,
		protected SessionService $sessionService,
		protected SignFileService $signFileService,
		private IPreview $preview,
		private ValidateHelper $validateHelper
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Create signature element
	 *
	 * @param array<string, mixed> $elements Element object
	 * @return DataResponse<Http::STATUS_OK, array{elements: LibresignUserElement[], message: string}, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 422: Invalid data
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[RequireSignRequestUuid(skipIfAuthenticated: true)]
	public function createSignatureElement(array $elements): DataResponse {
		try {
			$this->validateHelper->validateVisibleElements($elements, $this->validateHelper::TYPE_VISIBLE_ELEMENT_USER);
			$this->accountService->saveVisibleElements(
				elements: $elements,
				sessionId: $this->sessionService->getSessionId(),
				user: $this->userSession->getUser(),
			);
		} catch (\Throwable $th) {
			return new DataResponse(
				[
					'message' => $th->getMessage(),
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
		return new DataResponse(
			[
				'message' => $this->l10n->n(
					'Element created with success',
					'Elements created with success',
					count($elements)
				),
				'elements' =>
					(
						$this->userSession->getUser() instanceof IUser
						? $this->signerElementsService->getUserElements($this->userSession->getUser()->getUID())
						: $this->signerElementsService->getElementsFromSessionAsArray()
					),
			],
			Http::STATUS_OK
		);
	}

	/**
	 * Get signature elements
	 *
	 * @return DataResponse<Http::STATUS_OK, array{elements: LibresignUserElement[]}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 404: Invalid data
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[RequireSignRequestUuid(skipIfAuthenticated: true)]
	public function getSignatureElements(): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		try {
			$elements = (
				$userId
				? $this->signerElementsService->getUserElements($userId)
				: $this->signerElementsService->getElementsFromSessionAsArray()
			);
			return new DataResponse(
				[
					'elements' => $elements,
				],
				Http::STATUS_OK
			);
		} catch (\Throwable $th) {
			return new DataResponse(
				[
					'message' => $this->l10n->t('Elements not found')
				],
				Http::STATUS_NOT_FOUND
			);
		}
	}

	/**
	 * Get preview of signature elements of
	 *
	 * @param int $nodeId Node id of a Nextcloud file
	 * @return FileDisplayResponse<Http::STATUS_OK, array{Content-Type: string}>|DataResponse<Http::STATUS_NOT_FOUND, array{}, array{}>
	 *
	 * 200: OK
	 * 404: Invalid data
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[NoCSRFRequired]
	#[RequireSignRequestUuid(skipIfAuthenticated: true)]
	public function getSignatureElementPreview(int $nodeId) {
		try {
			$node = $this->accountService->getFileByNodeIdAndSessionId(
				$nodeId,
				$this->sessionService->getSessionId()
			);
		} catch (DoesNotExistException $th) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
		$preview = $this->preview->getPreview(
			file: $node,
			width: SignerElementsService::ELEMENT_SIGN_WIDTH,
			height: SignerElementsService::ELEMENT_SIGN_HEIGHT,
		);
		$response = new FileDisplayResponse($preview, Http::STATUS_OK, [
			'Content-Type' => $preview->getMimeType(),
		]);
		return $response;
	}

	/**
	 * Get signature element of signer
	 *
	 * @param int $nodeId Node id of a Nextcloud file
	 * @return DataResponse<Http::STATUS_OK, LibresignUserElement, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 404: Invalid data
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getSignatureElement(int $nodeId): DataResponse {
		$userId = $this->userSession->getUser()->getUID();
		try {
			return new DataResponse(
				$this->signerElementsService->getUserElementByNodeId($userId, $nodeId),
				Http::STATUS_OK
			);
		} catch (\Throwable $th) {
			return new DataResponse(
				[
					'message' => $this->l10n->t('Element not found')
				],
				Http::STATUS_NOT_FOUND
			);
		}
	}

	/**
	 * Update signature element
	 *
	 * @param int $nodeId Node id of a Nextcloud file
	 * @param string $type The type of signature element
	 * @param array<string, mixed> $file Element object
	 * @return DataResponse<Http::STATUS_OK, array{elements: LibresignUserElement[], message: string}, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 422: Error
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[NoCSRFRequired]
	#[RequireSignRequestUuid(skipIfAuthenticated: true)]
	public function patchSignatureElement(int $nodeId, string $type = '', array $file = []): DataResponse {
		try {
			$element['nodeId'] = $nodeId;
			if ($type) {
				$element['type'] = $type;
			}
			if ($file) {
				$element['file'] = $file;
			}
			$this->validateHelper->validateVisibleElement($element, $this->validateHelper::TYPE_VISIBLE_ELEMENT_USER);
			$user = $this->userSession->getUser();
			if ($user instanceof IUser) {
				$userElement = $this->signerElementsService->getUserElementByNodeId(
					$user->getUID(),
					$nodeId,
				);
				$element['elementId'] = $userElement['id'];
			}
			$this->accountService->saveVisibleElement($element, $this->sessionService->getSessionId(), $user);
			/** @var LibresignUserElement[] $elements */
			$elements = (
				$this->userSession->getUser() instanceof IUser
				? $this->signerElementsService->getUserElements($this->userSession->getUser()->getUID())
				: $this->signerElementsService->getElementsFromSessionAsArray()
			);
			return new DataResponse(
				[
					'message' => $this->l10n->t('Element updated with success'),
					'elements' => $elements,
				],
				Http::STATUS_OK
			);
		} catch (\Throwable $th) {
			return new DataResponse(
				[
					'message' => $th->getMessage()
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
	}

	/**
	 * Delete signature element
	 *
	 * @param int $nodeId Node id of a Nextcloud file
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[RequireSignRequestUuid(skipIfAuthenticated: true)]
	public function deleteSignatureElement(int $nodeId): DataResponse {
		try {
			$this->accountService->deleteSignatureElement(
				user: $this->userSession->getUser(),
				nodeId: $nodeId,
				sessionId: $this->sessionService->getSessionId(),
			);
		} catch (\Throwable $th) {
			return new DataResponse(
				[
					'message' => $this->l10n->t('Element not found')
				],
				Http::STATUS_NOT_FOUND
			);
		}
		return new DataResponse(
			[
				'message' => $this->l10n->t('Visible element deleted')
			],
			Http::STATUS_OK
		);
	}
}
