<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Middleware\Attribute\RequireManager;
use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\RequestSignatureService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-import-type LibresignNewFile from ResponseDefinitions
 * @psalm-import-type LibresignNewSigner from ResponseDefinitions
 * @psalm-import-type LibresignValidateFile from ResponseDefinitions
 * @psalm-import-type LibresignSettings from ResponseDefinitions
 * @psalm-import-type LibresignSigner from ResponseDefinitions
 * @psalm-import-type LibresignVisibleElement from ResponseDefinitions
 */
class RequestSignatureController extends AEnvironmentAwareController {
	public function __construct(
		IRequest $request,
		protected IL10N $l10n,
		protected IUserSession $userSession,
		protected FileService $fileService,
		protected ValidateHelper $validateHelper,
		protected RequestSignatureService $requestSignatureService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Request signature
	 *
	 * Request that a file be signed by a group of people
	 *
	 * @param LibresignNewFile $file File object.
	 * @param LibresignNewSigner[] $users Collection of users who must sign the document
	 * @param string $name The name of file to sign
	 * @param string|null $callback URL that will receive a POST after the document is signed
	 * @param integer|null $status Numeric code of status * 0 - no signers * 1 - signed * 2 - pending
	 * @return DataResponse<Http::STATUS_OK, array{data: LibresignValidateFile, message: string}, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array{message?: string, action?: integer, errors?: list<array{message: string, title?: string}>}, array{}>
	 *
	 * 200: OK
	 * 422: Unauthorized
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireManager]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/request-signature', requirements: ['apiVersion' => '(v1)'])]
	public function request(array $file, array $users, string $name, ?string $callback = null, ?int $status = 1): DataResponse {
		$user = $this->userSession->getUser();
		$data = [
			'file' => $file,
			'name' => $name,
			'users' => $users,
			'status' => $status,
			'callback' => $callback,
			'userManager' => $user
		];
		try {
			$this->requestSignatureService->validateNewRequestToFile($data);
			$file = $this->requestSignatureService->save($data);
			$return = $this->fileService
				->setFile($file)
				->setHost($this->request->getServerHost())
				->setMe($data['userManager'])
				->showVisibleElements()
				->showSigners()
				->showSettings()
				->showMessages()
				->toArray();
			return new DataResponse(
				[
					'message' => $this->l10n->t('Success'),
					'data' => $return
				],
				Http::STATUS_OK
			);
		} catch (\Throwable $th) {
			return new DataResponse(
				[
					'message' => $th->getMessage(),
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
	}

	/**
	 * Updates signatures data
	 *
	 * Is necessary to inform the UUID of the file and a list of people
	 *
	 * @param LibresignNewSigner[]|null $users Collection of users who must sign the document
	 * @param string|null $uuid UUID of sign request. The signer UUID is what the person receives via email when asked to sign. This is not the file UUID.
	 * @param LibresignVisibleElement[]|null $visibleElements Visible elements on document
	 * @param LibresignNewFile|array<empty>|null $file File object.
	 * @param integer|null $status Numeric code of status * 0 - no signers * 1 - signed * 2 - pending
	 * @return DataResponse<Http::STATUS_OK, array{message: string, data: LibresignValidateFile}, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array{message?: string, action?: integer, errors?: list<array{message: string, title?: string}>}, array{}>
	 *
	 * 200: OK
	 * 422: Unauthorized
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireManager]
	#[ApiRoute(verb: 'PATCH', url: '/api/{apiVersion}/request-signature', requirements: ['apiVersion' => '(v1)'])]
	public function updateSign(?array $users = [], ?string $uuid = null, ?array $visibleElements = null, ?array $file = [], ?int $status = null): DataResponse {
		$user = $this->userSession->getUser();
		$data = [
			'uuid' => $uuid,
			'file' => $file,
			'users' => $users,
			'userManager' => $user,
			'status' => $status,
			'visibleElements' => $visibleElements
		];
		try {
			$this->validateHelper->validateExistingFile($data);
			$this->validateHelper->validateFileStatus($data);
			if (!empty($data['visibleElements'])) {
				$this->validateHelper->validateVisibleElements($data['visibleElements'], $this->validateHelper::TYPE_VISIBLE_ELEMENT_PDF);
			}
			$file = $this->requestSignatureService->save($data);
			$return = $this->fileService
				->setFile($file)
				->setHost($this->request->getServerHost())
				->setMe($data['userManager'])
				->showVisibleElements()
				->showSigners()
				->showSettings()
				->showMessages()
				->toArray();
			return new DataResponse(
				[
					'message' => $this->l10n->t('Success'),
					'data' => $return
				],
				Http::STATUS_OK
			);
		} catch (\Throwable $th) {
			return new DataResponse(
				[
					'message' => $th->getMessage(),
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
	}

	/**
	 * Delete sign request
	 *
	 * You can only request exclusion as any sign
	 *
	 * @param integer $fileId Node id of a Nextcloud file
	 * @param integer $signRequestId The sign request id
	 * @return DataResponse<Http::STATUS_OK, array{message: string}, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, array{message: string}, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array{action: integer, errors: list<array{message: string, title?: string}>}, array{}>
	 *
	 * 200: OK
	 * 401: Failed
	 * 422: Failed
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireManager]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/sign/file_id/{fileId}/{signRequestId}', requirements: ['apiVersion' => '(v1)'])]
	public function deleteOneRequestSignatureUsingFileId(int $fileId, int $signRequestId): DataResponse {
		try {
			$data = [
				'userManager' => $this->userSession->getUser(),
				'file' => [
					'fileId' => $fileId
				]
			];
			$this->validateHelper->validateExistingFile($data);
			$this->validateHelper->validateIsSignerOfFile($signRequestId, $fileId);
			$this->requestSignatureService->unassociateToUser($fileId, $signRequestId);
		} catch (\Throwable $th) {
			return new DataResponse(
				[
					'message' => $th->getMessage(),
				],
				Http::STATUS_UNAUTHORIZED
			);
		}
		return new DataResponse(
			[
				'message' => $this->l10n->t('Success')
			],
			Http::STATUS_OK
		);
	}

	/**
	 * Delete sign request
	 *
	 * You can only request exclusion as any sign
	 *
	 * @param integer $fileId Node id of a Nextcloud file
	 * @return DataResponse<Http::STATUS_OK, array{message: string}, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, array{message: string}, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array{action: integer, errors: list<array{message: string, title?: string}>}, array{}>
	 *
	 * 200: OK
	 * 401: Failed
	 * 422: Failed
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireManager]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/sign/file_id/{fileId}', requirements: ['apiVersion' => '(v1)'])]
	public function deleteAllRequestSignatureUsingFileId(int $fileId): DataResponse {
		try {
			$data = [
				'userManager' => $this->userSession->getUser(),
				'file' => [
					'fileId' => $fileId
				]
			];
			$this->validateHelper->validateExistingFile($data);
			$this->requestSignatureService->deleteRequestSignature($data);
		} catch (\Throwable $th) {
			return new DataResponse(
				[
					'message' => $th->getMessage(),
				],
				Http::STATUS_UNAUTHORIZED
			);
		}
		return new DataResponse(
			[
				'message' => $this->l10n->t('Success')
			],
			Http::STATUS_OK
		);
	}
}
