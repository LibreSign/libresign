<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Middleware\Attribute\CanSignRequestUuid;
use OCA\Libresign\Middleware\Attribute\RequireManager;
use OCA\Libresign\Middleware\Attribute\RequireSigner;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\SignFileService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class SignFileController extends AEnvironmentAwareController implements ISignatureUuid {
	use LibresignTrait;
	public function __construct(
		IRequest $request,
		protected IL10N $l10n,
		private SignRequestMapper $signRequestMapper,
		private FileMapper $fileMapper,
		protected IUserSession $userSession,
		private ValidateHelper $validateHelper,
		protected SignFileService $signFileService,
		private IdentifyMethodService $identifyMethodService,
		private FileService $fileService,
		protected LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Sign a file using file Id
	 *
	 * @param int $fileId Id of LibreSign file
	 * @param string $method Signature method
	 * @param array<string, mixed> $elements List of visible elements
	 * @param string $identifyValue Identify value
	 * @param string $token Token, commonly send by email
	 * @return DataResponse<Http::STATUS_OK, array{action: integer, message: string, file: array{uuid: string}}, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array{action: integer, errors: list<array{message: string, title?: string}>, redirect?: string}, array{}>
	 *
	 * 200: OK
	 * 404: Invalid data
	 * 422: Error
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireManager]
	#[PublicPage]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/sign/file_id/{fileId}', requirements: ['apiVersion' => '(v1)'])]
	public function signUsingFileId(int $fileId, string $method, array $elements = [], string $identifyValue = '', string $token = ''): DataResponse {
		return $this->sign($method, $elements, $identifyValue, $token, $fileId, null);
	}

	/**
	 * Sign a file using file UUID
	 *
	 * @param string $uuid UUID of LibreSign file
	 * @param string $method Signature method
	 * @param array<string, mixed> $elements List of visible elements
	 * @param string $identifyValue Identify value
	 * @param string $token Token, commonly send by email
	 * @return DataResponse<Http::STATUS_OK, array{action: integer, message: string, file: array{uuid: string}}, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array{action: integer, errors: list<array{message: string, title?: string}>, redirect?: string}, array{}>
	 *
	 * 200: OK
	 * 404: Invalid data
	 * 422: Error
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireSigner]
	#[PublicPage]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/sign/uuid/{uuid}', requirements: ['apiVersion' => '(v1)'])]
	public function signUsingUuid(string $uuid, string $method, array $elements = [], string $identifyValue = '', string $token = ''): DataResponse {
		return $this->sign($method, $elements, $identifyValue, $token, null, $uuid);
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, array{action: integer, message: string, file: array{uuid: string}}, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array{action: integer, errors: list<array{message: string, title?: string}>, redirect?: string}, array{}>
	 */
	public function sign(string $method, array $elements = [], string $identifyValue = '', string $token = '', ?int $fileId = null, ?string $signRequestUuid = null): DataResponse {
		try {
			$user = $this->userSession->getUser();
			$this->validateHelper->canSignWithIdentificationDocumentStatus(
				$user,
				$this->fileService->getIdentificationDocumentsStatus($user?->getUID())
			);
			$libreSignFile = $this->signFileService->getLibresignFile($fileId, $signRequestUuid);
			$signRequest = $this->signFileService->getSignRequestToSign($libreSignFile, $signRequestUuid, $user);
			$this->validateHelper->validateVisibleElementsRelation($elements, $signRequest, $user);
			$this->validateHelper->validateCredentials($signRequest, $method, $identifyValue, $token);
			if ($method === 'password') {
				$this->signFileService->setPassword($token);
			} else {
				$this->signFileService->setSignWithoutPassword();
			}
			$identifyMethod = $this->identifyMethodService->getIdentifiedMethod($signRequest->getId());
			$this->signFileService
				->setLibreSignFile($libreSignFile)
				->setSignRequest($signRequest)
				->setUserUniqueIdentifier(
					$identifyMethod->getEntity()->getIdentifierKey()
					. ':'
					. $identifyMethod->getEntity()->getIdentifierValue()
				)
				->setFriendlyName($signRequest->getDisplayName())
				->storeUserMetadata([
					'user-agent' => $this->request->getHeader('User-Agent'),
					'remote-address' => $this->request->getRemoteAddress(),
				])
				->setCurrentUser($user)
				->setVisibleElements($elements)
				->sign();

			return new DataResponse(
				[
					'action' => JSActions::ACTION_SIGNED,
					'message' => $this->l10n->t('File signed'),
					'file' => [
						'uuid' => $libreSignFile->getUuid()
					]
				],
				Http::STATUS_OK
			);
		} catch (LibresignException $e) {
			$message = $e->getMessage();
			if ($message === 'Password to sign not defined. Create a password to sign') {
				$action = JSActions::ACTION_CREATE_SIGNATURE_PASSWORD;
			} else {
				$action = JSActions::ACTION_DO_NOTHING;
			}
			$data = [
				'action' => $action,
				'errors' => [['message' => $e->getMessage()]],
			];
		} catch (\Throwable $th) {
			$message = $th->getMessage();
			$data = [
				'action' => JSActions::ACTION_DO_NOTHING,
			];
			switch ($message) {
				case 'Host violates local access rules.':
				case 'Certificate Password Invalid.':
				case 'Certificate Password is Empty.':
					$data['errors'] = [['message' => $this->l10n->t($message)]];
					break;
				default:
					$this->logger->error($message, ['exception' => $th]);
					$data['errors'] = [[
						'message'
							=> sprintf(
								"The server was unable to complete your request.\n"
								. "If this happens again, please send the technical details below to the server administrator.\n"
								. "## Technical details:\n"
								. "**Remote Address**: %s\n"
								. "**Request ID**: %s\n"
								. '**Message**: %s',
								$this->request->getRemoteAddress(),
								$this->request->getId(),
								$message,
							),
						'title' => $this->l10n->t('Internal Server Error'),
					]];
			}
		}
		return new DataResponse(
			$data,
			Http::STATUS_UNPROCESSABLE_ENTITY
		);
	}

	/**
	 * Renew the signature method
	 *
	 * @param string $method Signature method
	 * @return DataResponse<Http::STATUS_OK, array{message: string}, array{}>
	 *
	 * 200: OK
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[CanSignRequestUuid]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/sign/uuid/{uuid}/renew/{method}', requirements: ['apiVersion' => '(v1)'])]
	public function signRenew(string $method): DataResponse {
		$this->signFileService->renew(
			$this->getSignRequestEntity(),
			$method,
		);
		return new DataResponse(
			[
				// TRANSLATORS Message sent to signer when the sign link was expired and was possible to request to renew. The signer will see this message on the screen and nothing more.
				'message' => $this->l10n->t('Renewed with success. Access the link again.'),
			]
		);
	}

	/**
	 * Get code to sign the document using UUID
	 *
	 * @param string $uuid UUID of LibreSign file
	 * @param 'account'|'email'|null $identifyMethod Identify signer method
	 * @param string|null $signMethod Method used to sign the document, i.e. emailToken, account, clickToSign, sms, signal, telegram, whatsapp, xmpp
	 * @param string|null $identify Identify value, i.e. the signer email, account or phone number
	 * @return DataResponse<Http::STATUS_OK, array{message: string}, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 422: Error
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireSigner]
	#[PublicPage]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/sign/uuid/{uuid}/code', requirements: ['apiVersion' => '(v1)'])]
	public function getCodeUsingUuid(string $uuid, ?string $identifyMethod, ?string $signMethod, ?string $identify): DataResponse {
		try {
			$signRequest = $this->signRequestMapper->getBySignerUuidAndUserId($uuid);
		} catch (\Throwable) {
			throw new LibresignException($this->l10n->t('Invalid data to sign file'), 1);
		}
		return $this->getCode($signRequest);
	}

	/**
	 * Get code to sign the document using FileID
	 *
	 * @param int $fileId Id of LibreSign file
	 * @param 'account'|'email'|null $identifyMethod Identify signer method
	 * @param string|null $signMethod Method used to sign the document, i.e. emailToken, account, clickToSign, sms, signal, telegram, whatsapp, xmpp
	 * @param string|null $identify Identify value, i.e. the signer email, account or phone number
	 * @return DataResponse<Http::STATUS_OK, array{message: string}, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 422: Error
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireSigner]
	#[PublicPage]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/sign/file_id/{fileId}/code', requirements: ['apiVersion' => '(v1)'])]
	public function getCodeUsingFileId(int $fileId, ?string $identifyMethod, ?string $signMethod, ?string $identify): DataResponse {
		try {
			$signRequest = $this->signRequestMapper->getByFileIdAndUserId($fileId);
		} catch (\Throwable) {
			throw new LibresignException($this->l10n->t('Invalid data to sign file'), 1);
		}
		return $this->getCode($signRequest);
	}

	/**
	 * @todo validate if can request code
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNPROCESSABLE_ENTITY, array{message: string}, array{}>
	 */
	private function getCode(SignRequest $signRequest): DataResponse {
		try {
			$libreSignFile = $this->fileMapper->getById($signRequest->getFileId());
			$this->validateHelper->fileCanBeSigned($libreSignFile);
			$this->signFileService->requestCode(
				signRequest: $signRequest,
				identifyMethodName: $this->request->getParam('identifyMethod', ''),
				signMethodName: $this->request->getParam('signMethod', ''),
				identify: $this->request->getParam('identify', ''),
			);
			$message = $this->l10n->t('The code to sign file was successfully requested.');
			$statusCode = Http::STATUS_OK;
		} catch (\Throwable $th) {
			$message = $th->getMessage();
			$statusCode = Http::STATUS_UNPROCESSABLE_ENTITY;
		}
		return new DataResponse(
			[
				'message' => $message,
			],
			$statusCode,
		);
	}
}
