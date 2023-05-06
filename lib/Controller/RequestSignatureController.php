<?php

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

declare(strict_types=1);

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Middleware\Attribute\RequireManager;
use OCA\Libresign\Service\RequestSignatureService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

class RequestSignatureController extends AEnvironmentAwareController {
	public function __construct(
		IRequest $request,
		protected IL10N $l10n,
		protected IUserSession $userSession,
		protected ValidateHelper $validateHelper,
		protected RequestSignatureService $requestSignatureService
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Request signature
	 *
	 * Request that a file be signed by a group of people
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param array $file
	 * @param array $users
	 * @param string $name
	 * @param string|null $callback
	 * @return JSONResponse
	 */
	#[RequireManager]
	public function request(array $file, array $users, string $name, ?string $callback = null, ?int $status = 1) {
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
			$return = $this->requestSignatureService->save($data);
			unset(
				$return['id'],
				$return['users'],
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
				'message' => $this->l10n->t('Success'),
				'data' => $return
			],
			Http::STATUS_OK
		);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $uuid
	 * @param array $users
	 * @return JSONResponse
	 */
	#[RequireManager]
	public function updateSign(?array $users = [], ?string $uuid = null, ?array $visibleElements = null, ?array $file = [], ?int $status = null) {
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
			$return = $this->requestSignatureService->save($data);
			unset(
				$return['id'],
				$return['users'],
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
				'message' => $this->l10n->t('Success'),
				'data' => $return
			],
			Http::STATUS_OK
		);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param integer $fileId
	 * @param integer $fileUserId
	 * @return JSONResponse
	 */
	#[RequireManager]
	public function deleteOneRequestSignatureUsingFileId(int $fileId, int $fileUserId) {
		try {
			$data = [
				'userManager' => $this->userSession->getUser(),
				'file' => [
					'fileId' => $fileId
				]
			];
			$this->validateHelper->validateExistingFile($data);
			$this->validateHelper->validateIsSignerOfFile($fileUserId, $fileId);
			$this->requestSignatureService->unassociateToUser($fileId, $fileUserId);
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
				'success' => true,
				'message' => $this->l10n->t('Success')
			],
			Http::STATUS_OK
		);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param integer $fileId
	 * @return JSONResponse
	 */
	#[RequireManager]
	public function deleteAllRequestSignatureUsingFileId(int $fileId) {
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
				'success' => true,
				'message' => $this->l10n->t('Success')
			],
			Http::STATUS_OK
		);
	}
}
