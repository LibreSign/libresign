<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\TCPDILibresign;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Service\AccountService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ITempManager;
use OCP\IURLGenerator;
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
	private $account;
	/** @var LoggerInterface */
	private $logger;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var IUserSession */
	private $userSession;
	/** @var FileElementMapper */
	private $fileElementMapper;
	/** @var IRootFolder */
	private $rootFolder;
	/** @var ITempManager */
	private $tempManager;

	public function __construct(
		IRequest $request,
		FileUserMapper $fileUserMapper,
		FileMapper $fileMapper,
		IL10N $l10n,
		AccountService $account,
		LoggerInterface $logger,
		IURLGenerator $urlGenerator,
		IUserSession $userSession,
		FileElementMapper $fileElementMapper,
		IRootFolder $rootFolder,
		ITempManager $tempManager
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->fileUserMapper = $fileUserMapper;
		$this->fileMapper = $fileMapper;
		$this->l10n = $l10n;
		$this->account = $account;
		$this->logger = $logger;
		$this->urlGenerator = $urlGenerator;
		$this->userSession = $userSession;
		$this->fileElementMapper = $fileElementMapper;
		$this->rootFolder = $rootFolder;
		$this->tempManager = $tempManager;
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
					'signatureId' => $signer->getId()
				];
				if (!empty($uid)) {
					if ($uid === $file->getUserId()) {
						$signatureToShow['email'] = $signer->getEmail();
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
						'uid' => $visibleElement->getUserId(),
						'type' => $visibleElement->getType(),
						'coordinates' => [
							'page' => $visibleElement->getPage(),
							'urx' => $visibleElement->getUrx(),
							'ury' => $visibleElement->getUry(),
							'llx' => $visibleElement->getLlx(),
							'lly' => $visibleElement->getLly()
						]
					];
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
				$this->account->getSettings($this->userSession->getUser())
			);
		}
		return new JSONResponse($return, $statusCode);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function list($page = null, $length = null): JSONResponse {
		$return = $this->account->list($this->userSession->getUser(), $page, $length);
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
}
