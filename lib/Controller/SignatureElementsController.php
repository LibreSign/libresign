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
use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Service\AccountFileService;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\SessionService;
use OCA\Libresign\Service\SignatureTextService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Service\SignFileService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\Files\SimpleFS\InMemoryFile;
use OCP\IL10N;
use OCP\IPreview;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Preview\IMimeIconProvider;

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
		protected SignatureTextService $signatureTextService,
		protected IUserSession $userSession,
		protected SessionService $sessionService,
		protected SignFileService $signFileService,
		private IPreview $preview,
		protected IMimeIconProvider $mimeIconProvider,
		protected IURLGenerator $urlGenerator,
		private ValidateHelper $validateHelper,
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
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/signature/elements', requirements: ['apiVersion' => '(v1)'])]
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
		if (count($elements) === 1) {
			$message = $this->l10n->t('Element created with success');
		} else {
			$message = $this->l10n->t('Elements created with success');
		}
		return new DataResponse(
			[
				'message' => $message,
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
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/signature/elements', requirements: ['apiVersion' => '(v1)'])]
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
		} catch (\Throwable) {
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
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/signature/elements/preview/{nodeId}', requirements: ['apiVersion' => '(v1)'])]
	public function getSignatureElementPreview(int $nodeId) {
		try {
			$node = $this->accountService->getFileByNodeIdAndSessionId(
				$nodeId,
				$this->sessionService->getSessionId()
			);
			if ($this->preview->isAvailable($node)) {
				$preview = $this->preview->getPreview(
					file: $node,
					width: (int)$this->signatureTextService->getSignatureWidth(),
					height: (int)$this->signatureTextService->getSignatureHeight(),
				);
			} else {
				// When the preview is disabled, use the icon image of mimetype
				// as fallback
				$url = $this->mimeIconProvider->getMimeIconUrl($node->getMimeType());
				$baseUrl = $this->urlGenerator->getBaseUrl();
				if (!str_starts_with((string)$url, $baseUrl)) {
					throw new DoesNotExistException('Preview disabled');
				}
				$path = \OC::$SERVERROOT . str_replace($baseUrl, '', $url);
				if (!file_exists($path)) {
					throw new DoesNotExistException('Preview disabled');
				}
				$extension = pathinfo($path, PATHINFO_EXTENSION);
				$preview = new InMemoryFile(implode('.', ['signature', $extension]), file_get_contents($path));
			}
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
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
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/signature/elements/{nodeId}', requirements: ['apiVersion' => '(v1)'])]
	public function getSignatureElement(int $nodeId): DataResponse {
		$userId = $this->userSession->getUser()->getUID();
		try {
			return new DataResponse(
				$this->signerElementsService->getUserElementByNodeId($userId, $nodeId),
				Http::STATUS_OK
			);
		} catch (\Throwable) {
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
	#[ApiRoute(verb: 'PATCH', url: '/api/{apiVersion}/signature/elements/{nodeId}', requirements: ['apiVersion' => '(v1)'])]
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
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/signature/elements/{nodeId}', requirements: ['apiVersion' => '(v1)'])]
	public function deleteSignatureElement(int $nodeId): DataResponse {
		try {
			$this->accountService->deleteSignatureElement(
				user: $this->userSession->getUser(),
				nodeId: $nodeId,
				sessionId: $this->sessionService->getSessionId(),
			);
		} catch (\Throwable) {
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
