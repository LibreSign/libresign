<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use InvalidArgumentException;
use OC\Files\Filesystem;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\AccountFileMapper;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileTypeMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Db\UserElement;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Exception\InvalidPasswordException;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\Handler as CertificateEngineHandler;
use OCA\Libresign\Handler\Pkcs12Handler;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Settings\Mailer\NewUserMailHelper;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use Sabre\DAV\UUIDUtil;
use Throwable;

class AccountService {
	private ?SignRequest $signRequest = null;
	private ?\OCA\Libresign\Db\File $fileData = null;
	private \OCP\Files\File $fileToSign;

	public function __construct(
		private IL10N $l10n,
		private SignRequestMapper $signRequestMapper,
		private IUserManager $userManager,
		private IAccountManager $accountManager,
		private IRootFolder $root,
		private IUserMountCache $userMountCache,
		private IMimeTypeDetector $mimeTypeDetector,
		private FileMapper $fileMapper,
		private FileTypeMapper $fileTypeMapper,
		private AccountFileMapper $accountFileMapper,
		private SignFileService $signFileService,
		private RequestSignatureService $requestSignatureService,
		private CertificateEngineHandler $certificateEngineHandler,
		private IConfig $config,
		private IAppConfig $appConfig,
		private IMountProviderCollection $mountProviderCollection,
		private NewUserMailHelper $newUserMail,
		private IdentifyMethodService $identifyMethodService,
		private ValidateHelper $validateHelper,
		private IURLGenerator $urlGenerator,
		private Pkcs12Handler $pkcs12Handler,
		private IGroupManager $groupManager,
		private AccountFileService $accountFileService,
		private SignerElementsService $signerElementsService,
		private UserElementMapper $userElementMapper,
		private FolderService $folderService,
		private IClientService $clientService,
		private ITimeFactory $timeFactory,
	) {
	}

	public function validateCreateToSign(array $data): void {
		if (!UUIDUtil::validateUUID($data['uuid'])) {
			throw new LibresignException($this->l10n->t('Invalid UUID'), 1);
		}
		try {
			$signRequest = $this->getSignRequestByUuid($data['uuid']);
		} catch (\Throwable $th) {
			throw new LibresignException($this->l10n->t('UUID not found'), 1);
		}
		$identifyMethods = $this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signRequest->getId());
		if (!array_key_exists('identify', $data['user'])) {
			throw new LibresignException($this->l10n->t('Invalid identification method'), 1);
		}
		foreach ($data['user']['identify'] as $method => $value) {
			if (!array_key_exists($method, $identifyMethods)) {
				throw new LibresignException($this->l10n->t('Invalid identification method'), 1);
			}
			foreach ($identifyMethods[$method] as $identifyMethod) {
				$identifyMethod->validateToCreateAccount($value);
			}
		}
		if (empty($data['password'])) {
			throw new LibresignException($this->l10n->t('Password is mandatory'), 1);
		}
		$file = $this->getFileByUuid($data['uuid']);
		if (empty($file['fileToSign'])) {
			throw new LibresignException($this->l10n->t('File not found'));
		}
	}

	public function getFileByUuid(string $uuid): array {
		$signRequest = $this->getSignRequestByUuid($uuid);
		if (!$this->fileData instanceof \OCA\Libresign\Db\File) {
			$this->fileData = $this->fileMapper->getById($signRequest->getFileId());

			$nodeId = $this->fileData->getNodeId();

			$mountsContainingFile = $this->userMountCache->getMountsForFileId($nodeId);
			foreach ($mountsContainingFile as $fileInfo) {
				$this->root->getByIdInPath($nodeId, $fileInfo->getMountPoint());
			}
			$fileToSign = $this->root->getById($nodeId);
			if (count($fileToSign)) {
				$this->fileToSign = current($fileToSign);
			}
		}
		return [
			'fileData' => $this->fileData,
			'fileToSign' => $this->fileToSign
		];
	}

	public function validateCertificateData(array $data): void {
		if (array_key_exists('email', $data['user']) && empty($data['user']['email'])) {
			throw new LibresignException($this->l10n->t('You must have an email. You can define the email in your profile.'), 1);
		}
		if (!empty($data['user']['email']) && !filter_var($data['user']['email'], FILTER_VALIDATE_EMAIL)) {
			throw new LibresignException($this->l10n->t('Invalid email'), 1);
		}
		if (empty($data['signPassword'])) {
			throw new LibresignException($this->l10n->t('Password to sign is mandatory'), 1);
		}
	}

	/**
	 * Get signRequest by Uuid
	 */
	public function getSignRequestByUuid(string $uuid): SignRequest {
		if (!$this->signRequest instanceof SignRequest) {
			$this->signRequest = $this->signRequestMapper->getByUuid($uuid);
		}
		return $this->signRequest;
	}

	public function createToSign(string $uuid, string $email, string $password, ?string $signPassword): void {
		$signRequest = $this->getSignRequestByUuid($uuid);

		$newUser = $this->userManager->createUser($email, $password);
		$newUser->setDisplayName($signRequest->getDisplayName());
		$newUser->setSystemEMailAddress($email);

		// @todo implement this logic, the follow code is complex and dont work
		// $identifyMethods = $this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signRequest->getId());
		// foreach ($identifyMethods as $name => $identifyMethod) {
		// 	if ($name === IdentifyMethodService::IDENTIFY_ACCOUNT) {
		// 		$entity = $identifyMethod->getEntity();
		// 		if ($entity->getIdentifierKey() === IdentifyMethodService::IDENTIFY_ACCOUNT) {
		// 			$identifyMethod->getEntity()->setIdentifierValue($newUser->getUID());
		// 			$this->identifyMethodService->save($signRequest, false);
		// 		}
		// 	}
		// }

		if ($this->config->getAppValue('core', 'newUser.sendEmail', 'yes') === 'yes') {
			try {
				$emailTemplate = $this->newUserMail->generateTemplate($newUser, false);
				$this->newUserMail->sendMail($newUser, $emailTemplate);
			} catch (\Exception $e) {
				throw new LibresignException('Unable to send the invitation', 1);
			}
		}

		if ($signPassword) {
			$certificate = $this->pkcs12Handler->generateCertificate(
				[
					'host' => $newUser->getPrimaryEMailAddress(),
					'uid' => 'account:' . $newUser->getUID(),
					'name' => $newUser->getDisplayName()
				],
				$signPassword,
				$newUser->getDisplayName()
			);
			$this->pkcs12Handler->savePfx($newUser->getPrimaryEMailAddress(), $certificate);
		}
	}

	public function getCertificateEngineName(): string {
		return $this->certificateEngineHandler->getEngine()->getName();
	}

	/**
	 * @return array[]
	 */
	public function getConfig(?IUser $user = null): array {
		$info['identificationDocumentsFlow'] = $this->appConfig->getValueBool(Application::APP_ID, 'identification_documents', false);
		$info['hasSignatureFile'] = $this->hasSignatureFile($user);
		$info['phoneNumber'] = $this->getPhoneNumber($user);
		$info['isApprover'] = $this->validateHelper->userCanApproveValidationDocuments($user, false);
		return $info;
	}

	private function getPhoneNumber(?IUser $user): string {
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
	 * @throws Throwable
	 * @return \OCP\Files\File
	 */
	public function getPdfByUuid(string $uuid): File {
		$fileData = $this->fileMapper->getByUuid($uuid);

		if (in_array($fileData->getStatus(), [FileEntity::STATUS_PARTIAL_SIGNED, FileEntity::STATUS_SIGNED])) {
			$nodeId = $fileData->getSignedNodeId();
		} else {
			$nodeId = $fileData->getNodeId();
		}
		$mountsContainingFile = $this->userMountCache->getMountsForFileId($nodeId);
		foreach ($mountsContainingFile as $fileInfo) {
			$nodes = $this->root->getByIdInPath($nodeId, $fileInfo->getMountPoint());
		}
		$nodes = $this->root->getById($nodeId);
		if (empty($nodes)) {
			throw new DoesNotExistException('Not found');
		}
		$file = current($nodes);
		if (!$file instanceof File) {
			throw new DoesNotExistException('Not found');
		}
		return $file;
	}

	public function getFileByNodeIdAndSessionId(int $nodeId, string $sessionId): File {
		$rootSignatureFolder = $this->folderService->getFolder();
		if (!$rootSignatureFolder->nodeExists($sessionId)) {
			try {
				return $this->folderService->getFileById($nodeId);
			} catch (NotFoundException $th) {
				throw new DoesNotExistException('Not found');
			}
		}
		try {
			return $this->folderService->getFileById($nodeId);
		} catch (NotFoundException $th) {
			throw new DoesNotExistException('Not found');
		}
	}

	public function canRequestSign(?IUser $user = null): bool {
		if (!$user) {
			return false;
		}
		$authorized = $this->appConfig->getValueArray(Application::APP_ID, 'groups_request_sign', ['admin']);
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

	public function saveVisibleElements(array $elements, string $sessionId, ?IUser $user): void {
		foreach ($elements as $element) {
			$this->saveVisibleElement($element, $sessionId, $user);
		}
	}

	public function saveVisibleElement(array $data, string $sessionId, ?IUser $user): void {
		if (isset($data['elementId'])) {
			$this->updateFileOfVisibleElement($data);
			$this->updateDataOfVisibleElement($data);
		} elseif ($user instanceof IUser) {
			$file = $this->saveFileOfVisibleElementUsingUser($data, $user);
			$this->insertVisibleElement($data, $user, $file);
		} else {
			$file = $this->saveFileOfVisibleElementUsingSession($data, $sessionId);
		}
	}

	private function updateFileOfVisibleElement(array $data): void {
		if (!isset($data['file'])) {
			return;
		}
		$userElement = $this->userElementMapper->findOne(['id' => $data['elementId']]);
		$file = $this->folderService->getFileById($userElement->getFileId());
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

	private function saveFileOfVisibleElementUsingUser(array $data, IUser $user): File {
		$rootSignatureFolder = $this->folderService->getFolder();
		$folderName = $this->folderService->getFolderName($data, $user->getUID());
		if ($rootSignatureFolder->nodeExists($folderName)) {
			throw new \Exception($this->l10n->t('File already exists'));
		}
		$folderToFile = $rootSignatureFolder->newFolder($folderName);
		return $folderToFile->newFile(UUIDUtil::getUUID() . '.png', $this->getFileRaw($data));
	}

	private function saveFileOfVisibleElementUsingSession(array $data, string $sessionId): File {
		if (!empty($data['nodeId'])) {
			return $this->updateFileOfVisibleElementUsingSession($data, $sessionId);
		}
		return $this->createFileOfVisibleElementUsingSession($data, $sessionId);
	}

	private function updateFileOfVisibleElementUsingSession(array $data, string $sessionId): File {
		$fileList = $this->signerElementsService->getElementsFromSession();
		$element = array_filter($fileList, function (File $element) use ($data) {
			return $element->getId() === $data['nodeId'];
		});
		$element = current($element);
		if (!$element instanceof File) {
			throw new \Exception($this->l10n->t('File not found'));
		}
		$element->putContent($this->getFileRaw($data));
		return $element;
	}

	private function createFileOfVisibleElementUsingSession(array $data, string $sessionId): File {
		$rootSignatureFolder = $this->folderService->getFolder();
		$folderName = $sessionId;
		if ($rootSignatureFolder->nodeExists($folderName)) {
			/** @var Folder $folderToFile */
			$folderToFile = $rootSignatureFolder->get($folderName);
		} else {
			/** @var Folder $folderToFile */
			$folderToFile = $rootSignatureFolder->newFolder($folderName);
		}
		$filename = implode(
			'_',
			[
				$data['type'],
				$this->timeFactory->getDateTime()->getTimestamp(),
			]
		) . '.png';
		return $folderToFile->newFile($filename, $this->getFileRaw($data));
	}

	private function insertVisibleElement(array $data, IUser $user, File $file): void {
		$userElement = new UserElement();
		$userElement->setType($data['type']);
		$userElement->setFileId($file->getId());
		$userElement->setUserId($user->getUID());
		$userElement->setStarred(isset($data['starred']) && $data['starred'] ? 1 : 0);
		$userElement->setCreatedAt($this->timeFactory->getDateTime());
		$this->userElementMapper->insert($userElement);
	}

	private function getFileRaw(array $data): string {
		if (!empty($data['file']['url'])) {
			if (!filter_var($data['file']['url'], FILTER_VALIDATE_URL)) {
				throw new \Exception($this->l10n->t('Invalid URL file'));
			}
			$response = $this->clientService->newClient()->get($data['file']['url']);
			$contentType = $response->getHeader('Content-Type');
			if ($contentType !== 'image/png') {
				throw new \Exception($this->l10n->t('Visible element file must be png.'));
			}
			$content = (string)$response->getBody();
			if (empty($content)) {
				throw new \Exception($this->l10n->t('Empty file'));
			}
			$this->validateHelper->validateBase64($content, ValidateHelper::TYPE_VISIBLE_ELEMENT_USER);
			return $content;
		}
		$this->validateHelper->validateBase64($data['file']['base64'], ValidateHelper::TYPE_VISIBLE_ELEMENT_USER);
		$withMime = explode(',', $data['file']['base64']);
		if (count($withMime) === 2) {
			$content = base64_decode($withMime[1]);
		} else {
			$content = base64_decode($data['file']['base64']);
		}
		if (!$content) {
			return '';
		}
		return $content;
	}

	public function deleteSignatureElement(?IUser $user, string $sessionId, int $nodeId): void {
		if ($user instanceof IUser) {
			$element = $this->userElementMapper->findOne([
				'file_id' => $nodeId,
				'user_id' => $user->getUID(),
			]);
			$this->userElementMapper->delete($element);
			try {
				$file = $this->folderService->getFileById($element->getFileId());
				$file->delete();
			} catch (NotFoundException $e) {
			}
		} else {
			$rootSignatureFolder = $this->folderService->getFolder();
			$folderName = $sessionId;
			if ($rootSignatureFolder->nodeExists($folderName)) {
				$rootSignatureFolder->delete($folderName);
			}
		}
	}

	/**
	 * @throws LibresignException at savePfx
	 * @throws InvalidArgumentException
	 */
	public function uploadPfx(array $file, IUser $user): void {
		if (
			$file['error'] !== 0 ||
			!is_uploaded_file($file['tmp_name']) ||
			Filesystem::isFileBlacklisted($file['tmp_name'])
		) {
			// TRANSLATORS Error when the uploaded certificate file is not valid
			throw new InvalidArgumentException($this->l10n->t('Invalid file provided. Need to be a .pfx file.'));
		}
		if ($file['size'] > 10 * 1024) {
			// TRANSLATORS Error when the certificate file is bigger than normal
			throw new InvalidArgumentException($this->l10n->t('File is too big'));
		}
		$content = file_get_contents($file['tmp_name']);
		$mimetype = $this->mimeTypeDetector->detectString($content);
		if ($mimetype !== 'application/octet-stream') {
			// TRANSLATORS Error when the mimetype of uploaded file is not valid
			throw new InvalidArgumentException($this->l10n->t('Invalid file provided. Need to be a .pfx file.'));
		}
		$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
		if ($extension !== 'pfx') {
			// TRANSLATORS Error when the certificate file is not a pfx file
			throw new InvalidArgumentException($this->l10n->t('Invalid file provided. Need to be a .pfx file.'));
		}
		unlink($file['tmp_name']);
		$this->pkcs12Handler->savePfx($user->getUID(), $content);
	}

	public function deletePfx(IUser $user): void {
		$this->pkcs12Handler->deletePfx($user->getUID());
	}

	/**
	 * @throws LibresignException when have not a certificate file
	 */
	public function updatePfxPassword(IUser $user, string $current, string $new): void {
		try {
			$pfx = $this->pkcs12Handler->updatePassword($user->getUID(), $current, $new);
		} catch (InvalidPasswordException $e) {
			throw new LibresignException($this->l10n->t('Invalid user or password'));
		}
	}

	/**
	 * @throws LibresignException when have not a certificate file
	 */
	public function readPfxData(IUser $user, string $password): array {
		try {
			return $this->pkcs12Handler->readCertificate($user->getUID(), $password);
		} catch (InvalidPasswordException $e) {
			throw new LibresignException($this->l10n->t('Invalid user or password'));
		}
	}
}
