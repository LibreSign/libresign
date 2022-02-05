<?php

namespace OCA\Libresign\Service;

use OC\AppFramework\Utility\TimeFactory;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\AccountFileMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileTypeMapper;
use OCA\Libresign\Db\FileUser;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Db\UserElement;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\Pkcs12Handler;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Settings\Mailer\NewUserMailHelper;
use OCP\Accounts\IAccountManager;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use Sabre\DAV\UUIDUtil;
use Throwable;

class AccountService {
	/** @var IL10N */
	private $l10n;
	/** @var FileUserMapper */
	private $fileUserMapper;
	/** @var FileUser */
	private $fileUser;
	/** @var IUserManager */
	protected $userManager;
	/** @var IAccountManager */
	private $accountManager;
	/** @var IRootFolder */
	private $root;
	/** @var IConfig */
	private $config;
	/** @var NewUserMailHelper */
	private $newUserMail;
	/** @var ValidateHelper */
	private $validateHelper;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var Pkcs12Handler */
	private $pkcs12Handler;
	/** @var FileMapper */
	private $fileMapper;
	/** @var FileTypeMapper */
	private $fileTypeMapper;
	/** @var AccountFileMapper */
	private $accountFileMapper;
	/** @var SignFileService */
	private $signFileService;
	/** @var \OCA\Libresign\DbFile */
	private $fileData;
	/** @var \OCA\Files\Node\File */
	private $fileToSign;
	/** @var IGroupManager */
	private $groupManager;
	/** @var AccountFileService */
	private $accountFileService;
	/** @var UserElementMapper */
	private $userElementMapper;
	/** @var FolderService */
	private $folderService;
	/** @var IClientService */
	private $clientService;
	/** @var TimeFactory */
	private $timeFactory;

	public const ELEMENT_SIGN_WIDTH = 350;
	public const ELEMENT_SIGN_HEIGHT = 100;

	public function __construct(
		IL10N $l10n,
		FileUserMapper $fileUserMapper,
		IUserManager $userManager,
		IAccountManager $accountManager,
		IRootFolder $root,
		FileMapper $fileMapper,
		FileTypeMapper $fileTypeMapper,
		AccountFileMapper $accountFileMapper,
		SignFileService $signFileService,
		IConfig $config,
		NewUserMailHelper $newUserMail,
		ValidateHelper $validateHelper,
		IURLGenerator $urlGenerator,
		Pkcs12Handler $pkcs12Handler,
		IGroupManager $groupManager,
		AccountFileService $accountFileService,
		UserElementMapper $userElementMapper,
		FolderService $folderService,
		IClientService $clientService,
		TimeFactory $timeFactory
	) {
		$this->l10n = $l10n;
		$this->fileUserMapper = $fileUserMapper;
		$this->userManager = $userManager;
		$this->accountManager = $accountManager;
		$this->root = $root;
		$this->fileMapper = $fileMapper;
		$this->fileTypeMapper = $fileTypeMapper;
		$this->accountFileMapper = $accountFileMapper;
		$this->signFileService = $signFileService;
		$this->config = $config;
		$this->newUserMail = $newUserMail;
		$this->validateHelper = $validateHelper;
		$this->urlGenerator = $urlGenerator;
		$this->pkcs12Handler = $pkcs12Handler;
		$this->groupManager = $groupManager;
		$this->accountFileService = $accountFileService;
		$this->userElementMapper = $userElementMapper;
		$this->folderService = $folderService;
		$this->clientService = $clientService;
		$this->timeFactory = $timeFactory;
	}

	public function validateCreateToSign(array $data): void {
		if (!UUIDUtil::validateUUID($data['uuid'])) {
			throw new LibresignException($this->l10n->t('Invalid UUID'), 1);
		}
		try {
			$fileUser = $this->getFileUserByUuid($data['uuid']);
		} catch (\Throwable $th) {
			throw new LibresignException($this->l10n->t('UUID not found'), 1);
		}
		if ($fileUser->getEmail() !== $data['email']) {
			throw new LibresignException($this->l10n->t('This is not your file'), 1);
		}
		if ($this->userManager->userExists($data['email'])) {
			throw new LibresignException($this->l10n->t('User already exists'), 1);
		}
		if (empty($data['password'])) {
			throw new LibresignException($this->l10n->t('Password is mandatory'), 1);
		}
		$file = $this->getFileByUuid($data['uuid']);
		if (empty($file['fileToSign'])) {
			throw new LibresignException($this->l10n->t('File not found'));
		}
	}

	/**
	 * @return (\OCA\Files\Node\File|\OCA\Libresign\DbFile|\OCA\Libresign\Db\File|mixed)[]
	 *
	 * @psalm-return array{fileData: \OCA\Libresign\DbFile|\OCA\Libresign\Db\File, fileToSign: \OCA\Files\Node\File|mixed}
	 */
	public function getFileByUuid(string $uuid): array {
		$fileUser = $this->getFileUserByUuid($uuid);
		if (!$this->fileData) {
			$this->fileData = $this->fileMapper->getById($fileUser->getFileId());
			$userId = $this->fileData->getUserId();
			$userFolder = $this->root->getUserFolder($userId);
			$fileToSign = $userFolder->getById($this->fileData->getNodeId());
			if (count($fileToSign)) {
				$this->fileToSign = $fileToSign[0];
			}
		}
		return [
			'fileData' => $this->fileData,
			'fileToSign' => $this->fileToSign
		];
	}

	public function validateCertificateData(array $data): void {
		if (!$data['email']) {
			throw new LibresignException($this->l10n->t('You must have an email. You can define the email in your profile.'), 1);
		}
		if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
			throw new LibresignException($this->l10n->t('Invalid email'), 1);
		}
		if (empty($data['signPassword'])) {
			throw new LibresignException($this->l10n->t('Password to sign is mandatory'), 1);
		}
	}

	public function validateAccountFiles(array $files, IUser $user): void {
		foreach ($files as $fileIndex => $file) {
			$this->validateAccountFile($fileIndex, $file, $user);
		}
	}

	private function validateAccountFile(int $fileIndex, array $file, IUser $user): void {
		$profileFileTypes = $this->fileTypeMapper->getTypes();
		if (!array_key_exists($file['type'], $profileFileTypes)) {
			throw new LibresignException(json_encode([
				'type' => 'danger',
				'file' => $fileIndex,
				'message' => $this->l10n->t('Invalid file type.')
			]));
		}

		try {
			$this->validateHelper->validateFileTypeExists($file['type']);
			$this->validateHelper->validateNewFile($file);
			$this->validateHelper->validateUserHasNoFileWithThisType($user->getUID(), $file['type']);
		} catch (\Exception $e) {
			throw new LibresignException(json_encode([
				'type' => 'danger',
				'file' => $fileIndex,
				'message' => $e->getMessage()
			]));
		}
	}

	/**
	 * Get fileUser by Uuid
	 *
	 * @param string $uuid
	 * @return FileUser
	 */
	public function getFileUserByUuid($uuid): FileUser {
		if (!$this->fileUser) {
			$this->fileUser = $this->fileUserMapper->getByUuid($uuid);
		}
		return $this->fileUser;
	}

	/**
	 * @param null|string $signPassword
	 */
	public function createToSign(string $uuid, string $uid, string $password, ?string $signPassword): void {
		$fileUser = $this->getFileUserByUuid($uuid);

		$newUser = $this->userManager->createUser($uid, $password);
		$newUser->setDisplayName($fileUser->getDisplayName());
		$newUser->setEMailAddress($fileUser->getEmail());

		$fileUser->setUserId($newUser->getUID());
		$this->fileUserMapper->update($fileUser);

		if ($this->config->getAppValue('core', 'newUser.sendEmail', 'yes') === 'yes') {
			try {
				$emailTemplate = $this->newUserMail->generateTemplate($newUser, false);
				$this->newUserMail->sendMail($newUser, $emailTemplate);
			} catch (\Exception $e) {
				throw new LibresignException('Unable to send the invitation', 1);
			}
		}

		if ($signPassword) {
			$this->pkcs12Handler->generateCertificate($uid, $signPassword, $newUser->getUID());
		}
	}

	/**
	 * @param string $formatOfPdfOnSign (base64,url,file)
	 * @return (array|int|mixed)[]
	 * @psalm-return array{action?: int, user?: array{name: mixed}, sign?: array{pdf: mixed, uuid: mixed, filename: mixed, description: mixed}, errors?: non-empty-list<mixed>, redirect?: mixed, settings: array{accountHash: string, hasSignatureFile: bool}}
	 */
	public function getConfig(?string $uuid, ?IUser $user, string $formatOfPdfOnSign): array {
		try {
			$info = $this->signFileService->getInfoOfFileToSign($uuid, $user, $formatOfPdfOnSign);
		} catch (LibresignException $e) {
			$info = json_decode($e->getMessage());
		}
		$info['settings']['hasSignatureFile'] = $this->hasSignatureFile($user);
		$info['settings']['phoneNumber'] = $this->getPhoneNumber($user);
		$info['settings']['signMethod'] = $this->config->getAppValue(Application::APP_ID, 'sign_method', 'password');
		return $info;
	}

	private function getPhoneNumber(?IUser $user) {
		if (!$user) {
			return '';
		}
		$userAccount = $this->accountManager->getAccount($user);
		return $userAccount->getProperty(IAccountManager::PROPERTY_PHONE)->getValue();
	}

	public function hasSignatureFile(?IUser $user = null): bool {
		if (!$user) {
			return false;
		}
		try {
			$this->pkcs12Handler->getPfx($user->getUID());
			return true;
		} catch (\Throwable $th) {
		}
		return false;
	}

	/**
	 * Get PDF node by UUID
	 *
	 * @psalm-suppress MixedReturnStatement
	 * @param string $uuid
	 * @throws Throwable
	 * @return \OCP\Files\File
	 */
	public function getPdfByUuid(string $uuid) {
		$fileData = $this->fileMapper->getByUuid($uuid);
		$userFolder = $this->root->getUserFolder($fileData->getUserId());

		$fileUser = $this->fileUserMapper->getByFileId($fileData->getId());
		$signedUsers = array_filter($fileUser, function ($row) {
			return !is_null($row->getSigned());
		});

		if (count($fileUser) === count($signedUsers)) {
			$file = $userFolder->getById($fileData->getSignedNodeId())[0];
		} else {
			$file = $userFolder->getById($fileData->getNodeId())[0];
		}
		return $file;
	}

	public function canRequestSign(?IUser $user = null): bool {
		if (!$user) {
			return false;
		}
		$authorized = json_decode($this->config->getAppValue(Application::APP_ID, 'webhook_authorized', '["admin"]'));
		if (empty($authorized)) {
			return false;
		}
		$userGroups = $this->groupManager->getUserGroupIds($user);
		if (!array_intersect($userGroups, $authorized)) {
			return false;
		}
		return true;
	}

	public function getSettings(?IUser $user = null): array {
		$return['canRequestSign'] = $this->canRequestSign($user);
		$return['hasSignatureFile'] = $this->hasSignatureFile($user);
		return $return;
	}

	public function addFilesToAccount(array $files, IUser $user): void {
		$this->validateAccountFiles($files, $user);
		foreach ($files as $fileData) {
			$dataToSave = $fileData;
			$dataToSave['userManager'] = $user;
			$dataToSave['name'] = $fileData['name'] ?? $fileData['type'];
			$file = $this->signFileService->saveFile($dataToSave);

			$this->accountFileService->addFile($file, $user, $fileData['type']);
		}
	}

	public function deleteFileFromAccount(int $nodeId, IUser $user): void {
		$this->validateHelper->validateAccountFileIsOwnedByUser($nodeId, $user->getUID());
		$accountFile = $this->accountFileMapper->getByUserIdAndNodeId($user->getUID(), $nodeId);
		$this->accountFileService->deleteFile($accountFile->getFileId(), $user->getUID());
	}

	public function saveVisibleElements(array $elements, IUser $user): void {
		foreach ($elements as $element) {
			$this->saveVisibleElement($element, $user);
		}
	}

	public function saveVisibleElement(array $data, IUser $user): void {
		if (isset($data['elementId'])) {
			$this->updateFileOfVisibleElement($data);
			$this->updateDataOfVisibleElement($data);
		} else {
			$file = $this->insertFileOfVisibleElement($data, $user);
			$this->insertVisibleElement($data, $user, $file);
		}
	}

	private function updateFileOfVisibleElement(array $data): void {
		if (!isset($data['file'])) {
			return;
		}
		$userElement = $this->userElementMapper->findOne(['id' => $data['elementId']]);
		$userFolder = $this->folderService->getFolder($userElement->getFileId());
		$file = $userFolder->getById($userElement->getFileId())[0];
		$file->putContent($this->getFileRaw($data));
	}

	private function updateDataOfVisibleElement(array $data): void {
		if (!isset($data['starred'])) {
			return;
		}
		$userElement = $this->userElementMapper->findOne(['id' => $data['elementId']]);
		$userElement->setStarred($data['starred'] ? 1 : 0);
		$this->userElementMapper->update($userElement);
	}

	private function insertFileOfVisibleElement(array $data, IUser $user): File {
		$userFolder = $this->folderService->getFolder();
		$folderName = $this->folderService->getFolderName($data, $user);
		if ($userFolder->nodeExists($folderName)) {
			throw new \Exception($this->l10n->t('File already exists'));
		}
		$folderToFile = $userFolder->newFolder($folderName);
		return $folderToFile->newFile(UUIDUtil::getUUID() . '.png', $this->getFileRaw($data));
	}

	private function insertVisibleElement(array $data, IUser $user, File $file) {
		$userElement = new UserElement();
		$userElement->setType($data['type']);
		$userElement->setFileId($file->getId());
		$userElement->setUserId($user->getUID());
		$userElement->setStarred(isset($data['starred']) && $data['starred'] ? 1 : 0);
		$userElement->setCreatedAt($this->timeFactory->getDateTime());
		$this->userElementMapper->insert($userElement);
	}

	/**
	 * @psalm-suppress MixedReturnStatement
	 * @psalm-suppress MixedMethodCall
	 *
	 * @return false|resource|string
	 */
	private function getFileRaw(array $data) {
		if (!empty($data['file']['url'])) {
			if (!filter_var($data['file']['url'], FILTER_VALIDATE_URL)) {
				throw new \Exception($this->l10n->t('Invalid URL file'));
			}
			$response = $this->clientService->newClient()->get($data['file']['url']);
			$contentType = $response->getHeader('Content-Type');
			if ($contentType !== 'image/png') {
				throw new \Exception($this->l10n->t('Visible element file must be png.'));
			}
			$content = $response->getBody();
			if (!$content) {
				throw new \Exception($this->l10n->t('Empty file'));
			}
			$this->validateHelper->validateBase64($content);
		} else {
			$content = base64_decode($data['file']['base64']);
		}
		return $content;
	}

	public function getUserElements($userId): array {
		$elements = $this->userElementMapper->findMany(['user_id' => $userId]);
		$return = [];
		foreach ($elements as $key => $element) {
			$exists = $this->signatureFileExists($element);
			if (!$exists) {
				continue;
			}
			$return[] = [
				'id' => $element->getId(),
				'type' => $element->getType(),
				'file' => [
					'url' => $this->urlGenerator->linkToRoute('core.Preview.getPreviewByFileId', ['fileId' => $element->getFileId(), 'x' => self::ELEMENT_SIGN_WIDTH, 'y' => self::ELEMENT_SIGN_HEIGHT]),
					'fileId' => $element->getFileId()
				],
				'uid' => $element->getUserId(),
				'starred' => $element->getStarred() ? 1 : 0,
				'createdAt' => $element->getCreatedAt()
			];
		}
		return $return;
	}

	private function signatureFileExists(UserElement $userElement): bool {
		try {
			$this->folderService->getFolder($userElement->getFileId());
		} catch (\Exception $e) {
			$this->userElementMapper->delete($userElement);
			return false;
		}
		return true;
	}

	public function getUserElementByElementId($userId, $elementId): array {
		$element = $this->userElementMapper->findOne(['element_id' => $elementId, 'user_id' => $userId]);
		$exists = $this->signatureFileExists($element);
		if (!$exists) {
			return [];
		}
		return [
			'id' => $element->getId(),
			'type' => $element->getType(),
			'file' => [
				'url' => $this->urlGenerator->linkToRoute('core.Preview.getPreviewByFileId', ['fileId' => $element->getFileId(), 'x' => self::ELEMENT_SIGN_WIDTH, 'y' => self::ELEMENT_SIGN_HEIGHT]),
				'fileId' => $element->getFileId()
			],
			'uid' => $element->getUserId(),
			'starred' => $element->getStarred() ? 1 : 0,
			'createdAt' => $element->getCreatedAt()
		];
	}

	public function deleteSignatureElement(string $userId, int $elementId) {
		$element = $this->userElementMapper->findOne(['element_id' => $elementId, 'user_id' => $userId]);
		$this->userElementMapper->delete($element);
	}
}
