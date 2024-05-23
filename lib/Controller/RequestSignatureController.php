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
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\RequestSignatureService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

class RequestSignatureController extends AEnvironmentAwareController {
	public function __construct(
		IRequest $request,
		protected IL10N $l10n,
		protected IUserSession $userSession,
		protected FileService $fileService,
		protected ValidateHelper $validateHelper,
		protected RequestSignatureService $requestSignatureService
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Request signature
	 *
	 * Request that a file be signed by a group of people
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireManager]
	public function request(array $file, array $users, string $name, ?string $callback = null, ?int $status = 1): JSONResponse {
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
				->setMe($data['userManager'])
				->showVisibleElements()
				->showSigners()
				->showSettings()
				->showMessages()
				->formatFile();
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
				'message' => $this->l10n->t('Success'),
				'data' => $return
			],
			Http::STATUS_OK
		);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireManager]
	public function updateSign(?array $users = [], ?string $uuid = null, ?array $visibleElements = null, ?array $file = [], ?int $status = null): JSONResponse {
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
				->setMe($data['userManager'])
				->showVisibleElements()
				->showSigners()
				->showSettings()
				->showMessages()
				->formatFile();
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
				'message' => $this->l10n->t('Success'),
				'data' => $return
			],
			Http::STATUS_OK
		);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireManager]
	public function deleteOneRequestSignatureUsingFileId(int $fileId, int $signRequestId): JSONResponse {
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
			return new JSONResponse(
				[
					'message' => $th->getMessage(),
				],
				Http::STATUS_UNAUTHORIZED
			);
		}
		return new JSONResponse(
			[
				'message' => $this->l10n->t('Success')
			],
			Http::STATUS_OK
		);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireManager]
	public function deleteAllRequestSignatureUsingFileId(int $fileId): JSONResponse {
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
			return new JSONResponse(
				[
					'message' => $th->getMessage(),
				],
				Http::STATUS_UNAUTHORIZED
			);
		}
		return new JSONResponse(
			[
				'message' => $this->l10n->t('Success')
			],
			Http::STATUS_OK
		);
	}
}
