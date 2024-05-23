<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Files_Sharing\SharedStorage;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Middleware\Attribute\RequireManager;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\SessionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
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
	public function list($page = null, $length = null, ?array $filter = []): JSONResponse {
		$return = $this->fileService
			->setMe($this->userSession->getUser())
			->listAssociatedFilesOfSignFlow($page, $length, $filter);
		return new JSONResponse($return, Http::STATUS_OK);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
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
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		try {
			$myLibreSignFile = $this->fileService
				->setMe($this->userSession->getUser())
				->getMyLibresignFile($nodeId);
			$node = $this->accountService->getPdfByUuid($myLibreSignFile->getUuid());
		} catch (DoesNotExistException $e) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		return $this->fetchPreview($node, $x, $y, $a, $forceIcon, $mode, $mimeFallback);
	}

	/**
	 * @return FileDisplayResponse<Http::STATUS_OK, array{Content-Type: string}>|JSONResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND, array<empty>, array{}>|RedirectResponse<Http::STATUS_SEE_OTHER, array{}>
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
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}
		if (!$node->isReadable()) {
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		$storage = $node->getStorage();
		if ($storage->instanceOfStorage(SharedStorage::class)) {
			/** @var SharedStorage $storage */
			$share = $storage->getShare();
			$attributes = $share->getAttributes();
			if ($attributes !== null && $attributes->getAttribute('permissions', 'download') === false) {
				return new JSONResponse([], Http::STATUS_FORBIDDEN);
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

			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
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
}
