<?php

declare(strict_types=1);
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

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Middleware\Attribute\RequireManager;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\SessionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class FileController extends Controller {
	public function __construct(
		IRequest $request,
		private IL10N $l10n,
		private LoggerInterface $logger,
		private IUserSession $userSession,
		private SessionService $sessionService,
		private FileService $fileService,
		private ValidateHelper $validateHelper
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function validateUuid($uuid): JSONResponse {
		return $this->validate('Uuid', $uuid);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function validateFileId($fileId): JSONResponse {
		return $this->validate('FileId', $fileId);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function validate(?string $type = null, $identifier = null): JSONResponse {
		try {
			if ($type === 'Uuid' && !empty($identifier)) {
				try {
					$this->fileService
						->setFileByType('Uuid', $identifier);
				} catch (LibresignException $e) {
					$this->fileService
						->setFileByType('SignerUuid', $identifier);
				}
			} elseif (!empty($type) && !empty($identifier)) {
				$this->fileService
					->setFileByType($type, $identifier);
			} elseif ($this->request->getParam('path')) {
				$this->fileService
					->setMe($this->userSession->getUser())
					->setFileByPath($this->request->getParam('path'));
			} elseif ($this->request->getParam('fileId')) {
				$this->fileService->setFileByType(
					'FileId',
					$this->request->getParam('fileId')
				);
			} elseif ($this->request->getParam('uuid')) {
				$this->fileService->setFileByType(
					'Uuid',
					$this->request->getParam('uuid')
				);
			}
			$return = [];
			$statusCode = Http::STATUS_OK;
		} catch (LibresignException $e) {
			$message = $this->l10n->t($e->getMessage());
			$return = [
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [$message]
			];
			$statusCode = $e->getCode() ?? Http::STATUS_UNPROCESSABLE_ENTITY;
		} catch (\Throwable $th) {
			$message = $this->l10n->t($th->getMessage());
			$this->logger->error($message);
			$return = [
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [$message]
			];
			$statusCode = $th->getCode() ?? Http::STATUS_UNPROCESSABLE_ENTITY;
		}

		$return = array_merge($return,
			$this->fileService
				->setMe($this->userSession->getUser())
				->setIdentifyMethodId($this->sessionService->getIdentifyMethodId())
				->showVisibleElements()
				->showSigners()
				->showSettings()
				->showMessages()
				->formatFile()
		);

		return new JSONResponse($return, $statusCode);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function list($page = null, $length = null): JSONResponse {
		$return = $this->fileService->listAssociatedFilesOfSignFlow($this->userSession->getUser(), $page, $length);
		return new JSONResponse($return, Http::STATUS_OK);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireManager]
	public function save(array $file, string $name = '', array $settings = []): JSONResponse {
		try {
			if (empty($name)) {
				if (!empty($file['url'])) {
					$name = rawurldecode(pathinfo($file['url'], PATHINFO_FILENAME));
				}
			}
			if (empty($name)) {
				// The name of file to sign is mandatory. This phrase is used when we do a request to API sending a file to sign.
				throw new \Exception($this->l10n->t('Name is mandatory'));
			}
			$this->validateHelper->validateNewFile([
				'file' => $file,
				'userManager' => $this->userSession->getUser(),
			]);
			$this->validateHelper->canRequestSign($this->userSession->getUser());

			$node = $this->fileService->getNodeFromData([
				'userManager' => $this->userSession->getUser(),
				'name' => $name,
				'file' => $file,
				'settings' => $settings
			]);

			return new JSONResponse(
				[
					'message' => $this->l10n->t('Success'),
					'name' => $name,
					'id' => $node->getId(),
					'etag' => $node->getEtag(),
					'path' => $node->getPath(),
					'type' => $node->getType(),
				],
				Http::STATUS_OK
			);
		} catch (\Exception $e) {
			return new JSONResponse(
				[
					'message' => $e->getMessage(),
				],
				Http::STATUS_UNPROCESSABLE_ENTITY,
			);
		}
	}
}
