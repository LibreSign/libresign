<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\TCPDILibresign;
use OCP\Accounts\IAccountManager;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;

class FileService {
	/** @var FileMapper */
	private $fileMapper;
	/** @var FileUserMapper */
	private $fileUserMapper;
	/** @var FileElementMapper */
	private $fileElementMapper;
	/** @var FileElementService */
	private $fileElementService;
	/** @var AccountService */
	private $accountService;
	/** @var AccountFileService */
	private $accountFileService;
	/** @var IUserManager */
	private $userManager;
	/** @var IAccountManager */
	private $accountManager;
	/** @var IConfig */
	private $config;
	/** @var IRootFolder */
	private $rootFolder;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var IL10N */
	private $l10n;
	/** @var bool */
	private $showSigners = false;
	/** @var bool */
	private $showSettings = false;
	/** @var bool */
	private $showPages = false;
	/** @var bool */
	private $showVisibleElements = false;
	/** @var bool */
	private $showMessages = false;
	/** @var File|null */
	private $file;
	/** @var IUser|null */
	private $me;
	/** @var array */
	private $signers = [];
	/** @var array */
	private $settings = [
		'canSign' => false,
		'canRequestSign' => false,
		'hasSignatureFile' => false,
		'signerFileUuid' => null,
		'phoneNumber' => '',
		'signMethod' => 'password'
	];
	public const IDENTIFICATION_DOCUMENTS_DISABLED = 0;
	public const IDENTIFICATION_DOCUMENTS_NEED_SEND = 1;
	public const IDENTIFICATION_DOCUMENTS_NEED_APPROVAL = 2;
	public const IDENTIFICATION_DOCUMENTS_APPROVED = 3;
	public function __construct(
		FileMapper $fileMapper,
		FileUserMapper $fileUserMapper,
		FileElementMapper $fileElementMapper,
		FileElementService $fileElementService,
		AccountService $accountService,
		AccountFileService $accountFileService,
		IUserManager $userManager,
		IAccountManager $accountManager,
		IConfig $config,
		IRootFolder $rootFolder,
		IURLGenerator $urlGenerator,
		IL10N $l10n
	) {
		$this->fileMapper = $fileMapper;
		$this->fileUserMapper = $fileUserMapper;
		$this->fileElementMapper = $fileElementMapper;
		$this->fileElementService = $fileElementService;
		$this->accountService = $accountService;
		$this->accountFileService = $accountFileService;
		$this->userManager = $userManager;
		$this->accountManager = $accountManager;
		$this->config = $config;
		$this->rootFolder = $rootFolder;
		$this->urlGenerator = $urlGenerator;
		$this->l10n = $l10n;
	}

	public function showSigners(bool $show = true): self {
		$this->showSigners = $show;
		return $this;
	}

	public function showSettings(bool $show = true): self {
		$this->showSettings = $show;
		return $this;
	}

	public function showPages(bool $show = true): self {
		$this->showPages = $show;
		return $this;
	}

	public function showVisibleElements(bool $show = true): self {
		$this->showVisibleElements = $show;
		return $this;
	}

	public function showMessages(bool $show = true): self {
		$this->showMessages = $show;
		return $this;
	}

	public function setMe(?IUser $user): self {
		$this->me = $user;
		return $this;
	}

	public function setFile($file): self {
		$this->file = $file;
		return $this;
	}

	public function setFileByType(string $type, $identifier): self {
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
		$this->setFile($file);
		return $this;
	}

	private function getSigners(): array {
		if (!$this->file) {
			return $this->signers;
		}
		$signers = $this->fileUserMapper->getByFileId($this->file->getId());
		if ($this->me) {
			$uid = $this->me->getUID();
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
				if ($uid === $this->file->getUserId()) {
					$signatureToShow['email'] = $signer->getEmail();
					$user = $this->userManager->getByEmail($signer->getEmail());
					if ($user) {
						$signatureToShow['uid'] = $user[0]->getUID();
					}
				}
				$signatureToShow['me'] = $uid === $signer->getUserId();
				if ($uid === $signer->getUserId() && !$signer->getSigned()) {
					$this->settings['canSign'] = true;
					$this->settings['signerFileUuid'] = $signer->getUuid();
				}
			}
			$this->signers[] = $signatureToShow;
		}
		return $this->signers;
	}

	private function getPages(): array {
		$return = [];
		$metadata = json_decode($this->file->getMetadata());
		for ($page = 1; $page <= $metadata->p; $page++) {
			$return[] = [
				'url' => $this->urlGenerator->linkToRoute('libresign.libreSignFile.getPage', ['uuid' => $this->file->getUuid(), 'page' => $page]),
				'resolution' => $metadata->d[$page - 1]
			];
		}
		return $return;
	}

	private function getVisibleElements(): array {
		$return = [];
		try {
			if ($this->me) {
				$uid = $this->me->getUID();
			}
			$visibleElements = $this->fileElementMapper->getByFileId($this->file->getId());
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
				if (!empty($uid) && $uid === $this->file->getUserId()) {
					$fileUser = $this->fileUserMapper->getById($visibleElement->getFileUserId());
					$userAssociatedToVisibleElement = $this->userManager->getByEmail($fileUser->getEmail());
					if ($userAssociatedToVisibleElement) {
						$element['uid'] = $userAssociatedToVisibleElement[0]->getUID();
					}
					$element['email'] = $fileUser->getEmail();
				}
				$element['coordinates'] = array_merge(
					$element['coordinates'],
					$this->fileElementService->translateCoordinatesFromInternalNotation($element, $this->file)
				);
				$return[] = $element;
			}
		} catch (\Throwable $th) {
		}
		return $return;
	}

	private function getPhoneNumber() {
		if (!$this->me) {
			return '';
		}
		$userAccount = $this->accountManager->getAccount($this->me);
		return $userAccount->getProperty(IAccountManager::PROPERTY_PHONE)->getValue();
	}

	private function getSettings(): array {
		if ($this->me) {
			$this->settings = array_merge($this->settings, $this->accountService->getSettings($this->me));
			$this->settings['phoneNumber'] = $this->getPhoneNumber($this->me);
			$status = $this->getIdentificationDocumentsStatus($this->me->getUID());
			if ($status === self::IDENTIFICATION_DOCUMENTS_NEED_SEND) {
				$this->settings['needIdentificationDocuments'] = true;
				$this->settings['identificationDocumentsWaitingApproval'] = false;
			} elseif ($status === self::IDENTIFICATION_DOCUMENTS_NEED_APPROVAL) {
				$this->settings['needIdentificationDocuments'] = true;
				$this->settings['identificationDocumentsWaitingApproval'] = true;
			}
		}
		$this->settings['signMethod'] = $this->config->getAppValue(Application::APP_ID, 'sign_method', 'password');
		return $this->settings;
	}

	public function getIdentificationDocumentsStatus($userId): int {
		if (!$this->config->getAppValue(Application::APP_ID, 'identification_documents', false)) {
			return self::IDENTIFICATION_DOCUMENTS_DISABLED;
		}

		$files = $this->fileMapper->getFilesOfAccount($userId);
		if (!count($files)) {
			return self::IDENTIFICATION_DOCUMENTS_NEED_SEND;
		}
		$deleted = array_filter($files, function (File $file) {
			return $file->getStatus() === File::STATUS_DELETED;
		});
		if (count($deleted) === count($files)) {
			return self::IDENTIFICATION_DOCUMENTS_NEED_SEND;
		}

		$signed = array_filter($files, function (File $file) {
			return $file->getStatus() === File::STATUS_SIGNED;
		});
		if (count($signed) !== count($files)) {
			return self::IDENTIFICATION_DOCUMENTS_NEED_APPROVAL;
		}

		return self::IDENTIFICATION_DOCUMENTS_APPROVED;
	}

	private function getFile(): array {
		$return = [];
		if (!$this->file) {
			return $return;
		}
		$return['status'] = $this->file->getStatus();
		$return['statusText'] = $this->fileMapper->getTextOfStatus($this->file->getStatus());
		$return['fileId'] = $this->file->getNodeId();
		$return['uuid'] = $this->file->getUuid();
		$return['name'] = $this->file->getName();
		$return['file'] = $this->urlGenerator->linkToRoute('libresign.page.getPdf', ['uuid' => $this->file->getUuid()]);

		if ($this->showSigners) {
			$return['signers'] = $this->getSigners();
		}
		if ($this->showPages) {
			$return['pages'] = $this->getPages();
		}
		if ($this->showVisibleElements) {
			$visibleElements = $this->getVisibleElements();
			if ($visibleElements) {
				$return['visibleElements'] = $visibleElements;
			}
		}
		return $return;
	}

	private function getMessages(): array {
		$messages = [];
		if ($this->settings['canSign']) {
			$messages[] = [
				'type' => 'info',
				'message' => $this->l10n->t('You need to sign this document')
			];
		}
		if (!$this->settings['canRequestSign'] && empty($this->signers)) {
			$messages[] = [
				'type' => 'info',
				'message' => $this->l10n->t('You cannot request signature for this document, please contact your administrator')
			];
		}
		return $messages;
	}

	public function formatFile(): array {
		$return = $this->getFile();
		if ($this->showSettings) {
			$return['settings'] = $this->getSettings();
		}
		if ($this->showMessages) {
			$messages = $this->getMessages();
			if ($messages) {
				$return['messages'] = $messages;
			}
		}
		return $return;
	}

	public function getPage(string $uuid, int $page, string $uid): string {
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
		return $imagick->getImageBlob();
	}

	/**
	 * @return array[]
	 *
	 * @psalm-return array{data: array, pagination: array}
	 */
	public function listAssociatedFilesOfSignFlow(IUser $user, $page = null, $length = null): array {
		$page = $page ?? 1;
		$length = $length ?? $this->config->getAppValue(Application::APP_ID, 'length_of_page', 100);

		$url = $this->urlGenerator->linkToRoute('libresign.page.getPdfUser', ['uuid' => '_replace_']);
		$url = str_replace('_replace_', '', $url);

		$data = $this->fileUserMapper->getFilesAssociatedFilesWithMeFormatted($user->getUID(), $url, $page, $length);
		$data['pagination']->setRootPath('/file/list');
		return [
			'data' => $data['data'],
			'pagination' => $data['pagination']->getPagination($page, $length)
		];
	}
}
