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
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDisplayResponse;
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
		$this->validateParameters($type, $identifier);

		try {
			$file = $this->locateFile($type, $identifier);
		} catch (LibresignException $e) {
			return new JSONResponse([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [$this->l10n->t($e->getMessage())],
			], $e->getCode() ?? Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$return = $this->formatFile($file);

		return new JSONResponse($return, Http::STATUS_OK);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function list($page = null, $length = null): JSONResponse {
		$return = $this->fileService->listAssociatedFilesOfSignFlow($this->userSession->getUser(), $page, $length);
		return new JSONResponse($return, Http::STATUS_OK);
	}

	/**
	 * @return DataDisplayResponse|JSONResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getPage(string $uuid, int $page) {
		try {
			$page = $this->fileService->getPage($uuid, $page, $this->userSession->getUser()->getUID());
			return new DataDisplayResponse(
				$page,
				Http::STATUS_OK,
				['Content-Type' => 'image/png']
			);
		} catch (\Throwable $th) {
			$this->logger->error($th->getMessage());
			$return = [
				'errors' => [$th->getMessage()]
			];
			$statusCode = $th->getCode() > 0 ? $th->getCode() : Http::STATUS_NOT_FOUND;
			return new JSONResponse($return, $statusCode);
		}
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

	private function validateParameters(?string $type, $identifier): void {
		if ($type !== null && !in_array($type, ['Uuid', 'SignerUuid', 'FileId'])) {
			throw new InvalidArgumentException('The file type must be one of: Uuid, SignerUuid, FileId.');
		}

		if ($identifier === null) {
			throw new InvalidArgumentException('The file identifier must be specified.');
		}
	}

	private function locateFile(?string $type, $identifier): FileEntity {
		if ($type === 'Uuid') {
			$file = $this->fileService->getFileByUuid($identifier);
		} elseif ($type === 'SignerUuid') {
			$file = $this->fileService->getFileBySignerUuid($identifier);
		} elseif ($type === 'FileId') {
			$file = $this->fileService->getFileById($identifier);
		} else {
			$file = $this->fileService->getFileByPath($identifier);
		}

		if ($file === null) {
			throw new LibresignException('The file could not be found.');
		}

		return $file;
	}

	private function formatFile(FileEntity $file): array {
		return [
			'action' => JSActions::ACTION_DO_NOTHING,
			'file' => $this->fileService->formatFile($file),
		];
	}
}
