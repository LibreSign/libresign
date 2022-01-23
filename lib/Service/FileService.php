<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCP\Accounts\IAccountManager;
use OCP\IConfig;
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
	/** @var IUserManager */
	private $userManager;
	/** @var IAccountManager */
	private $accountManager;
	/** @var IConfig */
	private $config;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var bool */
	private $showSigners = false;
	/** @var bool */
	private $showSettings = false;
	/** @var bool */
	private $showPages = false;
	/** @var bool */
	private $showVisibleElements = false;
	/** @var File|null */
	private $file;
	/** @var IUser|null */
	private $me;
	/** @var array */
	private $settings = [
		'canSign' => false,
		'canRequestSign' => false,
		'hasSignatureFile' => false,
		'signerFileUuid' => null,
		'phoneNumber' => '',
		'signMethod' => 'password'
	];
	public function __construct(
		FileMapper $fileMapper,
		FileUserMapper $fileUserMapper,
		FileElementMapper $fileElementMapper,
		FileElementService $fileElementService,
		AccountService $accountService,
		IUserManager $userManager,
		IAccountManager $accountManager,
		IConfig $config,
		IURLGenerator $urlGenerator
	) {
		$this->fileMapper = $fileMapper;
		$this->fileUserMapper = $fileUserMapper;
		$this->fileElementMapper = $fileElementMapper;
		$this->fileElementService = $fileElementService;
		$this->accountService = $accountService;
		$this->userManager = $userManager;
		$this->accountManager = $accountManager;
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
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

	public function setMe(?IUser $user): self {
		$this->me = $user;
		return $this;
	}

	public function setFile($file): self {
		$this->file = $file;
		return $this;
	}

	private function getSigners(): array {
		$return = [];
		if (!$this->file) {
			return $return;
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
			$return[] = $signatureToShow;
		}
		return $return;
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
		}
		$this->settings['signMethod'] = $this->config->getAppValue(Application::APP_ID, 'sign_method', 'password');
		return $this->settings;
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

	public function formatFile(): array {
		$return = $this->getFile();

		if ($this->showSettings) {
			$return['settings'] = $this->getSettings();
		}
		return $return;
	}
}
