<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Service\FileService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
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

	public function __construct(
		IRequest $request,
		IL10N $l10n,
		LoggerInterface $logger,
		IUserSession $userSession,
		FileService $fileService
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->userSession = $userSession;
		$this->fileService = $fileService;
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
	 * @NoCSRFRequired
	 * @return JSONResponse|FileDisplayResponse
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
}
