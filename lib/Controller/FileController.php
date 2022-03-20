<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\FileService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class FileController extends Controller {
	/** @var IL10N */
	private $l10n;
	/** @var LoggerInterface */
	private $logger;
	/** @var IUserSession */
	private $userSession;
	/** @var FileService */
	private $fileService;
	/** @var ValidateHelper */
	private $validateHelper;

	public function __construct(
		IRequest $request,
		IL10N $l10n,
		LoggerInterface $logger,
		IUserSession $userSession,
		FileService $fileService,
		ValidateHelper $validateHelper
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->userSession = $userSession;
		$this->fileService = $fileService;
		$this->validateHelper = $validateHelper;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * @return JSONResponse
	 */
	public function validateUuid($uuid): JSONResponse {
		return $this->validate('Uuid', $uuid);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * @return JSONResponse
	 */
	public function validateFileId($fileId): JSONResponse {
		return $this->validate('FileId', $fileId);
	}

	private function validate(string $type, $identifier): JSONResponse {
		try {
			$this->fileService->setFileByType($type, $identifier);
			$return['success'] = true;
			$statusCode = Http::STATUS_OK;
		} catch (LibresignException $e) {
			$message = $this->l10n->t($e->getMessage());
			$return = [
				'success' => false,
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [$message]
			];
			$statusCode = $e->getCode() ?? Http::STATUS_UNPROCESSABLE_ENTITY;
		} catch (\Throwable $th) {
			$message = $this->l10n->t($th->getMessage());
			$this->logger->error($message);
			$return = [
				'success' => false,
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [$message]
			];
			$statusCode = $th->getCode() ?? Http::STATUS_UNPROCESSABLE_ENTITY;
		}

		$return = array_merge($return,
			$this->fileService
				->setMe($this->userSession->getUser())
				->showVisibleElements()
				->showPages()
				->showSigners()
				->showSettings()
				->showMessages()
				->formatFile()
		);

		return new JSONResponse($return, $statusCode);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function list($page = null, $length = null): JSONResponse {
		$return = $this->fileService->listAssociatedFilesOfSignFlow($this->userSession->getUser(), $page, $length);
		return new JSONResponse($return, Http::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @NoCSRFRequired
	 *
	 * @return DataDisplayResponse|JSONResponse
	 */
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
				'success' => false,
				'errors' => [$th->getMessage()]
			];
			$statusCode = $th->getCode() > 0 ? $th->getCode() : Http::STATUS_NOT_FOUND;
			return new JSONResponse($return, $statusCode);
		}
	}


	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return JSONResponse
	 */
	public function save(string $name, array $file, array $settings = []): JSONResponse {
		try {
			if (empty($name)) {
				throw new \Exception($this->l10n->t('Name is mandatory'));
			}
			$this->validateHelper->validateNewFile(['file' => $file]);
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
