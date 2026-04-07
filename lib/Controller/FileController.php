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
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Middleware\Attribute\PrivateValidation;
use OCA\Libresign\Middleware\Attribute\RequireManager;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\File\FileListService;
use OCA\Libresign\Service\File\SettingsLoader;
use OCA\Libresign\Service\FileService;
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
use OCP\IL10N;
use OCP\IPreview;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Preview\IMimeIconProvider;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type LibresignFile from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignDetailedFile from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignDetailedFileResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignActionErrorResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignFileListResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignMessageResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignFileSummary from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignFolderSettings from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignNewFile from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignPagination from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignSettings from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignValidatedFile from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignValidateMetadata from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignVisibleElement from \OCA\Libresign\ResponseDefinitions
 */
class FileController extends AEnvironmentAwareController {
	public function __construct(
		IRequest $request,
		private IL10N $l10n,
		private LoggerInterface $logger,
		private IUserSession $userSession,
		private SessionService $sessionService,
		private SignRequestMapper $signRequestMapper,
		private FileMapper $fileMapper,
		private RequestSignatureService $requestSignatureService,
		private AccountService $accountService,
		private IPreview $preview,
		private IMimeIconProvider $mimeIconProvider,
		private FileService $fileService,
		private FileListService $fileListService,
		private ValidateHelper $validateHelper,
		private SettingsLoader $settingsLoader,
		private IURLGenerator $urlGenerator,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Validate a file using Uuid
	 *
	 * Validate a file returning file data.
	 * The response always includes `filesCount` and `files`.
	 * For `nodeType=file`, `filesCount=1` and `files` contains the current file.
	 * For `nodeType=envelope`, `files` contains envelope child files.
	 *
	 * @param string $uuid The UUID of the LibreSign file
	 * @param bool $showVisibleElements Whether to include visible elements in the response
	 * @param bool $showMessages Whether to include validation messages in the response
	 * @param bool $showValidateFile Whether to include the file payload in the response
	 * @return DataResponse<Http::STATUS_OK, LibresignValidatedFile, array{}>|DataResponse<Http::STATUS_NOT_FOUND, LibresignActionErrorResponse, array{}>
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
	public function validateUuid(
		string $uuid,
		bool $showVisibleElements = true,
		bool $showMessages = true,
		bool $showValidateFile = true,
	): DataResponse {
		return $this->validate('Uuid', $uuid, $showVisibleElements, $showMessages, $showValidateFile);
	}

	/**
	 * Validate a file using FileId
	 *
	 * Validate a file returning file data.
	 * The response always includes `filesCount` and `files`.
	 * For `nodeType=file`, `filesCount=1` and `files` contains the current file.
	 * For `nodeType=envelope`, `files` contains envelope child files.
	 *
	 * @param int $fileId The identifier value of the LibreSign file
	 * @param bool $showVisibleElements Whether to include visible elements in the response
	 * @param bool $showMessages Whether to include validation messages in the response
	 * @param bool $showValidateFile Whether to include the file payload in the response
	 * @return DataResponse<Http::STATUS_OK, LibresignValidatedFile, array{}>|DataResponse<Http::STATUS_NOT_FOUND, LibresignActionErrorResponse, array{}>
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
	public function validateFileId(
		int $fileId,
		bool $showVisibleElements = true,
		bool $showMessages = true,
		bool $showValidateFile = true,
	): DataResponse {
		return $this->validate('FileId', $fileId, $showVisibleElements, $showMessages, $showValidateFile);
	}

	/**
	 * Validate a binary file
	 *
	 * Validate a binary file returning file data.
	 * Use field 'file' for the file upload.
	 * The response always includes `filesCount` and `files`.
	 * For `nodeType=file`, `filesCount=1` and `files` contains the current file.
	 * For `nodeType=envelope`, `files` contains envelope child files.
	 *
	 * @return DataResponse<Http::STATUS_OK, LibresignValidatedFile, array{}>|DataResponse<Http::STATUS_NOT_FOUND|Http::STATUS_BAD_REQUEST, LibresignActionErrorResponse, array{}>
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
	 * @return DataResponse<Http::STATUS_OK, LibresignValidatedFile, array{}>|DataResponse<Http::STATUS_NOT_FOUND, LibresignActionErrorResponse, array{}>
	 */
	private function validate(
		?string $type = null,
		$identifier = null,
		bool $showVisibleElements = true,
		bool $showMessages = true,
		bool $showValidateFile = true,
	): DataResponse {
		try {
			$signRequest = null;
			if ($type === 'Uuid' && !empty($identifier)) {
				try {
					$this->fileService->setFileByUuid((string)$identifier);
				} catch (LibresignException) {
					$this->fileService->setFileBySignerUuid((string)$identifier);
					$signRequest = $this->signRequestMapper->getBySignerUuidAndUserId((string)$identifier);
				}
			} elseif ($type === 'SignerUuid' && !empty($identifier)) {
				$this->fileService->setFileBySignerUuid((string)$identifier);
				$signRequest = $this->signRequestMapper->getBySignerUuidAndUserId((string)$identifier);
			} elseif ($type === 'FileId' && !empty($identifier)) {
				$this->fileService->setFileById((int)$identifier);
			} elseif ($this->request->getParam('fileId')) {
				$this->fileService->setFileById((int)$this->request->getParam('fileId'));
			} elseif ($this->request->getParam('uuid')) {
				try {
					$this->fileService->setFileByUuid((string)$this->request->getParam('uuid'));
				} catch (LibresignException) {
					$this->fileService->setFileBySignerUuid((string)$this->request->getParam('uuid'));
					$signRequest = $this->signRequestMapper->getBySignerUuidAndUserId((string)$this->request->getParam('uuid'));
				}
			}

			if ($signRequest) {
				$this->fileService->setSignRequest($signRequest);
			}

			$return = $this->fileService
				->setMe($this->userSession->getUser())
				->setIdentifyMethodId($this->sessionService->getIdentifyMethodId())
				->setHost($this->request->getServerHost())
				->showVisibleElements($showVisibleElements)
				->showSigners()
				->showSettings()
				->showMessages($showMessages)
				->showValidateFile($showValidateFile)
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
	 * List identification documents that need to be approved
	 *
	 * @param string|null $signer_uuid Signer UUID
	 * @param list<int>|null $fileIds The list of fileIds (database file IDs). It's the ids of LibreSign files
	 * @param list<int>|null $nodeIds The list of nodeIds. It's the ids of files at Nextcloud
	 * @param list<int>|null $status Status could be none or many of 0 = draft, 1 = able to sign, 2 = partial signed, 3 = signed, 4 = deleted.
	 * @param int|null $page the number of page to return
	 * @param int|null $length Total of elements to return
	 * @param int|null $start Start date of signature request (UNIX timestamp)
	 * @param int|null $end End date of signature request (UNIX timestamp)
	 * @param string|null $sortBy Name of the column to sort by
	 * @param string|null $sortDirection Ascending or descending order
	 * @param int|null $parentFileId Filter files by parent envelope file ID
	 * @param bool $details Whether to return the detailed payload instead of the lightweight summary payload
	 * @return DataResponse<Http::STATUS_OK, LibresignFileListResponse, array{}>
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
		?array $fileIds = null,
		?array $nodeIds = null,
		?array $status = null,
		?int $start = null,
		?int $end = null,
		?string $sortBy = null,
		?string $sortDirection = null,
		?int $parentFileId = null,
		bool $details = false,
	): DataResponse {
		$filter = array_filter([
			'signer_uuid' => $signer_uuid,
			'fileIds' => $fileIds,
			'nodeIds' => $nodeIds,
			'status' => $status,
			'start' => $start,
			'end' => $end,
			'parentFileId' => $parentFileId,
		], static fn ($var) => $var !== null);
		$sort = [
			'sortBy' => $sortBy,
			'sortDirection' => $sortDirection,
		];

		$user = $this->userSession->getUser();
		$return = $this->fileListService->listAssociatedFilesOfSignFlow($user, $page, $length, $filter, $sort, $details);

		if ($user) {
			$return['settings'] = $this->settingsLoader->getUserIdentificationSettings($user);
		}

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
			$libreSignFile = $this->fileMapper->getByNodeId($nodeId);
			if ($libreSignFile->getUserId() !== $this->userSession->getUser()->getUID()) {
				return new DataResponse([], Http::STATUS_FORBIDDEN);
			}

			if ($libreSignFile->getNodeType() === 'envelope') {
				if ($mimeFallback) {
					$url = $this->mimeIconProvider->getMimeIconUrl('folder');
					if ($url) {
						return new RedirectResponse($url);
					}
				}
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}

			$node = $this->accountService->getPdfByUuid($libreSignFile->getUuid());
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		return $this->fetchPreview($node, $x, $y, $a, $forceIcon, $mode, $mimeFallback);
	}

	/**
	 * Return the thumbnail of a LibreSign file by fileId
	 *
	 * @param integer $fileId The LibreSign fileId (database id)
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
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/file/thumbnail/file_id/{fileId}', requirements: ['apiVersion' => '(v1)'])]
	public function getThumbnailByFileId(
		int $fileId = -1,
		int $x = 32,
		int $y = 32,
		bool $a = false,
		bool $forceIcon = true,
		string $mode = 'fill',
		bool $mimeFallback = false,
	) {
		if ($fileId === -1 || $x === 0 || $y === 0) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		try {
			$libreSignFile = $this->fileMapper->getById($fileId);
			if ($libreSignFile->getUserId() !== $this->userSession->getUser()->getUID()) {
				return new DataResponse([], Http::STATUS_FORBIDDEN);
			}

			if ($libreSignFile->getNodeType() === 'envelope') {
				if ($mimeFallback) {
					$url = $this->mimeIconProvider->getMimeIconUrl('folder');
					if ($url) {
						return new RedirectResponse($url);
					}
				}
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}

			$node = $this->accountService->getPdfByUuid($libreSignFile->getUuid());
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
	 * Send a new file to Nextcloud and return the fileId to request signature.
	 * Files must be uploaded as multipart/form-data with field name 'file[]' or 'files[]'.
	 *
	 * **Note on multiple file uploads:**
	 * PHP has a limit on the number of files that can be uploaded in a single request (max_file_uploads directive, default 20).
	 * When uploading many files (more than 20), consider uploading them sequentially in multiple requests
	 * or use individual file uploads like the Files app does.
	 *
	 * @param LibresignNewFile $file File to save
	 * @param string $name The name of file to sign
	 * @param LibresignFolderSettings $settings Settings to define how and where the file should be stored
	 * @param list<LibresignNewFile> $files Multiple files to create an envelope (optional, use either file or files)
	 * @return DataResponse<Http::STATUS_OK, LibresignDetailedFileResponse, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, LibresignMessageResponse, array{}>
	 *
	 * 200: OK
	 * 422: Failed to save data
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireManager]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/file', requirements: ['apiVersion' => '(v1)'])]
	public function save(
		array $file = [],
		string $name = '',
		array $settings = [],
		array $files = [],
	): DataResponse {
		try {
			$this->validateHelper->canRequestSign($this->userSession->getUser());

			$normalizedFiles = $this->prepareFilesForSaving($file, $files, $settings);

			return $this->saveFiles($normalizedFiles, $name, $settings);
		} catch (LibresignException $e) {
			return new DataResponse(
				[
					'message' => $e->getMessage(),
				],
				Http::STATUS_UNPROCESSABLE_ENTITY,
			);
		}
	}

	/**
	 * Add file to envelope
	 *
	 * Add one or more files to an existing envelope that is in DRAFT status.
	 * Files must be uploaded as multipart/form-data with field name 'files[]'.
	 *
	 * @param string $uuid The UUID of the envelope
	 * @return DataResponse<Http::STATUS_OK, LibresignDetailedFileResponse, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_UNPROCESSABLE_ENTITY, LibresignMessageResponse, array{}>
	 *
	 * 200: Files added successfully
	 * 400: Invalid request
	 * 404: Envelope not found
	 * 422: Cannot add files (envelope not in DRAFT status or validation failed)
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireManager]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/file/{uuid}/add-file', requirements: ['apiVersion' => '(v1)'])]
	public function addFileToEnvelope(string $uuid): DataResponse {
		try {
			$this->validateHelper->canRequestSign($this->userSession->getUser());

			$envelope = $this->fileMapper->getByUuid($uuid);

			if ($envelope->getNodeType() !== 'envelope') {
				throw new LibresignException($this->l10n->t('This is not an envelope'));
			}

			if ($envelope->getStatus() !== FileStatus::DRAFT->value) {
				throw new LibresignException($this->l10n->t('Cannot add files to an envelope that is not in draft status'));
			}

			$settings = $envelope->getMetadata()['settings'] ?? [];

			$uploadedFiles = $this->request->getUploadedFile('files');
			if (!$uploadedFiles) {
				throw new LibresignException($this->l10n->t('No files uploaded'));
			}

			$normalizedFiles = $this->processUploadedFiles($uploadedFiles);

			$addedFiles = [];
			foreach ($normalizedFiles as $fileData) {
				$prepared = $this->prepareFileForSaving($fileData, '', $settings);

				$childFile = $this->requestSignatureService->save([
					'file' => ['fileNode' => $prepared['node']],
					'name' => $prepared['name'],
					'userManager' => $this->userSession->getUser(),
					'status' => FileStatus::DRAFT->value,
					'parentFileId' => $envelope->getId(),
				]);

				$addedFiles[] = $childFile;
			}

			$this->fileService->updateEnvelopeFilesCount($envelope, count($addedFiles));

			$envelope = $this->fileMapper->getById($envelope->getId());
			$response = $this->fileListService->formatFileWithChildren($envelope, $addedFiles, $this->userSession->getUser());
			return new DataResponse($response, Http::STATUS_OK);

		} catch (DoesNotExistException) {
			return new DataResponse(
				['message' => $this->l10n->t('Envelope not found')],
				Http::STATUS_NOT_FOUND,
			);
		} catch (LibresignException $e) {
			return new DataResponse(
				['message' => $e->getMessage()],
				Http::STATUS_UNPROCESSABLE_ENTITY,
			);
		} catch (\Exception $e) {
			$this->logger->error('Failed to add file to envelope', [
				'exception' => $e,
			]);
			return new DataResponse(
				['message' => $this->l10n->t('Failed to add file to envelope')],
				Http::STATUS_BAD_REQUEST,
			);
		}
	}

	/**
	 * @return array{node: Node, name: string}
	 */
	private function prepareFileForSaving(array $fileData, string $name, array $settings): array {
		if (empty($name)) {
			$name = $this->extractFileName($fileData);
		}
		if (empty($name)) {
			throw new LibresignException($this->l10n->t('File name is required'));
		}

		if (isset($fileData['fileNode']) && $fileData['fileNode'] instanceof Node) {
			$node = $fileData['fileNode'];
			$name = $fileData['name'] ?? $name;
		} elseif (isset($fileData['uploadedFile'])) {
			$this->fileService->validateUploadedFile($fileData['uploadedFile']);

			$node = $this->fileService->getNodeFromData([
				'userManager' => $this->userSession->getUser(),
				'name' => $name,
				'uploadedFile' => $fileData['uploadedFile'],
				'settings' => $settings
			]);
		} else {
			$this->validateHelper->validateNewFile([
				'file' => $fileData,
				'userManager' => $this->userSession->getUser(),
			]);

			$node = $this->fileService->getNodeFromData([
				'userManager' => $this->userSession->getUser(),
				'name' => $name,
				'file' => $fileData,
				'settings' => $settings
			]);
		}

		return [
			'node' => $node,
			'name' => $name,
		];
	}

	/**
	 * @return list<array{fileNode?: Node, name?: string, uploadedFile?: array}> Normalized files array
	 */
	private function prepareFilesForSaving(array $file, array $files, array $settings): array {
		$uploadedFiles = $this->request->getUploadedFile('files') ?: $this->request->getUploadedFile('file');

		if ($uploadedFiles) {
			return $this->processUploadedFiles($uploadedFiles);
		}

		if (!empty($files)) {
			/** @var list<array{fileNode?: Node, name?: string}> $files */
			return $files;
		}

		if (!empty($file)) {
			return [$file];
		}

		throw new LibresignException($this->l10n->t('File or files parameter is required'));
	}

	/**
	 * @return list<array{uploadedFile: array, name: string}>
	 */
	private function processUploadedFiles(array $uploadedFiles): array {
		$filesArray = [];

		if (isset($uploadedFiles['tmp_name'])) {
			if (is_array($uploadedFiles['tmp_name'])) {
				$count = count($uploadedFiles['tmp_name']);
				for ($i = 0; $i < $count; $i++) {
					$uploadedFile = [
						'tmp_name' => $uploadedFiles['tmp_name'][$i],
						'name' => $uploadedFiles['name'][$i],
						'type' => $uploadedFiles['type'][$i],
						'size' => $uploadedFiles['size'][$i],
						'error' => $uploadedFiles['error'][$i],
					];
					$this->fileService->validateUploadedFile($uploadedFile);
					$filesArray[] = [
						'uploadedFile' => $uploadedFile,
						'name' => pathinfo($uploadedFile['name'], PATHINFO_FILENAME),
					];
				}
			} else {
				$this->fileService->validateUploadedFile($uploadedFiles);
				$filesArray[] = [
					'uploadedFile' => $uploadedFiles,
					'name' => pathinfo($uploadedFiles['name'], PATHINFO_FILENAME),
				];
			}
		}

		if (empty($filesArray)) {
			throw new LibresignException($this->l10n->t('No files uploaded'));
		}

		return $filesArray;
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, LibresignDetailedFileResponse, array{}>
	 */
	private function saveFiles(array $files, string $name, array $settings): DataResponse {
		if (empty($files)) {
			throw new LibresignException($this->l10n->t('File or files parameter is required'));
		}

		$result = $this->requestSignatureService->saveFiles([
			'files' => $files,
			'name' => $name,
			'userManager' => $this->userSession->getUser(),
			'settings' => $settings,
		]);

		$response = $this->fileListService->formatFileWithChildren($result['file'], $result['children'], $this->userSession->getUser());
		return new DataResponse($response, Http::STATUS_OK);
	}

	private function extractFileName(array $fileData): string {
		if (!empty($fileData['name'])) {
			return $fileData['name'];
		}
		if (!empty($fileData['url'])) {
			return rawurldecode(pathinfo($fileData['url'], PATHINFO_FILENAME));
		}
		return '';
	}

	/**
	 * Delete File
	 *
	 * This will delete the file and all data
	 *
	 * @param integer $fileId LibreSign file ID
	 * @param boolean $deleteFile Whether to delete the physical file from Nextcloud (default: true)
	 * @return DataResponse<Http::STATUS_OK, LibresignMessageResponse, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, LibresignMessageResponse, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, LibresignActionErrorResponse, array{}>
	 *
	 * 200: OK
	 * 401: Failed
	 * 422: Failed
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireManager]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/file/file_id/{fileId}', requirements: ['apiVersion' => '(v1)'])]
	public function deleteAllRequestSignatureUsingFileId(int $fileId, bool $deleteFile = true): DataResponse {
		try {
			$data = [
				'userManager' => $this->userSession->getUser(),
				'file' => [
					'fileId' => $fileId
				]
			];
			$this->validateHelper->validateExistingFile($data);
			$this->fileService->delete($fileId, $deleteFile);
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
