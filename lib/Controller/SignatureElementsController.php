<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Middleware\Attribute\RequireSignRequestUuid;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\EmailSignerElementsService;
use OCA\Libresign\Service\IdentifyMethod\AbstractIdentifyMethod;
use OCA\Libresign\Service\SessionService;
use OCA\Libresign\Service\SignatureTextService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Service\SignFileService;
use OCA\Libresign\Service\Validation\FileInputValidator;
use OCA\Libresign\Service\Validation\VisibleElementValidator;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\RequestHeader;
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
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type LibresignMessageResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignUserElement from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignUserElementsMessageResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignUserElementsResponse from \OCA\Libresign\ResponseDefinitions
 */
class SignatureElementsController extends AEnvironmentAwareOCSController implements ISignatureUuid {
	use LibresignTrait;
	public function __construct(
		IRequest $request,
		protected IL10N $l10n,
		private AccountService $accountService,
		private SignerElementsService $signerElementsService,
		protected SignatureTextService $signatureTextService,
		protected IUserSession $userSession,
		protected SessionService $sessionService,
		protected SignFileService $signFileService,
		private IPreview $preview,
		protected IMimeIconProvider $mimeIconProvider,
		protected IURLGenerator $urlGenerator,
		private VisibleElementValidator $visibleElementValidator,
		private EmailSignerElementsService $emailSignerElementsService,
		private LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Create signature element
	 *
	 * @param array<string, mixed> $elements Element object
	 * @return DataResponse<Http::STATUS_OK, LibresignUserElementsMessageResponse, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, LibresignMessageResponse, array{}>
	 *
	 * 200: OK
	 * 422: Invalid data
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[RequestHeader(name: 'libresign-sign-request-uuid', description: 'The UUID of the sign request, used to identify the request', indirect: true)]
	#[RequireSignRequestUuid(skipIfAuthenticated: true)]
	#[OpenAPI(tags: ['appearance'])]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/signature/elements', requirements: ['apiVersion' => '(v1)'])]
	public function createSignatureElement(array $elements): DataResponse {
		try {
			$this->visibleElementValidator->validateVisibleElements($elements, FileInputValidator::TYPE_VISIBLE_ELEMENT_USER);
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
			// TRANSLATORS Success message shown after creating one visible signature element (signature image, initials, or text) on a document.
			$message = $this->l10n->t('Element created with success');
		} else {
			// TRANSLATORS Success message shown after creating multiple visible signature elements on a document in one request.
			$message = $this->l10n->t('Elements created with success');
		}
		return new DataResponse(
			[
				'message' => $message,
				'elements'
					=> (
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
	 * @return DataResponse<Http::STATUS_OK, LibresignUserElementsResponse, array{}>|DataResponse<Http::STATUS_NOT_FOUND, LibresignMessageResponse, array{}>
	 *
	 * 200: OK
	 * 404: Invalid data
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[RequestHeader(name: 'libresign-sign-request-uuid', description: 'The UUID of the sign request, used to identify the request', indirect: true)]
	#[RequireSignRequestUuid(skipIfAuthenticated: true)]
	#[OpenAPI(tags: ['appearance'])]
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
					// TRANSLATORS Error shown when requested visible signature elements cannot be found for the document.
					'message' => $this->l10n->t('Elements not found')
				],
				Http::STATUS_NOT_FOUND
			);
		}
	}

	/**
	 * Use the saved signature elements of a signer identified by email
	 *
	 * The saved elements are copied into the current signing session only
	 * after the signer satisfies the confirm method configured for the Email
	 * identification factor.
	 *
	 * @param string $token Verification code, required when the confirm method is the email token
	 * @return DataResponse<Http::STATUS_OK, LibresignUserElementsResponse, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, LibresignMessageResponse, array{}>
	 *
	 * 200: OK
	 * 422: The saved elements cannot be used
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 60)]
	#[BruteForceProtection(action: 'libresign_email_signer_elements')]
	#[RequestHeader(name: 'libresign-sign-request-uuid', description: 'The UUID of the sign request, used to identify the request', indirect: true)]
	#[RequireSignRequestUuid]
	#[OpenAPI(tags: ['appearance'])]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/signature/elements/saved', requirements: ['apiVersion' => '(v1)'])]
	public function useSavedSignatureElements(string $token = ''): DataResponse {
		$signRequest = $this->getSignRequestEntity();
		if ($this->userSession->getUser() instanceof IUser || !$signRequest instanceof SignRequest) {
			return new DataResponse(
				[
					// TRANSLATORS Error shown when a signer tries to reuse a saved signature on a request that was not sent by email.
					'message' => $this->l10n->t('Saved signatures are only available to signers identified by email'),
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
		try {
			$elements = $this->emailSignerElementsService->unlock($signRequest, $token);
		} catch (LibresignException $e) {
			$message = $e->getMessage();
			$decoded = json_decode($message, true);
			if (is_array($decoded) && isset($decoded['errors'][0]['message'])) {
				$message = (string)$decoded['errors'][0]['message'];
			}
			$response = new DataResponse(
				[
					'message' => $message,
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
			if ($e->getCode() === AbstractIdentifyMethod::CODE_INVALID_TOKEN) {
				$response->throttle();
			}
			return $response;
		} catch (\Exception $e) {
			// Unexpected failures must not be reported as invalid credentials.
			$this->logger->error('Unable to load saved email signature elements', ['exception' => $e]);
			throw $e;
		}
		return new DataResponse(
			[
				'elements' => $elements,
			],
			Http::STATUS_OK
		);
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
	#[OpenAPI(tags: ['appearance'])]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/signature/elements/preview/{nodeId}', requirements: ['apiVersion' => '(v1)'])]
	public function previewSignatureElement(int $nodeId) {
		try {
			$node = $this->userSession->getUser() instanceof IUser
				? $this->accountService->getFileByNodeId($nodeId)
				: $this->signerElementsService->getElementFromSessionByNodeId($nodeId);
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
	 * @return DataResponse<Http::STATUS_OK, LibresignUserElement, array{}>|DataResponse<Http::STATUS_NOT_FOUND, LibresignMessageResponse, array{}>
	 *
	 * 200: OK
	 * 404: Invalid data
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[OpenAPI(tags: ['appearance'])]
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
					// TRANSLATORS Error shown when a visible signature element cannot be found for update or inspection.
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
	 * @return DataResponse<Http::STATUS_OK, LibresignUserElementsMessageResponse, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, LibresignMessageResponse, array{}>
	 *
	 * 200: OK
	 * 422: Error
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[NoCSRFRequired]
	#[RequireSignRequestUuid(skipIfAuthenticated: true)]
	#[OpenAPI(tags: ['appearance'])]
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
			$this->visibleElementValidator->validateVisibleElement($element, FileInputValidator::TYPE_VISIBLE_ELEMENT_USER);
			$user = $this->userSession->getUser();
			if ($user instanceof IUser) {
				$userElement = $this->signerElementsService->getUserElementByNodeId(
					$user->getUID(),
					$nodeId,
				);
				$element['elementId'] = $userElement['id'];
			}
			$this->accountService->saveVisibleElement($element, $this->sessionService->getSessionId(), $user);
			/** @var list<LibresignUserElement> $elements */
			$elements = (
				$this->userSession->getUser() instanceof IUser
				? $this->signerElementsService->getUserElements($this->userSession->getUser()->getUID())
				: $this->signerElementsService->getElementsFromSessionAsArray()
			);
			return new DataResponse(
				[
					// TRANSLATORS Success message shown after updating the position or content of a visible signature element on the document.
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
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_NOT_FOUND, LibresignMessageResponse, array{}>
	 *
	 * 200: OK
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[RequireSignRequestUuid(skipIfAuthenticated: true)]
	#[OpenAPI(tags: ['appearance'])]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/signature/elements/{nodeId}', requirements: ['apiVersion' => '(v1)'])]
	public function deleteSignatureElement(int $nodeId): DataResponse {
		try {
			if (!$this->userSession->getUser() instanceof IUser) {
				// Keep the session element available for retry when forgetting its copy fails.
				$this->emailSignerElementsService->forgetSessionElement($nodeId);
			}
			$this->accountService->deleteSignatureElement(
				user: $this->userSession->getUser(),
				nodeId: $nodeId,
				sessionId: $this->sessionService->getSessionId(),
			);
		} catch (DoesNotExistException|\OCP\Files\NotFoundException) {
			return new DataResponse(
				[
					// TRANSLATORS Error shown when deleting a visible signature element that cannot be found.
					'message' => $this->l10n->t('Element not found')
				],
				Http::STATUS_NOT_FOUND
			);
		} catch (\Exception $e) {
			$this->logger->error('Unable to delete signature element', ['exception' => $e]);
			throw $e;
		}
		return new DataResponse(
			[
				// TRANSLATORS Message shown after deleting the stored visible signature element.
				'message' => $this->l10n->t('Visible element deleted')
			],
			Http::STATUS_OK
		);
	}
}
