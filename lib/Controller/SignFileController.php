<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\SigningErrorHandler;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Middleware\Attribute\CanSignRequestUuid;
use OCA\Libresign\Middleware\Attribute\RequireManager;
use OCA\Libresign\Middleware\Attribute\RequireSigner;
use OCA\Libresign\Service\AsyncSigningService;
use OCA\Libresign\Service\File\SettingsLoader;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\RequestMetadataService;
use OCA\Libresign\Service\SignFileService;
use OCA\Libresign\Service\Worker\WorkerHealthService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;

class SignFileController extends AEnvironmentAwareController implements ISignatureUuid {
	use LibresignTrait;
	public function __construct(
		IRequest $request,
		protected IL10N $l10n,
		private SignRequestMapper $signRequestMapper,
		protected IUserSession $userSession,
		private ValidateHelper $validateHelper,
		protected SignFileService $signFileService,
		private IdentifyMethodService $identifyMethodService,
		private FileService $fileService,
		private SettingsLoader $settingsLoader,
		private WorkerHealthService $workerHealthService,
		private AsyncSigningService $asyncSigningService,
		private RequestMetadataService $requestMetadataService,
		private SigningErrorHandler $errorHandler,
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
	 * @param bool $async Execute signing asynchronously when possible
	 * @return DataResponse<Http::STATUS_OK, array{action: integer, message?: string, file?: array{uuid: string}, job?: array{status: 'SIGNING_IN_PROGRESS', file: array{uuid: string}}}, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array{action: integer, errors: list<array{message: string, code?: int, title?: string}>, redirect?: string}, array{}>
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
	public function signUsingFileId(int $fileId, string $method, array $elements = [], string $identifyValue = '', string $token = '', bool $async = false): DataResponse {
		return $this->sign($method, $elements, $identifyValue, $token, $fileId, null, $async);
	}

	/**
	 * Sign a file using file UUID
	 *
	 * @param string $uuid UUID of LibreSign file
	 * @param string $method Signature method
	 * @param array<string, mixed> $elements List of visible elements
	 * @param string $identifyValue Identify value
	 * @param string $token Token, commonly send by email
	 * @param bool $async Execute signing asynchronously when possible
	 * @return DataResponse<Http::STATUS_OK, array{action: integer, message?: string, file?: array{uuid: string}, job?: array{status: 'SIGNING_IN_PROGRESS', file: array{uuid: string}}}, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array{action: integer, errors: list<array{message: string, code?: int, title?: string}>, redirect?: string}, array{}>
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
	public function signUsingUuid(string $uuid, string $method, array $elements = [], string $identifyValue = '', string $token = '', bool $async = false): DataResponse {
		return $this->sign($method, $elements, $identifyValue, $token, null, $uuid, $async);
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, array{action: integer, message?: string, file?: array{uuid: string}, job?: array{status: 'SIGNING_IN_PROGRESS', file: array{uuid: string}}}, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array{action: integer, errors: list<array{message: string, code?: int, title?: string}>}, array{}>
	 */
	public function sign(
		string $method,
		array $elements = [],
		string $identifyValue = '',
		string $token = '',
		?int $fileId = null,
		?string $signRequestUuid = null,
		bool $async = false,
	): DataResponse {
		try {
			$user = $this->userSession->getUser();
			$isIdDocApproval = $this->request->getParam('idDocApproval') === 'true';

			if ($isIdDocApproval && $signRequestUuid) {
				$libreSignFile = $this->signFileService->getFileByUuid($signRequestUuid);
				$signRequest = $this->signFileService->getSignRequestToSign($libreSignFile, null, $user);
			} else {
				$libreSignFile = $this->signFileService->getLibresignFile($fileId, $signRequestUuid);
				$signRequest = $this->signFileService->getSignRequestToSign($libreSignFile, $signRequestUuid, $user);
			}

			$this->validateHelper->canSignWithIdentificationDocumentStatus(
				$user,
				$this->settingsLoader->getIdentificationDocumentsStatus($user, $signRequest)
			);

			$this->validateHelper->validateVisibleElementsRelation($elements, $signRequest, $user);
			$this->validateHelper->validateCredentials($signRequest, $method, $identifyValue, $token);

			$userIdentifier = $this->identifyMethodService->getUserIdentifier($signRequest->getId());
			$metadata = $this->requestMetadataService->collectMetadata();

			$this->signFileService->prepareForSigning(
				$libreSignFile,
				$signRequest,
				$user,
				$userIdentifier,
				$signRequest->getDisplayName(),
				$method !== 'password',
				$method === 'password' ? $token : null,
				$method,
			);

			if ($async && $this->workerHealthService->isAsyncLocalEnabled()) {
				return $this->signAsync($libreSignFile, $signRequest, $user, $userIdentifier, $method, $token, $elements, $metadata);
			}

			return $this->signSync($libreSignFile, $elements, $metadata);
		} catch (\Throwable $e) {
			$data = $this->errorHandler->handleException($e);
			return new DataResponse($data, Http::STATUS_UNPROCESSABLE_ENTITY);
		}
	}

	/**
	 * Execute asynchronous signing using background job
	 *
	 * @return DataResponse<Http::STATUS_OK, array{action: integer, job: array{status: 'SIGNING_IN_PROGRESS', file: array{uuid: string}}}, array{}>
	 */
	private function signAsync(
		File $libreSignFile,
		SignRequest $signRequest,
		?IUser $user,
		string $userIdentifier,
		string $method,
		?string $token,
		array $elements,
		array $metadata,
	): DataResponse {
		$this->signFileService->validateSigningRequirements();

		$this->asyncSigningService->enqueueSigningJob(
			$libreSignFile,
			$signRequest,
			$user,
			$userIdentifier,
			$method !== 'password',
			$method === 'password' ? $token : null,
			$method,
			$elements,
			$metadata,
		);

		return new DataResponse(
			[
				'action' => JSActions::ACTION_DO_NOTHING,
				'job' => [
					'status' => 'SIGNING_IN_PROGRESS',
					'file' => [
						'uuid' => $libreSignFile->getUuid(),
					],
				],
			],
			Http::STATUS_OK
		);
	}

	/**
	 * Execute synchronous signing immediately
	 *
	 * @return DataResponse<Http::STATUS_OK, array{action: integer, message: string, file: array{uuid: string}}, array{}>
	 */
	private function signSync($libreSignFile, array $elements, array $metadata): DataResponse {
		$this->signFileService
			->setVisibleElements($elements)
			->storeUserMetadata($metadata)
			->sign();

		$validationUuid = $libreSignFile->getUuid();
		if ($libreSignFile->hasParent()) {
			$parentFile = $this->signFileService->getFile($libreSignFile->getParentFileId());
			$validationUuid = $parentFile->getUuid();
		}

		return new DataResponse(
			[
				'action' => JSActions::ACTION_SIGNED,
				'message' => $this->l10n->t('File signed'),
				'file' => [
					'uuid' => $validationUuid
				]
			],
			Http::STATUS_OK
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
	 * @param string|null $signMethod Method used to sign the document, i.e. emailToken, account, clickToSign, smsToken, signalToken, telegramToken, whatsappToken, xmppToken
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
	 * @param string|null $signMethod Method used to sign the document, i.e. emailToken, account, clickToSign, smsToken, signalToken, telegramToken, whatsappToken, xmppToken
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
			$libreSignFile = $this->signFileService->getFile($signRequest->getFileId());
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
