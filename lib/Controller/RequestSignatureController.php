<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Middleware\Attribute\RequireManager;
use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Service\File\FileListService;
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
 * @psalm-import-type LibresignFileDetail from ResponseDefinitions
 * @psalm-import-type LibresignNextcloudFile from ResponseDefinitions
 * @psalm-import-type LibresignFolderSettings from ResponseDefinitions
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
		protected FileListService $fileListService,
		protected ValidateHelper $validateHelper,
		protected RequestSignatureService $requestSignatureService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Request signature
	 *
	 * Request that a file be signed by a group of people.
	 * Each user in the users array can optionally include a 'signing_order' field
	 * to control the order of signatures when ordered signing flow is enabled.
	 * When the created entity is an envelope (`nodeType` = `envelope`),
	 * the returned `data` includes `filesCount` and `files` as a list of
	 * envelope child files.
	 *
	 * @param LibresignNewFile $file File object.
	 * @param LibresignNewSigner[] $users Collection of users who must sign the document. Each user can have: identify, displayName, description, notify, signing_order
	 * @param string $name The name of file to sign
	 * @param LibresignFolderSettings $settings Settings to define how and where the file should be stored
	 * @param list<LibresignNewFile> $files Multiple files to create an envelope (optional, use either file or files)
	 * @param string|null $callback URL that will receive a POST after the document is signed
	 * @param integer|null $status Numeric code of status * 0 - no signers * 1 - signed * 2 - pending
	 * @param string|null $signatureFlow Signature flow mode: 'parallel' or 'ordered_numeric'. If not provided, uses global configuration
	 * @return DataResponse<Http::STATUS_OK, LibresignNextcloudFile, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array{message?: string, action?: integer, errors?: list<array{message: string, title?: string}>}, array{}>
	 *
	 * 200: OK
	 * 422: Unauthorized
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireManager]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/request-signature', requirements: ['apiVersion' => '(v1)'])]
	public function request(
		array $file,
		array $users,
		string $name,
		array $settings = [],
		array $files = [],
		?string $callback = null,
		?int $status = 1,
		?string $signatureFlow = null,
	): DataResponse {
		try {
			$user = $this->userSession->getUser();
			return $this->createSignatureRequest(
				$user,
				$file,
				$files,
				$name,
				$settings,
				$users,
				$status,
				$callback,
				$signatureFlow
			);
		} catch (LibresignException $e) {
			$errorMessage = $e->getMessage();
			$decoded = json_decode($errorMessage, true);
			if (json_last_error() === JSON_ERROR_NONE && isset($decoded['errors'])) {
				$errorMessage = $decoded['errors'][0]['message'] ?? $errorMessage;
			}
			return new DataResponse(
				[
					'message' => $errorMessage,
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		} catch (\Throwable $th) {
			$errorMessage = $th->getMessage();
			return new DataResponse(
				[
					'message' => $errorMessage,
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
	 * @param string|null $signatureFlow Signature flow mode: 'parallel' or 'ordered_numeric'. If not provided, uses global configuration
	 * @param string|null $name The name of file to sign
	 * @param LibresignFolderSettings $settings Settings to define how and where the file should be stored
	 * @param list<LibresignNewFile> $files Multiple files to create an envelope (optional, use either file or files)
	 * @return DataResponse<Http::STATUS_OK, LibresignNextcloudFile, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array{message?: string, action?: integer, errors?: list<array{message: string, title?: string}>}, array{}>
	 *
	 * 200: OK
	 * 422: Unauthorized
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireManager]
	#[ApiRoute(verb: 'PATCH', url: '/api/{apiVersion}/request-signature', requirements: ['apiVersion' => '(v1)'])]
	public function updateSign(
		?array $users = [],
		?string $uuid = null,
		?array $visibleElements = null,
		?array $file = [],
		?int $status = null,
		?string $signatureFlow = null,
		?string $name = null,
		array $settings = [],
		array $files = [],
	): DataResponse {
		try {
			$user = $this->userSession->getUser();

			if (empty($uuid)) {
				return $this->createSignatureRequest(
					$user,
					$file,
					$files,
					$name,
					$settings,
					$users,
					$status,
					null,
					$signatureFlow,
					$visibleElements
				);
			}

			$data = [
				'uuid' => $uuid,
				'file' => $file,
				'users' => $users,
				'userManager' => $user,
				'status' => $status,
				'visibleElements' => $visibleElements,
				'signatureFlow' => $signatureFlow,
				'name' => $name,
				'settings' => $settings,
			];
			$this->validateHelper->validateExistingFile($data);
			$this->validateHelper->validateFileStatus($data);
			$this->validateHelper->validateIdentifySigners($data);
			if (!empty($visibleElements)) {
				$this->validateHelper->validateVisibleElements($visibleElements, $this->validateHelper::TYPE_VISIBLE_ELEMENT_PDF);
			}
			$fileEntity = $this->requestSignatureService->save($data);
			$childFiles = [];

			$response = $this->fileListService->formatFileWithChildren($fileEntity, $childFiles, $user);
			return new DataResponse($response, Http::STATUS_OK);
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
	 * Internal method to handle signature request creation logic
	 * Used by both request() and updateSign() when creating new requests
	 *
	 * @return DataResponse<Http::STATUS_OK, LibresignNextcloudFile, array{}>
	 * @throws LibresignException
	 */
	private function createSignatureRequest(
		$user,
		array $file,
		array $files,
		string $name,
		array $settings,
		array $users,
		?int $status,
		?string $callback,
		?string $signatureFlow,
		?array $visibleElements = null,
	): DataResponse {
		$filesToSave = !empty($files) ? $files : ($file['files'] ?? null);

		if (!$filesToSave && !empty($file)) {
			$filesToSave = [$file];
		}

		if (empty($filesToSave)) {
			throw new LibresignException($this->l10n->t('File or files parameter is required'));
		}

		$data = [
			'file' => $file,
			'name' => $name,
			'users' => $users,
			'status' => $status,
			'callback' => $callback,
			'userManager' => $user,
			'signatureFlow' => $signatureFlow,
			'settings' => !empty($settings) ? $settings : ($file['settings'] ?? []),
		];
		if ($visibleElements !== null) {
			$data['visibleElements'] = $visibleElements;
		}
		$this->requestSignatureService->validateNewRequestToFile($data);

		$saveData = [
			'files' => $filesToSave,
			'name' => $name,
			'userManager' => $user,
			'settings' => !empty($settings) ? $settings : ($file['settings'] ?? []),
			'users' => $users,
			'status' => $status,
			'signatureFlow' => $signatureFlow,
		];
		if ($callback !== null) {
			$saveData['callback'] = $callback;
		}
		if ($visibleElements !== null) {
			$saveData['visibleElements'] = $visibleElements;
		}

		$result = $this->requestSignatureService->saveFiles($saveData);
		$fileEntity = $result['file'];
		$childFiles = $result['children'] ?? [];

		$response = $this->fileListService->formatFileWithChildren($fileEntity, $childFiles, $user);
		return new DataResponse($response, Http::STATUS_OK);
	}

	/**
	 * Delete sign request
	 *
	 * You can only request exclusion as any sign
	 *
	 * @param integer $fileId LibreSign file ID
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
