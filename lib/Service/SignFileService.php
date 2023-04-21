<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\DataObjects\VisibleElementAssoc;
use OCA\Libresign\Db\AccountFileMapper;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUser as FileUserEntity;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Event\SignedEvent;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\Pkcs12Handler;
use OCA\Libresign\Handler\Pkcs7Handler;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Helper\ValidateHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\File;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;
use Sabre\DAV\UUIDUtil;

class SignFileService {
	use TFile;

	/** @var IL10N */
	protected $l10n;
	/** @var FileMapper */
	private $fileMapper;
	/** @var FileUserMapper */
	private $fileUserMapper;
	/** @var AccountFileMapper */
	private $accountFileMapper;
	/** @var Pkcs7Handler */
	private $pkcs7Handler;
	/** @var Pkcs12Handler */
	private $pkcs12Handler;
	/** @var FolderService */
	protected $folderService;
	/** @var IClientService */
	private $client;
	/** @var IUserManager */
	private $userManager;
	/** @var LoggerInterface */
	protected $logger;
	/** @var IConfig */
	private $config;
	/** @var ValidateHelper */
	protected $validateHelper;
	/** @var IRootFolder */
	private $root;
	/** @var FileElementMapper */
	private $fileElementMapper;
	/** @var UserElementMapper */
	private $userElementMapper;
	/** @var IEventDispatcher */
	private $eventDispatcher;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var SignMethodService */
	private $signMethod;
	/** @var IdentifyMethodService */
	private $identifyMethod;
	/** @var IdentifyMethodMapper */
	private $identifyMethodMapper;
	/** @var IMimeTypeDetector */
	protected $mimeTypeDetector;
	/** @var ITempManager */
	private $tempManager;
	/** @var FileUserEntity */
	private $fileUser;
	/** @var string */
	private $password;
	/** @var FileEntity */
	private $libreSignFile;
	/** @var VisibleElementAssoc[] */
	private $elements = [];
	/** @var bool */
	private $signWithoutPassword = false;

	public function __construct(
		IL10N $l10n,
		FileMapper $fileMapper,
		FileUserMapper $fileUserMapper,
		AccountFileMapper $accountFileMapper,
		Pkcs7Handler $pkcs7Handler,
		Pkcs12Handler $pkcs12Handler,
		FolderService $folderService,
		IClientService $client,
		IUserManager $userManager,
		LoggerInterface $logger,
		IConfig $config,
		ValidateHelper $validateHelper,
		IRootFolder $root,
		FileElementMapper $fileElementMapper,
		UserElementMapper $userElementMapper,
		IEventDispatcher $eventDispatcher,
		IURLGenerator $urlGenerator,
		SignMethodService $signMethod,
		IdentifyMethodService $identifyMethod,
		IdentifyMethodMapper $identifyMethodMapper,
		IMimeTypeDetector $mimeTypeDetector,
		ITempManager $tempManager
	) {
		$this->l10n = $l10n;
		$this->fileMapper = $fileMapper;
		$this->fileUserMapper = $fileUserMapper;
		$this->accountFileMapper = $accountFileMapper;
		$this->pkcs7Handler = $pkcs7Handler;
		$this->pkcs12Handler = $pkcs12Handler;
		$this->folderService = $folderService;
		$this->client = $client;
		$this->userManager = $userManager;
		$this->logger = $logger;
		$this->config = $config;
		$this->validateHelper = $validateHelper;
		$this->root = $root;
		$this->fileElementMapper = $fileElementMapper;
		$this->userElementMapper = $userElementMapper;
		$this->eventDispatcher = $eventDispatcher;
		$this->urlGenerator = $urlGenerator;
		$this->signMethod = $signMethod;
		$this->identifyMethod = $identifyMethod;
		$this->identifyMethodMapper = $identifyMethodMapper;
		$this->mimeTypeDetector = $mimeTypeDetector;
		$this->tempManager = $tempManager;
	}

	/**
	 * Can delete sing request
	 *
	 * @param array $data
	 */
	public function canDeleteRequestSignature(array $data): void {
		if (!empty($data['uuid'])) {
			$signatures = $this->fileUserMapper->getByFileUuid($data['uuid']);
		} elseif (!empty($data['file']['fileId'])) {
			$signatures = $this->fileUserMapper->getByNodeId($data['file']['fileId']);
		} else {
			throw new \Exception($this->l10n->t('Inform or UUID or a File object'));
		}
		$signed = array_filter($signatures, fn ($s) => $s->getSigned());
		if ($signed) {
			throw new \Exception($this->l10n->t('Document already signed'));
		}
		array_walk($data['users'], function ($user) use ($signatures) {
			$exists = array_filter($signatures, fn ($s) => $s->getEmail() === $user['email']);
			if (!$exists) {
				throw new \Exception($this->l10n->t('No signature was requested to %s', $user['email']));
			}
		});
	}

	/**
	 * @psalm-suppress MixedReturnStatement
	 * @psalm-suppress MixedMethodCall
	 */
	public function notifyCallback(File $file): void {
		$uri = $this->libreSignFile->getCallback();
		if (!$uri) {
			$uri = $this->config->getAppValue(Application::APP_ID, 'webhook_sign_url');
			if (!$uri) {
				return;
			}
		}
		$options = [
			'multipart' => [
				[
					'name' => 'uuid',
					'contents' => $this->libreSignFile->getUuid(),
				],
				[
					'name' => 'status',
					'contents' => $this->libreSignFile->getStatus(),
				],
				[
					'name' => 'file',
					'contents' => $file->fopen('r'),
					'filename' => $file->getName()
				]
			]
		];
		$this->client->newClient()->post($uri, $options);
	}

	/**
	 * @return static
	 */
	public function setLibreSignFile(FileEntity $libreSignFile): self {
		$this->libreSignFile = $libreSignFile;
		return $this;
	}

	/**
	 * @return static
	 */
	public function setFileUser(FileUserEntity $fileUser): self {
		$this->fileUser = $fileUser;
		return $this;
	}

	/**
	 * @return static
	 */
	public function setSignWithoutPassword(bool $signWithoutPassword): self {
		$this->signWithoutPassword = $signWithoutPassword;
		return $this;
	}

	/**
	 * @return static
	 */
	public function setPassword(?string $password = null): self {
		$this->password = $password;
		return $this;
	}

	/**
	 * @return static
	 */
	public function setVisibleElements(array $list): self {
		$fileElements = $this->fileElementMapper->getByFileIdAndUserId($this->fileUser->getFileId(), $this->fileUser->getUserId());
		foreach ($fileElements as $fileElement) {
			$element = array_filter($list, function (array $element) use ($fileElement): bool {
				return $element['documentElementId'] === $fileElement->getId();
			});
			if ($element) {
				$c = current($element);
				$userElement = $this->userElementMapper->findOne(['id' => $c['profileElementId']]);
			} else {
				$userElement = $this->userElementMapper->findOne([
					'user_id' => $this->fileUser->getUserId(),
					'type' => $fileElement->getType(),
				]);
			}
			try {
				/** @var \OCP\Files\File[] */
				$node = $this->root->getById($userElement->getFileId());
				if (!$node) {
					throw new \Exception('empty');
				}
				$node = $node[0];
			} catch (\Throwable $th) {
				throw new LibresignException($this->l10n->t('You need to define a visible signature or initials to sign this document.'));
			}
			$tempFile = $this->tempManager->getTemporaryFile('.png');
			file_put_contents($tempFile, $node->getContent());
			$visibleElements = new VisibleElementAssoc(
				$fileElement,
				$userElement,
				$tempFile
			);
			$this->elements[] = $visibleElements;
		}
		return $this;
	}

	public function sign(): File {
		$fileToSign = $this->getFileToSing($this->libreSignFile);
		$pfxFile = $this->getPfxFile();
		switch ($fileToSign->getExtension()) {
			case 'pdf':
				$signedFile = $this->pkcs12Handler
					->setInputFile($fileToSign)
					->setCertificate($pfxFile)
					->setVisibleElements($this->elements)
					->setPassword($this->password)
					->sign();
				break;
			default:
				$signedFile = $this->pkcs7Handler
					->setInputFile($fileToSign)
					->setCertificate($pfxFile)
					->setPassword($this->password)
					->sign();
		}

		$this->fileUser->setSigned(time());
		if ($this->fileUser->getId()) {
			$this->fileUserMapper->update($this->fileUser);
		} else {
			$this->fileUserMapper->insert($this->fileUser);
		}

		$this->libreSignFile->setSignedNodeId($signedFile->getId());
		$allSigned = $this->updateStatus();
		$this->fileMapper->update($this->libreSignFile);

		$this->eventDispatcher->dispatchTyped(new SignedEvent($this, $signedFile, $allSigned));

		return $signedFile;
	}

	public function storeUserMetadata(array $metadata = []): self {
		$collectMetadata = $this->config->getAppValue(Application::APP_ID, 'collect_metadata') ? true : false;
		if (!$collectMetadata || !$metadata) {
			return $this;
		}
		$this->fileUser->setMetadata($metadata);
		$this->fileUserMapper->update($this->fileUser);
		return $this;
	}

	private function updateStatus(): bool {
		$signers = $this->fileUserMapper->getByFileId($this->fileUser->getFileId());
		$total = array_reduce($signers, function ($carry, $signer) {
			$carry += $signer->getSigned() ? 1 : 0;
			return $carry;
		});
		if (count($signers) === $total && $this->libreSignFile->getStatus() !== FileEntity::STATUS_SIGNED) {
			$this->libreSignFile->setStatus(FileEntity::STATUS_SIGNED);
			return true;
		}
		return false;
	}

	private function getPfxFile(): \OCP\Files\Node {
		if ($this->signWithoutPassword) {
			$tempPassword = sha1(time());
			$this->setPassword($tempPassword);
			return $this->pkcs12Handler->generateCertificate(
				['email' => $this->fileUser->getEmail()],
				$tempPassword,
				$this->fileUser->getUserId(),
				true
			);
		}
		return $this->pkcs12Handler->getPfx($this->fileUser->getUserId());
	}

	/**
	 * Get file to sign
	 *
	 * @throws LibresignException
	 * @param FileEntity $libresignFile
	 * @return \OCP\Files\Node
	 */
	public function getFileToSing(FileEntity $libresignFile): \OCP\Files\Node {
		$userFolder = $this->root->getUserFolder($libresignFile->getUserId());
		$originalFile = $userFolder->getById($libresignFile->getNodeId());
		if (count($originalFile) < 1) {
			throw new LibresignException($this->l10n->t('File not found'));
		}
		$originalFile = $originalFile[0];
		if ($originalFile->getExtension() === 'pdf') {
			return $this->getPdfToSign($libresignFile, $originalFile);
		}
		return $userFolder->get($originalFile);
	}

	public function getLibresignFile(?int $fileId, ?string $fileUserUuid): FileEntity {
		try {
			if ($fileId) {
				$libresignFile = $this->fileMapper->getByFileId($fileId);
			} elseif ($fileUserUuid) {
				$fileUser = $this->fileUserMapper->getByUuid($fileUserUuid);
				$libresignFile = $this->fileMapper->getById($fileUser->getFileId());
			} else {
				throw new \Exception('Invalid arguments');
			}
		} catch (DoesNotExistException $th) {
			throw new LibresignException($this->l10n->t('File not found'), 1);
		}
		return $libresignFile;
	}

	public function requestCode(FileUserEntity $fileUser, IUser $user): string {
		return $this->signMethod->requestCode($fileUser, $user);
	}

	public function getFileUserToSign(FileEntity $libresignFile, IUser $user): FileUserEntity {
		$this->validateHelper->fileCanBeSigned($libresignFile);
		try {
			$fileUser = $this->fileUserMapper->getByFileIdAndUserId($libresignFile->getNodeId(), $user->getUID());
			if ($fileUser->getSigned()) {
				throw new LibresignException($this->l10n->t('File already signed by you'), 1);
			}
		} catch (DoesNotExistException $th) {
			try {
				$accountFile = $this->accountFileMapper->getByFileId($libresignFile->getId());
			} catch (\Throwable $th) {
				throw new LibresignException($this->l10n->t('Invalid data to sign file'), 1);
			}
			$this->validateHelper->userCanApproveValidationDocuments($user);
			$fileUser = new FileUserEntity();
			$fileUser->setFileId($libresignFile->getId());
			$fileUser->setEmail($user->getEMailAddress());
			$fileUser->setDisplayName($user->getDisplayName());
			$fileUser->setUserId($user->getUID());
			$fileUser->setUuid(UUIDUtil::getUUID());
			$fileUser->setCreatedAt(time());
		}
		return $fileUser;
	}

	/**
	 * @psalm-suppress MixedReturnStatement
	 * @psalm-suppress InvalidReturnStatement
	 * @psalm-suppress MixedMethodCall
	 *
	 * @return File
	 */
	private function getPdfToSign(FileEntity $fileData, File $originalFile): File {
		if ($fileData->getSignedNodeId()) {
			/** @var \OCP\Files\File */
			$fileToSign = $this->root->getById($fileData->getSignedNodeId())[0];
		} else {
			$signedFilePath = preg_replace(
				'/' . $originalFile->getExtension() . '$/',
				$this->l10n->t('signed') . '.' . $originalFile->getExtension(),
				$originalFile->getPath()
			);

			/** @var \OCP\Files\File */
			$buffer = $this->pkcs12Handler->writeFooter($originalFile, $fileData->getUuid());
			if (!$buffer) {
				$buffer = $originalFile->getContent($originalFile);
			}
			/** @var \OCP\Files\File */
			$fileToSign = $this->root->newFile($signedFilePath);
			$fileToSign->putContent($buffer);
		}
		return $fileToSign;
	}

	/**
	 * @return (array|int|mixed)[]
	 * @psalm-return array{action?: int, user?: array{name: mixed}, sign?: array{pdf: array{file?: File, nodeId?: mixed, url?: mixed, base64?: string}|null, uuid: mixed, filename: mixed, description: mixed}, errors?: non-empty-list<mixed>, redirect?: mixed, settings?: array{accountHash: string}}
	 */
	public function getInfoOfFileToSignUsingFileUserUuid(?string $uuid, ?IUser $user, string $formatOfPdfOnSign): array {
		$return = [];
		if (!$uuid) {
			return $return;
		}
		try {
			$fileUser = $this->fileUserMapper->getByUuid($uuid);
			$fileEntity = $this->fileMapper->getById($fileUser->getFileId());
		} catch (DoesNotExistException $e) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [$this->l10n->t('Invalid UUID')],
			]));
		}
		$this->trhowIfCantIdentifyUser($uuid, $user, $fileUser);
		$this->throwIfUserIsNotSigner($user, $fileUser);
		$this->throwIfAlreadySigned($fileEntity, $fileUser);
		$this->throwIfInvalidUser($uuid, $fileUser, $user);
		$userFolder = $this->root->getUserFolder($fileEntity->getUserId());
		$fileToSign = $userFolder->getById($fileEntity->getNodeId());
		if (count($fileToSign) < 1) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [$this->l10n->t('File not found')],
			]));
		}
		/** @var File */
		$fileToSign = $fileToSign[0];
		$return = $this->getFileData($fileEntity, $user, $fileUser);
		$return['sign']['pdf'] = $this->getFileUrl($formatOfPdfOnSign, $fileEntity, $fileToSign, $uuid);
		return $return;
	}

	public function getInfoOfFileToSignUsingFileUuid(?string $uuid, ?IUser $user, string $formatOfPdfOnSign): array {
		$return = [];
		if (!$uuid) {
			return $return;
		}
		try {
			$fileEntity = $this->fileMapper->getByUuid($uuid);
			$this->accountFileMapper->getByFileId($fileEntity->getId());
		} catch (DoesNotExistException $e) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [$this->l10n->t('Invalid UUID')],
			]));
		}
		$this->throwIfAlreadySigned($fileEntity);
		try {
			$this->validateHelper->userCanApproveValidationDocuments($user);
		} catch (LibresignException $e) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [$e->getMessage()],
			]));
		}
		$userFolder = $this->root->getUserFolder($fileEntity->getUserId());
		$fileToSign = $userFolder->getById($fileEntity->getNodeId());
		if (count($fileToSign) < 1) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [$this->l10n->t('File not found')],
			]));
		}
		/** @var File */
		$fileToSign = $fileToSign[0];
		$return = $this->getFileData($fileEntity, $user);
		$return['sign']['pdf'] = $this->getFileUrl($formatOfPdfOnSign, $fileEntity, $fileToSign, $uuid);
		return $return;
	}

	private function throwIfInvalidUser(string $uuid, FileUserEntity $fileUser, ?IUser $user): void {
		$identifyMethods = $this->identifyMethodMapper->getIdentifyMethodsFromFileUserId($fileUser->getId());
		$nextcloudIdentifyMethod = array_filter($identifyMethods, function(IdentifyMethod $identifyMethod): bool {
			return $identifyMethod->getMethod() === IdentifyMethodService::IDENTIFTY_NEXTCLOUD;
		});
		if (!count($nextcloudIdentifyMethod)) {
			return;
		}
		if (!$user) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_REDIRECT,
				'errors' => [$this->l10n->t('You are not logged in. Please log in.')],
				'redirect' => $this->urlGenerator->linkToRoute('core.login.showLoginForm', [
					'redirect_url' => $this->urlGenerator->linkToRoute(
						'libresign.page.sign',
						['uuid' => $uuid]
					),
				]),
			]));
		}
	}

	private function throwIfAlreadySigned(FileEntity $fileEntity, ?FileUserEntity $fileUser = null): void {
		if ($fileEntity->getStatus() === FileEntity::STATUS_SIGNED
			|| (!is_null($fileUser) && $fileUser->getSigned())
		) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_SHOW_ERROR,
				'errors' => [$this->l10n->t('File already signed.')],
				'uuid' => $fileEntity->getUuid(),
			]));
		}
	}

	private function trhowIfCantIdentifyUser(string $uuid, ?IUser $user, ?FileUserEntity $fileUser): void {
		if ($fileUser instanceof FileUserEntity) {
			$fileUserId = $fileUser->getUserId();
			if ($fileUserId) {
				return;
			}
		}
		if ($user) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [$this->l10n->t('This is not your file')],
			]));
		}
		$email = $fileUser->getEmail();
		if ($this->userManager->getByEmail($email)) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_REDIRECT,
				'errors' => [$this->l10n->t('User already exists. Please login.')],
				'redirect' => $this->urlGenerator->linkToRoute('core.login.showLoginForm', [
					'redirect_url' => $this->urlGenerator->linkToRoute(
						'libresign.page.sign',
						['uuid' => $uuid]
					),
				]),
			]));
		}
		throw new LibresignException(json_encode([
			'action' => JSActions::ACTION_CREATE_USER,
			'settings' => ['accountHash' => md5($email)],
		]));
	}

	private function throwIfUserIsNotSigner(?IUser $user, FileUserEntity $fileUser): void {
		if ($user && $fileUser->getUserId() !== $user->getUID()) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [$this->l10n->t('Invalid user')],
			]));
		}
	}

	private function getFileData(FileEntity $fileData, ?IUser $user, ?FileUserEntity $fileUser = null): array {
		$return['action'] = JSActions::ACTION_SIGN;
		$return['sign'] = [
			'uuid' => $fileData->getUuid(),
			'filename' => $fileData->getName()
		];
		if ($fileUser) {
			$return['user']['name'] = $fileUser->getDisplayName();
			$return['sign']['description'] = $fileUser->getDescription();
			$return['settings']['identifyMethods'] = $this->identifyMethodMapper->getIdentifyMethodsFromFileUserId($fileUser->getId());
		} else {
			$return['user']['name'] = $user->getDisplayName();
		}
		return $return;
	}

	/**
	 * @return array
	 *
	 * @psalm-return array{file?: File, nodeId?: int, url?: string, base64?: string}
	 */
	private function getFileUrl(string $format, FileEntity $fileEntity, File $fileToSign, string $uuid): array {
		$url = [];
		switch ($format) {
			case 'base64':
				$url = ['base64' => base64_encode($fileToSign->getContent())];
				break;
			case 'url':
				try {
					$this->accountFileMapper->getByFileId($fileEntity->getId());
					$url = ['url' => $this->urlGenerator->linkToRoute('libresign.page.getPdf', ['uuid' => $uuid])];
				} catch (DoesNotExistException $e) {
					$url = ['url' => $this->urlGenerator->linkToRoute('libresign.page.getPdfUser', ['uuid' => $uuid])];
				}
				break;
			case 'nodeId':
				$url = ['nodeId' => $fileToSign->getId()];
				break;
			case 'file':
				$url = ['file' => $fileToSign];
				break;
		}
		return $url;
	}
}
