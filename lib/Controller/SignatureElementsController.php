<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Middleware\Attribute\RequireSignRequestUuid;
use OCA\Libresign\Service\AccountFileService;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\SessionService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Service\SignFileService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IPreview;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;

class SignatureElementsController extends ApiController implements ISignatureUuid {
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

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[RequireSignRequestUuid(skipIfAuthenticated: true)]
	public function createSignatureElement(array $elements): JSONResponse {
		try {
			$this->validateHelper->validateVisibleElements($elements, $this->validateHelper::TYPE_VISIBLE_ELEMENT_USER);
			$this->accountService->saveVisibleElements(
				elements: $elements,
				sessionId: $this->sessionService->getSessionId(),
				user: $this->userSession->getUser(),
			);
		} catch (\Throwable $th) {
			return new JSONResponse(
				[
					'message' => $th->getMessage(),
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
		return new JSONResponse(
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

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[RequireSignRequestUuid(skipIfAuthenticated: true)]
	public function getSignatureElements(): JSONResponse {
		$userId = $this->userSession->getUser()?->getUID();
		try {
			return new JSONResponse(
				[
					'elements' =>
						(
							$userId
							? $this->signerElementsService->getUserElements($userId)
							: $this->signerElementsService->getElementsFromSessionAsArray()
						)
				],
				Http::STATUS_OK
			);
		} catch (\Throwable $th) {
			return new JSONResponse(
				[
					'message' => $this->l10n->t('Elements not found')
				],
				Http::STATUS_NOT_FOUND
			);
		}
	}

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
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
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

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getSignatureElement(int $nodeId): JSONResponse {
		$userId = $this->userSession->getUser()->getUID();
		try {
			return new JSONResponse(
				$this->signerElementsService->getUserElementByNodeId($userId, $nodeId),
				Http::STATUS_OK
			);
		} catch (\Throwable $th) {
			return new JSONResponse(
				[
					'message' => $this->l10n->t('Element not found')
				],
				Http::STATUS_NOT_FOUND
			);
		}
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[NoCSRFRequired]
	#[RequireSignRequestUuid(skipIfAuthenticated: true)]
	public function patchSignatureElement(int $nodeId, string $type = '', array $file = []): JSONResponse {
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
			return new JSONResponse(
				[
					'message' => $this->l10n->t('Element updated with success'),
					'elements' =>
						(
							$this->userSession->getUser() instanceof IUser
							? $this->signerElementsService->getUserElements($this->userSession->getUser()->getUID())
							: $this->signerElementsService->getElementsFromSessionAsArray()
						),
				],
				Http::STATUS_OK
			);
		} catch (\Throwable $th) {
			return new JSONResponse(
				[
					'message' => $th->getMessage()
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[RequireSignRequestUuid(skipIfAuthenticated: true)]
	public function deleteSignatureElement(int $nodeId): JSONResponse {
		try {
			$this->accountService->deleteSignatureElement(
				user: $this->userSession->getUser(),
				nodeId: $nodeId,
				sessionId: $this->sessionService->getSessionId(),
			);
		} catch (\Throwable $th) {
			return new JSONResponse(
				[
					'message' => $this->l10n->t('Element not found')
				],
				Http::STATUS_NOT_FOUND
			);
		}
		return new JSONResponse(
			[
				'message' => $this->l10n->t('Visible element deleted')
			],
			Http::STATUS_OK
		);
	}
}
