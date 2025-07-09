<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use InvalidArgumentException;
use OCA\Files_Sharing\SharedStorage;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Middleware\Attribute\PrivateValidation;
use OCA\Libresign\Middleware\Attribute\RequireManager;
use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\RequestSignatureService;
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
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IAppConfig;
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
		private RequestSignatureService $requestSignatureService,
		private AccountService $accountService,
		private IPreview $preview,
		private IAppConfig $appConfig,
		private IMimeIconProvider $mimeIconProvider,
		private FileService $fileService,
		private ValidateHelper $validateHelper,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Validate a file using Uuid
	 *
	 * Validate a file returning file data.
	 *
	 * @param string $uuid The UUID of the LibreSign file
	 * @return DataResponse<Http::STATUS_OK, LibresignValidateFile, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{action: int, errors: list<array{message: string, title?: string}>, messages?: array{type: string, message: string}[]}, array{}>
	 *
	 * 200: OK
	 * 404: Request failed
	 * 422: Request failed
	 */
	#[PrivateValidation]
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
	 * @return DataResponse<Http::STATUS_OK, LibresignValidateFile, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{action: int, errors: list<array{message: string, title?: string}>, messages?: array{type: string, message: string}[]}, array{}>
	 *
	 * 200: OK
	 * 404: Request failed
	 * 422: Request failed
	 */
	#[PrivateValidation]
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/file/validate/file_id/{fileId}', requirements: ['apiVersion' => '(v1)'])]
	public function validateFileId(int $fileId): DataResponse {
		return $this->validate('FileId', $fileId);
	}

	/**
	 * Validate a binary file
	 *
	 * Validate a binary file returning file data.
	 * Use field 'file' for the file upload
	 *
	 * @return DataResponse<Http::STATUS_OK, LibresignValidateFile, array{}>|DataResponse<Http::STATUS_NOT_FOUND|Http::STATUS_BAD_REQUEST, array{action: int, errors: list<array{message: string, title?: string}>, messages?: array{type: string, message: string}[], message?: string}, array{}>
	 *
	 * 200: OK
	 * 404: Request failed
	 * 400: Request failed
	 */
	#[PrivateValidation]
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/file/validate/', requirements: ['apiVersion' => '(v1)'])]
	public function validateBinary(): DataResponse {
		try {
			$file = $this->request->getUploadedFile('file');
			$return = $this->fileService
				->setMe($this->userSession->getUser())
				->setFileFromRequest($file)
				->setHost($this->request->getServerHost())
				->showVisibleElements()
				->showSigners()
				->showSettings()
				->showMessages()
				->showValidateFile()
				->toArray();
			$statusCode = Http::STATUS_OK;
		} catch (InvalidArgumentException $e) {
			$message = $this->l10n->t($e->getMessage());
			$return = [
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [['message' => $message]]
			];
			$statusCode = Http::STATUS_NOT_FOUND;
		} catch (\Exception $e) {
			$this->logger->error('Failed to post file to validate', [
				'exception' => $e,
			]);

			$return = ['message' => $this->l10n->t('Internal error. Contact admin.')];
			$statusCode = Http::STATUS_BAD_REQUEST;
		}
		return new DataResponse($return, $statusCode);
	}

	/**
	 * Validate a file
	 *
	 * @param string|null $type The type of identifier could be Uuid or FileId
	 * @param string|int $identifier The identifier value, could be string or integer, if UUID will be a string, if FileId will be an integer
	 * @return DataResponse<Http::STATUS_OK, LibresignValidateFile, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{action: int, errors: list<array{message: string, title?: string}>, messages?: array{type: string, message: string}[]}, array{}>
	 */
	private function validate(?string $type = null, $identifier = null): DataResponse {
		try {
			if ($type === 'Uuid' && !empty($identifier)) {
				try {
					$this->fileService
						->setFileByType('Uuid', $identifier);
				} catch (LibresignException) {
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

			$return = $this->fileService
				->setMe($this->userSession->getUser())
				->setIdentifyMethodId($this->sessionService->getIdentifyMethodId())
				->setHost($this->request->getServerHost())
				->showVisibleElements()
				->showSigners()
				->showSettings()
				->showMessages()
				->showValidateFile()
				->toArray();
			$statusCode = Http::STATUS_OK;
		} catch (LibresignException $e) {
			$message = $this->l10n->t($e->getMessage());
			$return = [
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [['message' => $message]]
			];
			$statusCode = Http::STATUS_NOT_FOUND;
		} catch (\Throwable $th) {
			$message = $this->l10n->t($th->getMessage());
			$this->logger->error($message);
			$return = [
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [['message' => $message]]
			];
			$statusCode = Http::STATUS_NOT_FOUND;
		}

		return new DataResponse($return, $statusCode);
	}

	/**
	 * List account files that need to be approved
	 *
	 * @param string|null $signer_uuid Signer UUID
	 * @param list<string>|null $nodeIds The list of nodeIds (also called fileIds). It's the ids of files at Nextcloud
	 * @param list<int>|null $status Status could be none or many of 0 = draft, 1 = able to sign, 2 = partial signed, 3 = signed, 4 = deleted.
	 * @param int|null $page the number of page to return
	 * @param int|null $length Total of elements to return
	 * @param int|null $start Start date of signature request (UNIX timestamp)
	 * @param int|null $end End date of signature request (UNIX timestamp)
	 * @param string|null $sortBy Name of the column to sort by
	 * @param string|null $sortDirection Ascending or descending order
	 * @return DataResponse<Http::STATUS_OK, array{pagination: LibresignPagination, data: ?LibresignFile[]}, array{}>
	 *
	 * 200: OK
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/file/list', requirements: ['apiVersion' => '(v1)'])]
	public function list(
		?int $page = null,
		?int $length = null,
		?string $signer_uuid = null,
		?array $nodeIds = null,
		?array $status = null,
		?int $start = null,
		?int $end = null,
		?string $sortBy = null,
		?string $sortDirection = null,
	): DataResponse {
		$filter = array_filter([
			'signer_uuid' => $signer_uuid,
			'nodeIds' => $nodeIds,
			'status' => $status,
			'start' => $start,
			'end' => $end,
		], static fn ($var) => $var !== null);
		$sort = [
			'sortBy' => $sortBy,
			'sortDirection' => $sortDirection,
		];
		$return = $this->fileService
			->setMe($this->userSession->getUser())
			->listAssociatedFilesOfSignFlow($page, $length, $filter, $sort);
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
		bool $mimeFallback = false,
	) {
		if ($nodeId === -1 || $x === 0 || $y === 0) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		try {
			$myLibreSignFile = $this->fileService
				->setMe($this->userSession->getUser())
				->getMyLibresignFile($nodeId);
			$node = $this->accountService->getPdfByUuid($myLibreSignFile->getUuid());
		} catch (DoesNotExistException) {
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
		} catch (NotFoundException) {
			// If we have no preview enabled, we can redirect to the mime icon if any
			if ($mimeFallback) {
				if ($url = $this->mimeIconProvider->getMimeIconUrl($node->getMimeType())) {
					return new RedirectResponse($url);
				}
			}

			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException) {
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
			$data = [
				'file' => [
					'fileNode' => $node,
				],
				'name' => $name,
				'userManager' => $this->userSession->getUser(),
				'status' => FileEntity::STATUS_DRAFT,
			];
			$this->requestSignatureService->save($data);

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

	/**
	 * Delete File
	 *
	 * This will delete the file and all data
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
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/file/file_id/{fileId}', requirements: ['apiVersion' => '(v1)'])]
	public function deleteAllRequestSignatureUsingFileId(int $fileId): DataResponse {
		try {
			$data = [
				'userManager' => $this->userSession->getUser(),
				'file' => [
					'fileId' => $fileId
				]
			];
			$this->validateHelper->validateExistingFile($data);
			$this->fileService->delete($fileId);
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
