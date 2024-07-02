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

use OCA\Files_Sharing\SharedStorage;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Middleware\Attribute\RequireManager;
use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\SessionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\IPreview;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Preview\IMimeIconProvider;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type LibresignFile from ResponseDefinitions
 * @psalm-import-type LibresignNewFile from ResponseDefinitions
 * @psalm-import-type LibresignFolderSettings from ResponseDefinitions
 * @psalm-import-type LibresignNextcloudFile from ResponseDefinitions
 * @psalm-import-type LibresignPagination from ResponseDefinitions
 * @psalm-import-type LibresignSettings from ResponseDefinitions
 * @psalm-import-type LibresignSigner from ResponseDefinitions
 * @psalm-import-type LibresignValidateFile from ResponseDefinitions
 */
class FileController extends AEnvironmentAwareController {
	public function __construct(
		IRequest $request,
		private IL10N $l10n,
		private LoggerInterface $logger,
		private IUserSession $userSession,
		private SessionService $sessionService,
		private SignRequestMapper $signRequestMapper,
		private IdentifyMethodService $identifyMethodService,
		private AccountService $accountService,
		private IRootFolder $root,
		private IPreview $preview,
		private IMimeIconProvider $mimeIconProvider,
		private FileService $fileService,
		private ValidateHelper $validateHelper
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Validate a file using Uuid
	 *
	 * Validate a file returning file data.
	 *
	 * @param string $uuid The UUID of the LibreSign file
	 * @return DataResponse<Http::STATUS_OK, LibresignValidateFile, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{action: int, errors: string[], settings: LibresignSettings, messages?: array{type: string, message: string}[]}, array{}>
	 *
	 * 200: OK
	 * 404: Request failed
	 * 422: Request failed
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/file/validate/uuid/{uuid}', requirements: ['apiVersion' => '(v1)'])]
	public function validateUuid(string $uuid): DataResponse {
		return $this->validate('Uuid', $uuid);
	}

	/**
	 * Validate a file using FileId
	 *
	 * Validate a file returning file data.
	 *
	 * @param int $fileId The identifier value of the LibreSign file
	 * @return DataResponse<Http::STATUS_OK, LibresignValidateFile, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{action: int, errors: string[], settings: LibresignSettings, messages?: array{type: string, message: string}[]}, array{}>
	 *
	 * 200: OK
	 * 404: Request failed
	 * 422: Request failed
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/file/validate/file_id/{fileId}', requirements: ['apiVersion' => '(v1)'])]
	public function validateFileId(int $fileId): DataResponse {
		return $this->validate('FileId', $fileId);
	}

	/**
	 * Validate a file
	 *
	 * Validate a file returning file data.
	 *
	 * @param string|null $type The type of identifier could be Uuid or FileId
	 * @param string|int $identifier The identifier value, could be string or integer, if UUID will be a string, if FileId will be an integer
	 * @return DataResponse<Http::STATUS_OK, LibresignValidateFile, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{action: int, errors: string[], settings: LibresignSettings, messages?: array{type: string, message: string}[]}, array{}>
	 *
	 * 200: OK
	 * 404: Request failed
	 * 422: Request failed
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/file/validate/', requirements: ['apiVersion' => '(v1)'])]
	public function validate(?string $type = null, $identifier = null): DataResponse {
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
			$statusCode = Http::STATUS_NOT_FOUND;
		} catch (\Throwable $th) {
			$message = $this->l10n->t($th->getMessage());
			$this->logger->error($message);
			$return = [
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [$message]
			];
			$statusCode = Http::STATUS_NOT_FOUND;
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

		return new DataResponse($return, $statusCode);
	}

	/**
	 * List account files that need to be approved
	 *
	 * @param array{signer_uuid?: string, nodeId?: string}|null $filter Filter params
	 * @param int|null $page the number of page to return
	 * @param int|null $length Total of elements to return
	 * @return DataResponse<Http::STATUS_OK, array{pagination: LibresignPagination, data: ?LibresignFile[]}, array{}>
	 *
	 * 200: OK
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/file/list', requirements: ['apiVersion' => '(v1)'])]
	public function list(?int $page = null, ?int $length = null, ?array $filter = []): DataResponse {
		$return = $this->fileService
			->setMe($this->userSession->getUser())
			->listAssociatedFilesOfSignFlow($page, $length, $filter);
		return new DataResponse($return, Http::STATUS_OK);
	}

	/**
	 * Return the thumbnail of a LibreSign file
	 *
	 * @param integer $nodeId The nodeId of document
	 * @param integer $x Width of generated file
	 * @param integer $y Height of generated file
	 * @param boolean $a Crop, boolean value, default false
	 * @param boolean $forceIcon Force to generate a new thumbnail
	 * @param string $mode To force a given mimetype for the file
	 * @param boolean $mimeFallback If we have no preview enabled, we can redirect to the mime icon if any
	 * @return FileDisplayResponse<Http::STATUS_OK, array{Content-Type: string}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND, array<empty>, array{}>|RedirectResponse<Http::STATUS_SEE_OTHER, array{}>
	 *
	 * 200: OK
	 * 303: Redirect
	 * 400: Bad request
	 * 403: Forbidden
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/file/thumbnail/{nodeId}', requirements: ['apiVersion' => '(v1)'])]
	public function getThumbnail(
		int $nodeId = -1,
		int $x = 32,
		int $y = 32,
		bool $a = false,
		bool $forceIcon = true,
		string $mode = 'fill',
		bool $mimeFallback = false
	) {
		if ($nodeId === -1 || $x === 0 || $y === 0) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		try {
			$myLibreSignFile = $this->fileService
				->setMe($this->userSession->getUser())
				->getMyLibresignFile($nodeId);
			$node = $this->accountService->getPdfByUuid($myLibreSignFile->getUuid());
		} catch (DoesNotExistException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		return $this->fetchPreview($node, $x, $y, $a, $forceIcon, $mode, $mimeFallback);
	}

	/**
	 * @return FileDisplayResponse<Http::STATUS_OK, array{Content-Type: string}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND, array<empty>, array{}>|RedirectResponse<Http::STATUS_SEE_OTHER, array{}>
	 */
	private function fetchPreview(
		Node $node,
		int $x,
		int $y,
		bool $a,
		bool $forceIcon,
		string $mode,
		bool $mimeFallback = false,
	) : Http\Response {
		if (!($node instanceof File) || (!$forceIcon && !$this->preview->isAvailable($node))) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
		if (!$node->isReadable()) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		$storage = $node->getStorage();
		if ($storage->instanceOfStorage(SharedStorage::class)) {
			/** @var SharedStorage $storage */
			$share = $storage->getShare();
			$attributes = $share->getAttributes();
			if ($attributes !== null && $attributes->getAttribute('permissions', 'download') === false) {
				return new DataResponse([], Http::STATUS_FORBIDDEN);
			}
		}

		try {
			$f = $this->preview->getPreview($node, $x, $y, !$a, $mode);
			$response = new FileDisplayResponse($f, Http::STATUS_OK, [
				'Content-Type' => $f->getMimeType(),
			]);
			$response->cacheFor(3600 * 24, false, true);
			return $response;
		} catch (NotFoundException $e) {
			// If we have no preview enabled, we can redirect to the mime icon if any
			if ($mimeFallback) {
				if ($url = $this->mimeIconProvider->getMimeIconUrl($node->getMimeType())) {
					return new RedirectResponse($url);
				}
			}

			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Send a file
	 *
	 * Send a new file to Nextcloud and return the fileId to request to sign usign fileId
	 *
	 * @param LibresignNewFile $file File to save
	 * @param string $name The name of file to sign
	 * @param LibresignFolderSettings $settings Settings to define the pattern to store the file. See more informations at FolderService::getFolderName method.
	 * @return DataResponse<Http::STATUS_OK, LibresignNextcloudFile, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 422: Failed to save data
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireManager]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/file', requirements: ['apiVersion' => '(v1)'])]
	public function save(array $file, string $name = '', array $settings = []): DataResponse {
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

			return new DataResponse(
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
			return new DataResponse(
				[
					'message' => $e->getMessage(),
				],
				Http::STATUS_UNPROCESSABLE_ENTITY,
			);
		}
	}
}
