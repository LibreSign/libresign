<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\TCPDILibresign;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\FileService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class FileController extends Controller {
	/** @var FileUserMapper */
	private $fileUserMapper;
	/** @var FileMapper */
	private $fileMapper;
	/** @var IL10N */
	private $l10n;
	/** @var AccountService */
	private $accountService;
	/** @var LoggerInterface */
	private $logger;
	/** @var IUserSession */
	private $userSession;
	/** @var FileService */
	private $fileService;
	/** @var IRootFolder */
	private $rootFolder;

	public function __construct(
		IRequest $request,
		FileUserMapper $fileUserMapper,
		FileMapper $fileMapper,
		IL10N $l10n,
		AccountService $accountService,
		LoggerInterface $logger,
		IUserSession $userSession,
		FileService $fileService,
		IRootFolder $rootFolder
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->fileUserMapper = $fileUserMapper;
		$this->fileMapper = $fileMapper;
		$this->l10n = $l10n;
		$this->accountService = $accountService;
		$this->logger = $logger;
		$this->userSession = $userSession;
		$this->fileService = $fileService;
		$this->rootFolder = $rootFolder;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @NoCSRFRequired
	 *
	 * @PublicPage
	 *
	 * @return JSONResponse
	 */
	public function validateUuid($uuid): JSONResponse {
		return $this->validate('Uuid', $uuid);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @NoCSRFRequired
	 *
	 * @PublicPage
	 *
	 * @return JSONResponse
	 */
	public function validateFileId($fileId): JSONResponse {
		return $this->validate('FileId', $fileId);
	}

	private function validate(string $type, $identifier): JSONResponse {
		try {
			try {
				/** @var File */
				$file = call_user_func(
					[$this->fileMapper, 'getBy' . $type],
					$identifier
				);
				$this->fileService->setFile($file);
			} catch (\Throwable $th) {
				throw new LibresignException($this->l10n->t('Invalid data to validate file'), 404);
			}
			if (!$file) {
				throw new LibresignException($this->l10n->t('Invalid file identifier'), 404);
			}

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
				->showSigners()
				->showSettings()
				->showVisibleElements()
				->showPages()
				->setMe($this->userSession->getUser())
				->formatFile()
		);
		if ($return['settings']['canSign']) {
			$return['messages'] = [
				[
					'type' => 'info',
					'message' => $this->l10n->t('You need to sign this document')
				]
			];
		}
		if (!$return['settings']['canRequestSign'] && empty($return['signatures'])) {
			$return['messages'] = [
				[
					'type' => 'info',
					'message' => $this->l10n->t('You cannot request signature for this document, please contact your administrator')
				]
			];
		}

		return new JSONResponse($return, $statusCode);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function list($page = null, $length = null): JSONResponse {
		$return = $this->accountService->listAssociatedFilesOfSignFlow($this->userSession->getUser(), $page, $length);
		return new JSONResponse($return, Http::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return JSONResponse|FileDisplayResponse
	 */
	public function getPage(string $uuid, int $page) {
		try {
			$libreSignFile = $this->fileMapper->getByUuid($uuid);
			$uid = $this->userSession->getUser()->getUID();
			if ($libreSignFile->getUserId() !== $uid) {
				$signers = $this->fileUserMapper->getByFileId($libreSignFile->id);
				if (!$signers) {
					throw new LibresignException($this->l10n->t('No signers.'));
				}
				$iNeedSign = false;
				foreach ($signers as $signer) {
					if ($signer->getUserId() === $uid) {
						$iNeedSign = true;
						break;
					}
				}
				if (!$iNeedSign) {
					throw new LibresignException($this->l10n->t('You must not sign this file.'));
				}
			}
			$userFolder = $this->rootFolder->getUserFolder($libreSignFile->getUserId());
			$file = $userFolder->getById($libreSignFile->getNodeId());
			$pdf = new TCPDILibresign();
			$pageCount = $pdf->setSourceData($file[0]->getContent());
			if ($page > $pageCount || $page < 1) {
				throw new LibresignException($this->l10n->t('Page not found.'));
			}
			$templateId = $pdf->importPage($page);
			$pdf->AddPage();
			$pdf->useTemplate($templateId);
			$blob = $pdf->Output(null, 'S');
			$imagick = new \Imagick();
			$imagick->setResolution(100, 100);
			$imagick->readImageBlob($blob);
			$imagick->setImageFormat('png');
			return new DataDisplayResponse(
				$imagick->getImageBlob(),
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
