<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\TCPDILibresign;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\FileElementService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class LibreSignFileController extends Controller {
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
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var IUserSession */
	private $userSession;
	/** @var IUserManager */
	private $userManager;
	/** @var FileElementMapper */
	private $fileElementMapper;
	/** @var ValidateHelper */
	private $validateHelper;
	/** @var FileElementService */
	private $fileElementService;
	/** @var IRootFolder */
	private $rootFolder;

	public function __construct(
		IRequest $request,
		FileUserMapper $fileUserMapper,
		FileMapper $fileMapper,
		IL10N $l10n,
		AccountService $accountService,
		LoggerInterface $logger,
		IURLGenerator $urlGenerator,
		IUserSession $userSession,
		IUserManager $userManager,
		FileElementMapper $fileElementMapper,
		FileElementService $fileElementService,
		ValidateHelper $validateHelper,
		IRootFolder $rootFolder
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->fileUserMapper = $fileUserMapper;
		$this->fileMapper = $fileMapper;
		$this->l10n = $l10n;
		$this->accountService = $accountService;
		$this->logger = $logger;
		$this->urlGenerator = $urlGenerator;
		$this->userSession = $userSession;
		$this->userManager = $userManager;
		$this->fileElementMapper = $fileElementMapper;
		$this->fileElementService = $fileElementService;
		$this->validateHelper = $validateHelper;
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
		$canSign = false;
		try {
			if ($this->userSession->getUser()) {
				$uid = $this->userSession->getUser()->getUID();
			}
			try {
				/** @var File */
				$file = call_user_func(
					[$this->fileMapper, 'getBy' . $type],
					$identifier
				);
			} catch (\Throwable $th) {
				throw new LibresignException($this->l10n->t('Invalid data to validate file'), 404);
			}
			if (!$file) {
				throw new LibresignException($this->l10n->t('Invalid file identifier'), 404);
			}

			$return['success'] = true;
			$return['status'] = $file->getStatus();
			$return['name'] = $file->getName();
			$return['file'] = $this->urlGenerator->linkToRoute('libresign.page.getPdf', ['uuid' => $file->getUuid()]);
			$signers = $this->fileUserMapper->getByFileId($file->id);
			if ($this->userSession->getUser()) {
				$uid = $this->userSession->getUser()->getUID();
			}
			foreach ($signers as $signer) {
				$signatureToShow = [
					'signed' => $signer->getSigned(),
					'displayName' => $signer->getDisplayName(),
					'fullName' => $signer->getFullName(),
					'me' => false,
					'fileUserId' => $signer->getId()
				];
				if (!empty($uid)) {
					if ($uid === $file->getUserId()) {
						$signatureToShow['email'] = $signer->getEmail();
						$user = $this->userManager->getByEmail($signer->getEmail());
						if ($user) {
							$signatureToShow['uid'] = $user[0]->getUID();
						}
					}
					$signatureToShow['me'] = $uid === $signer->getUserId();
					if ($uid === $signer->getUserId() && !$signer->getSigned()) {
						$canSign = true;
					}
				}
				$return['signers'][] = $signatureToShow;
			}
			try {
				$visibleElements = $this->fileElementMapper->getByFileId($file->id);
				foreach ($visibleElements as $visibleElement) {
					$element = [
						'elementId' => $visibleElement->getId(),
						'fileUserId' => $visibleElement->getFileUserId(),
						'type' => $visibleElement->getType(),
						'coordinates' => [
							'page' => $visibleElement->getPage(),
							'urx' => $visibleElement->getUrx(),
							'ury' => $visibleElement->getUry(),
							'llx' => $visibleElement->getLlx(),
							'lly' => $visibleElement->getLly()
						]
					];
					if ($uid === $file->getUserId()) {
						$fileUser = $this->fileUserMapper->getById($visibleElement->getFileUserId());
						$userAssociatedToVisibleElement = $this->userManager->getByEmail($fileUser->getEmail());
						if ($userAssociatedToVisibleElement) {
							$element['uid'] = $userAssociatedToVisibleElement[0]->getUID();
						}
						$element['email'] = $fileUser->getEmail();
					}
					$element['coordinates'] = array_merge(
						$element['coordinates'],
						$this->fileElementService->translateCoordinatesFromInternalNotation($element, $file)
					);
					if ($visibleElement->getSignatureFileId()) {
						$return['file']['url'] = $this->urlGenerator->linkToRoute('files.View.showFile', ['fileid' => $visibleElement->getSignatureFileId()]);
					}
					$return['visibleElements'][] = $element;
				}
			} catch (\Throwable $th) {
			}
			$metadata = json_decode($file->getMetadata());
			for ($page = 1; $page <= $metadata->p; $page++) {
				$return['pages'][] = [
					'url' => $this->urlGenerator->linkToRoute('libresign.libreSignFile.getPage', ['uuid' => $file->getUuid(), 'page' => $page]),
					'resolution' => $metadata->d[$page - 1]
				];
			}
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
		$return['settings'] = [
			'canSign' => $canSign,
			'canRequestSign' => false,
			'hasSignatureFile' => false
		];
		if (!empty($uid)) {
			$return['settings'] = array_merge(
				$return['settings'],
				$this->accountService->getSettings($this->userSession->getUser())
			);
		}
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
			$pageCount = $pdf->setNextcloudSourceFile($file[0]);
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

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return JSONResponse|FileDisplayResponse
	 */
	public function postElement(string $uuid, int $fileUserId, int $elementId = null, string $type = '', array $metadata = [], array $coordinates = []): JSONResponse {
		$visibleElement = [
			'elementId' => $elementId,
			'type' => $type,
			'fileUserId' => $fileUserId,
			'coordinates' => $coordinates,
			'metadata' => $metadata,
			'fileUuid' => $uuid,
		];
		try {
			$this->validateHelper->validateVisibleElement($visibleElement, ValidateHelper::TYPE_VISIBLE_ELEMENT_PDF);
			$this->validateHelper->validateExistingFile([
				'uuid' => $uuid,
				'userManager' => $this->userSession->getUser()
			]);
			$fileElement = $this->fileElementService->saveVisibleElement($visibleElement, $uuid);
			$return = [
				'fileElementId' => $fileElement->getId(),
				'success' => true,
			];
			$statusCode = Http::STATUS_OK;
		} catch (\Throwable $th) {
			$this->logger->error($th->getMessage());
			$return = [
				'success' => false,
				'errors' => [$th->getMessage()]
			];
			$statusCode = $th->getCode() > 0 ? $th->getCode() : Http::STATUS_NOT_FOUND;
		}
		return new JSONResponse($return, $statusCode);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return JSONResponse|FileDisplayResponse
	 */
	public function patchElement(string $uuid, int $fileUserId, int $elementId = null, string $type = '', array $metadata = [], array $coordinates = []) {
		return $this->postElement($uuid, $fileUserId, $elementId, $type, $metadata , $coordinates);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return JSONResponse|FileDisplayResponse
	 */
	public function deletelement(string $uuid, int $elementId): JSONResponse {
		try {
			$this->validateHelper->validateExistingFile([
				'uuid' => $uuid,
				'userManager' => $this->userSession->getUser()
			]);
			$this->validateHelper->validateUserIsOwnerOfPdfVisibleElement($elementId, $this->userSession->getUser()->getUID());
			$this->fileElementService->deleteVisibleElement($elementId);
			$return = [];
			$statusCode = Http::STATUS_OK;
		} catch (\Throwable $th) {
			$this->logger->error($th->getMessage());
			$return = [
				'success' => false,
				'errors' => [$th->getMessage()]
			];
			$statusCode = $th->getCode() > 0 ? $th->getCode() : Http::STATUS_NOT_FOUND;
		}
		return new JSONResponse($return, $statusCode);
	}
}
