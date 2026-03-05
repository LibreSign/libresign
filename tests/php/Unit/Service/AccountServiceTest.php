<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OC\AppFramework\Utility\TimeFactory;
use OC\Http\Client\ClientService;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileTypeMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Db\UserElement;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Helper\FileUploadHelper;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\IdDocsService;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\RequestSignatureService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Service\SignFileService;
use OCA\Settings\Mailer\NewUserMailHelper;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Config\IUserConfig;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @internal
 * @group DB
 */
final class AccountServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IL10N&MockObject $l10n;
	private SignRequestMapper&MockObject $signRequestMapper;
	private IUserManager&MockObject $userManager;
	private IAccountManager $accountManager;
	private IRootFolder&MockObject $root;
	private IMimeTypeDetector&MockObject $mimeTypeDetector;
	private FileMapper&MockObject $fileMapper;
	private FileTypeMapper&MockObject $fileTypeMapper;
	private SignFileService&MockObject $signFile;
	private CertificateEngineFactory&MockObject $certificateEngineFactory;
	private IAppConfig&MockObject $appConfig;
	private IUserConfig&MockObject $userConfig;
	private IMountProviderCollection&MockObject $mountProviderCollection;
	private NewUserMailHelper&MockObject $newUserMail;
	private IdentifyMethodService&MockObject $identifyMethodService;
	private IdentifyMethodMapper&MockObject $identifyMethodMapper;
	private ValidateHelper&MockObject $validateHelper;
	private IURLGenerator&MockObject $urlGenerator;
	private IGroupManager&MockObject $groupManager;
	private IdDocsService&MockObject $idDocsService;
	private SignerElementsService&MockObject $signerElementsService;
	private UserElementMapper&MockObject $userElementMapper;
	private FolderService&MockObject $folderService;
	private ClientService&MockObject $clientService;
	private TimeFactory&MockObject $timeFactory;
	private RequestSignatureService&MockObject $requestSignatureService;
	private Pkcs12Handler&MockObject $pkcs12Handler;
	private FileUploadHelper&MockObject $uploadHelper;

	public function setUp(): void {
		parent::setUp();
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->willReturnArgument(0);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->accountManager = $this->createMock(IAccountManager::class);
		$this->root = $this->createMock(IRootFolder::class);
		$this->mimeTypeDetector = $this->createMock(IMimeTypeDetector::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->fileTypeMapper = $this->createMock(FileTypeMapper::class);
		$this->signFile = $this->createMock(SignFileService::class);
		$this->requestSignatureService = $this->createMock(RequestSignatureService::class);
		$this->certificateEngineFactory = $this->createMock(CertificateEngineFactory::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->userConfig = $this->createMock(IUserConfig::class);
		$this->mountProviderCollection = $this->createMock(IMountProviderCollection::class);
		$this->newUserMail = $this->createMock(NewUserMailHelper::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->identifyMethodMapper = $this->createMock(IdentifyMethodMapper::class);
		$this->validateHelper = $this->createMock(ValidateHelper::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->pkcs12Handler = $this->createMock(Pkcs12Handler::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->idDocsService = $this->createMock(IdDocsService::class);
		$this->signerElementsService = $this->createMock(SignerElementsService::class);
		$this->userElementMapper = $this->createMock(UserElementMapper::class);
		$this->folderService = $this->createMock(FolderService::class);
		$this->clientService = $this->createMock(ClientService::class);
		$this->timeFactory = $this->createMock(TimeFactory::class);
		$this->uploadHelper = $this->createMock(FileUploadHelper::class);
	}

	private function getService(): AccountService {
		return new AccountService(
			$this->l10n,
			$this->signRequestMapper,
			$this->userManager,
			$this->accountManager,
			$this->root,
			$this->mimeTypeDetector,
			$this->fileMapper,
			$this->fileTypeMapper,
			$this->signFile,
			$this->requestSignatureService,
			$this->certificateEngineFactory,
			$this->appConfig,
			$this->userConfig,
			$this->mountProviderCollection,
			$this->newUserMail,
			$this->identifyMethodService,
			$this->identifyMethodMapper,
			$this->validateHelper,
			$this->urlGenerator,
			$this->pkcs12Handler,
			$this->groupManager,
			$this->idDocsService,
			$this->signerElementsService,
			$this->userElementMapper,
			$this->folderService,
			$this->clientService,
			$this->timeFactory,
			$this->uploadHelper
		);
	}

	#[DataProvider('provideValidateCertificateDataCases')]
	public function testValidateCertificateDataUsingDataProvider($arguments, $expectedErrorMessage):void {
		if (is_callable($arguments)) {
			$arguments = $arguments($this);
		}

		$this->expectExceptionMessage($expectedErrorMessage);
		$this->getService()->validateCertificateData($arguments);
	}

	public static function provideValidateCertificateDataCases():array {
		return [
			'emptyCertificateEmail' => [
				[
					'uuid' => '12345678-1234-1234-1234-123456789012',
					'user' => [
						'email' => '',
					],
				],
				'You must have an email. You can define the email in your profile.'
			],
			'invalidCertificateEmail' => [
				[
					'uuid' => '12345678-1234-1234-1234-123456789012',
					'user' => [
						'email' => 'invalid',
					],
				],
				'Invalid email'
			]
		];
	}

	public function testValidateCertificateDataWithSuccess():void {
		$signRequest = $this->createMock(SignRequest::class);
		$signRequest
			->method('__call')
			->with($this->equalTo('getEmail'), $this->anything())
			->willReturn('valid@test.coop');
		$this->signRequestMapper
			->method('getByUuid')
			->willReturn($signRequest);
		$actual = $this->getService()->validateCertificateData([
			'uuid' => '12345678-1234-1234-1234-123456789012',
			'user' => [
				'email' => 'valid@test.coop',
			],
			'password' => '123456789',
			'signPassword' => '123456',
		]);
		$this->assertNull($actual);
	}

	public function testCreateToSignWithErrorInSendingEmail():void {
		$signRequest = $this->createMock(\OCA\Libresign\Db\SignRequest::class);
		$signRequest
			->method('__call')
			->willReturnCallback(fn (string $method)
				=> match ($method) {
					'getDisplayName' => 'John Doe',
					'getId' => 1,
				}
			);
		$this->signRequestMapper->method('getByUuid')->willReturn($signRequest);
		$userToSign = $this->createMock(\OCP\IUser::class);
		$userToSign->method('getUID')->willReturn('username');
		$this->userManager->method('createUser')->willReturn($userToSign);
		$this->identifyMethodService->method('getIdentifyMethodsFromSignRequestId')->willReturn([]);
		$this->appConfig->method('getValueString')->willReturn('yes');
		$template = $this->createMock(\OCP\Mail\IEMailTemplate::class);
		$this->newUserMail->method('generateTemplate')->willReturn($template);
		$this->newUserMail->method('sendMail')->willReturnCallback(function ():void {
			throw new \Exception('Error Processing Request', 1);
		});
		$this->expectExceptionMessage('Unable to send the invitation');
		$this->getService()->createToSign('uuid', 'username', 'passwordOfUser', 'passwordToSign');
	}

	public function testGetPdfByUuidWithSuccessAndSignedFile():void {
		$libresignFile = $this->createMock(\OCA\Libresign\Db\File::class);
		$libresignFile->method('__call')
			->willReturnCallback(fn ($method)
				=> match ($method) {
					'getSignedNodeId' => 1,
					'getNodeId' => 1,
					'getStatus' => \OCA\Libresign\Enum\FileStatus::SIGNED->value,
				}
			);
		$this->fileMapper
			->method('getByUuid')
			->willReturn($libresignFile);
		$node = $this->createMock(\OCP\Files\File::class);
		$this->root
			->method('getUserFolder')
			->willReturn($this->root);
		$this->root
			->method('getFirstNodeById')
			->willReturn($node);

		$actual = $this->getService()->getPdfByUuid('uuid');
		$this->assertInstanceOf(\OCP\Files\File::class, $actual);
	}

	public function testGetPdfByUuidWithSuccessAndUnignedFile():void {
		$libresignFile = $this->createMock(\OCA\Libresign\Db\File::class);
		$libresignFile->method('__call')
			->willReturnCallback(fn ($method)
				=> match ($method) {
					'getSignedNodeId' => 1,
					'getNodeId' => 1,
					'getStatus' => \OCA\Libresign\Enum\FileStatus::SIGNED->value,
				}
			);
		$this->fileMapper
			->method('getByUuid')
			->willReturn($libresignFile);
		$node = $this->createMock(\OCP\Files\File::class);
		$this->root
			->method('getUserFolder')
			->willReturn($this->root);
		$this->root
			->method('getFirstNodeById')
			->willReturn($node);

		$actual = $this->getService()->getPdfByUuid('uuid');
		$this->assertInstanceOf(\OCP\Files\File::class, $actual);
	}

	public function testCanRequestSignWithUnexistentUser():void {
		$actual = $this->getService()->canRequestSign();
		$this->assertFalse($actual);
	}

	public function testCanRequestSignWithoutGroups():void {
		$this->appConfig
			->method('getValueString')
			->willReturn('');
		$user = $this->createMock(\OCP\IUser::class);
		$actual = $this->getService()->canRequestSign($user);
		$this->assertFalse($actual);
	}

	public function testCanRequestSignWithUserOutOfAuthorizedGroups():void {
		$this->appConfig
			->method('getValueArray')
			->willReturn(['admin']);
		$this->groupManager
			->method('getUserGroupIds')
			->willReturn([]);
		$user = $this->createMock(\OCP\IUser::class);
		$actual = $this->getService()->canRequestSign($user);
		$this->assertFalse($actual);
	}

	public function testCanRequestSignWithSuccess():void {
		$this->appConfig
			->method('getValueArray')
			->willReturn(['admin']);
		$this->groupManager
			->method('getUserGroupIds')
			->willReturn(['admin']);
		$user = $this->createMock(\OCP\IUser::class);
		$actual = $this->getService()->canRequestSign($user);
		$this->assertTrue($actual);
	}

	#[DataProvider('provideValidateCreateToSignCases')]
	public function testValidateCreateToSignUsingDataProvider($arguments, $expectedErrorMessage):void {
		if (is_callable($arguments)) {
			$arguments = $arguments($this);
		}

		$this->expectExceptionMessage($expectedErrorMessage);
		$this->getService()->validateCreateToSign($arguments);
	}

	public static function provideValidateCreateToSignCases():array {
		return [
			'invalidUuid' => [
				[
					'uuid' => 'invalid uuid'
				],
				'Invalid UUID'
			],
			'uuidNotFound' => [
				function ($self):array {
					$uuid = '12345678-1234-1234-1234-123456789012';
					$self->signRequestMapper = $self->createMock(SignRequestMapper::class);
					$self->signRequestMapper
						->method('getByUuid')
						->will($self->returnCallback(function ():void {
							throw new \Exception('Beep, beep, not found!', 1);
						}));
					return [
						'uuid' => $uuid
					];
				},
				'UUID not found'
			],
			'emailMismatch' => [
				function ($self):array {
					$signRequest = $self->createMock(SignRequest::class);
					$signRequest
						->method('__call')
						->willReturnCallback(fn (string $method)
							=> match ($method) {
								'getEmail' => 'valid@test.coop',
								'getId' => 10,
							}
						);
					$self->signRequestMapper
						->method('getByUuid')
						->will($self->returnValue($signRequest));
					$identifyMethod = $self->createMock(IIdentifyMethod::class);
					$identifyMethod
						->method('validateToCreateAccount')
						->willReturnCallback(function ():void {
							throw new \OCA\Libresign\Exception\LibresignException('This is not your file');
						});
					$self->identifyMethodService
						->method('getIdentifyMethodsFromSignRequestId')
						->willReturn(['email' => [$identifyMethod]]);
					return [
						'uuid' => '12345678-1234-1234-1234-123456789012',
						'user' => [
							'email' => 'invalid@test.coop',
							'identify' => [
								'email' => 'invalid@test.coop',
							],
						],
						'signPassword' => '132456789',
						'password' => '123456789',
					];
				},
				'This is not your file'
			],
			'userAlreadyExists' => [
				function ($self):array {
					$signRequest = $self->createMock(SignRequest::class);
					$signRequest
						->method('__call')
						->willReturnCallback(fn (string $method)
							=> match ($method) {
								'getEmail' => 'valid@test.coop',
								'getId' => 11,
							}
						);
					$self->signRequestMapper
						->method('getByUuid')
						->will($self->returnValue($signRequest));
					$identifyMethod = $self->createMock(IIdentifyMethod::class);
					$identifyMethod
						->method('validateToCreateAccount')
						->willReturnCallback(function ():void {
							throw new \OCA\Libresign\Exception\LibresignException('User already exists');
						});
					$self->identifyMethodService
						->method('getIdentifyMethodsFromSignRequestId')
						->willReturn(['email' => [$identifyMethod]]);
					return [
						'uuid' => '12345678-1234-1234-1234-123456789012',
						'user' => [
							'identify' => [
								'email' => 'valid@test.coop',
							],
						],
						'signPassword' => '123456789',
						'signPassword' => '123456789',
					];
				},
				'User already exists'
			],
			'emptyPassword' => [
				function ($self):array {
					$signRequest = $self->createMock(SignRequest::class);
					$signRequest
						->method('__call')
						->willReturnCallback(fn (string $method)
							=> match ($method) {
								'getEmail' => 'valid@test.coop',
								'getId' => 12,
							}
						);
					$self->signRequestMapper
						->method('getByUuid')
						->will($self->returnValue($signRequest));
					$identifyMethod = $self->createMock(IIdentifyMethod::class);
					$self->identifyMethodService
						->method('getIdentifyMethodsFromSignRequestId')
						->willReturn(['email' => [$identifyMethod]]);
					return [
						'uuid' => '12345678-1234-1234-1234-123456789012',
						'user' => [
							'identify' => [
								'email' => 'valid@test.coop',
							],
						],
						'signPassword' => '132456789',
						'password' => ''
					];
				},
				'Password is mandatory'
			],
			'fileNotFound' => [
				function ($self):array {
					$signRequest = $self->createMock(SignRequest::class);
					$signRequest
						->method('__call')
						->willReturnCallback(fn (string $method)
							=> match ($method) {
								'getEmail' => 'valid@test.coop',
								'getFileId' => 171,
								'getId' => 13,
								'getUserId' => 'username',
							}
						);
					$file = $self->createMock(\OCA\Libresign\Db\File::class);
					$file
						->method('__call')
						->willReturnCallback(fn (string $method)
							=> match ($method) {
								'getNodeId' => 999,
								'getUserId' => 'username',
							}
						);
					$self->fileMapper
						->method('getById')
						->will($self->returnValue($file));
					$self->signRequestMapper
						->method('getByUuid')
						->will($self->returnValue($signRequest));
					$identifyMethod = $self->createMock(IIdentifyMethod::class);
					$self->identifyMethodService
						->method('getIdentifyMethodsFromSignRequestId')
						->willReturn(['email' => [$identifyMethod]]);

					$self->root
						->method('getById')
						->will($self->returnValue([]));
					$folder = $self->createMock(\OCP\Files\Folder::class);
					$folder
						->method('getById')
						->willReturn([]);
					$self->root
						->method('getUserFolder')
						->willReturn($folder);
					return [
						'uuid' => '12345678-1234-1234-1234-123456789012',
						'user' => [
							'identify' => [
								'email' => 'valid@test.coop',
							],
						],
						'signPassword' => '132456789',
						'password' => '123456789'
					];
				},
				'File not found'
			],
		];
	}

	public function testDeleteSignatureElementWithUserDeletesFromDB(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('testuser');

		// Use real UserElement instead of mock since it uses magic methods
		$element = new UserElement();
		$element->setId(42);
		$element->setNodeId(123);

		$this->userElementMapper
			->expects($this->once())
			->method('findOne')
			->with([
				'node_id' => 123,
				'user_id' => 'testuser',
			])
			->willReturn($element);

		$this->userElementMapper
			->expects($this->once())
			->method('delete')
			->with($element);

		$file = $this->createMock(File::class);
		$file->expects($this->once())
			->method('delete');

		$this->folderService
			->expects($this->once())
			->method('getFileByNodeId')
			->with(123)
			->willReturn($file);

		$this->getService()->deleteSignatureElement($user, 'session123', 123);
	}

	public function testDeleteSignatureElementWithUserWhenFileNotFound(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('testuser');

		// Use real UserElement
		$element = new UserElement();
		$element->setNodeId(123);

		$this->userElementMapper
			->expects($this->once())
			->method('findOne')
			->willReturn($element);

		$this->userElementMapper
			->expects($this->once())
			->method('delete')
			->with($element);

		$this->folderService
			->expects($this->once())
			->method('getFileByNodeId')
			->willThrowException(new NotFoundException());

		// Should not throw, just skip file deletion
		$this->getService()->deleteSignatureElement($user, 'session123', 123);
	}

	public function testDeleteSignatureElementWithUserWhenFileDeleteFails(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('testuser');

		$element = new UserElement();
		$element->setNodeId(123);

		$this->userElementMapper
			->expects($this->once())
			->method('findOne')
			->willReturn($element);

		$this->userElementMapper
			->expects($this->once())
			->method('delete')
			->with($element);

		$file = $this->createMock(File::class);
		$file->expects($this->once())
			->method('delete')
			->willThrowException(new \Exception('storage error'));

		$this->folderService
			->expects($this->once())
			->method('getFileByNodeId')
			->with(123)
			->willReturn($file);

		// Should not throw, element deletion in DB must be enough
		$this->getService()->deleteSignatureElement($user, 'session123', 123);
	}

	public function testDeleteSignatureElementWithoutUserDeletesFromSession(): void {
		$sessionFolder = $this->createMock(Folder::class);
		$element = $this->createMock(File::class);

		$element->expects($this->once())
			->method('delete');

		$sessionFolder
			->expects($this->once())
			->method('getFirstNodeById')
			->with(456)
			->willReturn($element);

		// Session folder becomes empty after deletion
		$sessionFolder
			->expects($this->once())
			->method('getDirectoryListing')
			->willReturn([]);

		// Empty folder should be deleted too
		$sessionFolder
			->expects($this->once())
			->method('delete');

		$rootFolder = $this->createMock(Folder::class);
		$rootFolder
			->expects($this->once())
			->method('get')
			->with('session789')
			->willReturn($sessionFolder);

		$this->folderService
			->expects($this->once())
			->method('getFolder')
			->willReturn($rootFolder);

		$this->getService()->deleteSignatureElement(null, 'session789', 456);
	}

	public function testDeleteSignatureElementWithoutUserThrowsWhenSessionFolderNotFound(): void {
		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Element not found');

		$rootFolder = $this->createMock(Folder::class);
		$rootFolder
			->expects($this->once())
			->method('get')
			->with('nonexistent')
			->willThrowException(new NotFoundException());

		$this->folderService
			->expects($this->once())
			->method('getFolder')
			->willReturn($rootFolder);

		$this->getService()->deleteSignatureElement(null, 'nonexistent', 999);
	}

	public function testDeleteSignatureElementWithoutUserThrowsWhenNodeNotInSession(): void {
		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Element not found');

		$sessionFolder = $this->createMock(Folder::class);
		$sessionFolder
			->expects($this->once())
			->method('getFirstNodeById')
			->with(999)
			->willReturn(null);

		$rootFolder = $this->createMock(Folder::class);
		$rootFolder
			->expects($this->once())
			->method('get')
			->with('session123')
			->willReturn($sessionFolder);

		$this->folderService
			->expects($this->once())
			->method('getFolder')
			->willReturn($rootFolder);

		$this->getService()->deleteSignatureElement(null, 'session123', 999);
	}

	public function testDeleteSignatureElementWithoutUserThrowsWhenNodeIsNotFile(): void {
		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Element not found');

		$sessionFolder = $this->createMock(Folder::class);
		$folderNode = $this->createMock(Folder::class); // Not a File!

		$sessionFolder
			->expects($this->once())
			->method('getFirstNodeById')
			->with(777)
			->willReturn($folderNode);

		$rootFolder = $this->createMock(Folder::class);
		$rootFolder
			->expects($this->once())
			->method('get')
			->with('session456')
			->willReturn($sessionFolder);

		$this->folderService
			->expects($this->once())
			->method('getFolder')
			->willReturn($rootFolder);

		$this->getService()->deleteSignatureElement(null, 'session456', 777);
	}

	public function testDeleteSignatureElementOnlyDeletesSpecificFileNotWholeFolder(): void {
		// This test validates the critical security fix:
		// Previously: deleted entire session folder immediately (losing all files)
		// Now: deletes only specific file by nodeId, keeps other files intact

		$sessionFolder = $this->createMock(Folder::class);
		$targetFile = $this->createMock(File::class);
		$otherFile = $this->createMock(File::class);

		// Should call delete on the specific FILE, not on the FOLDER
		$targetFile->expects($this->once())
			->method('delete');

		$sessionFolder
			->expects($this->once())
			->method('getFirstNodeById')
			->with(100)
			->willReturn($targetFile);

		// After deleting target file, folder still has other files
		$sessionFolder
			->expects($this->once())
			->method('getDirectoryListing')
			->willReturn([$otherFile]);

		// Folder should NOT be deleted because it still has files
		$sessionFolder->expects($this->never())
			->method('delete');

		$rootFolder = $this->createMock(Folder::class);
		$rootFolder
			->expects($this->once())
			->method('get')
			->with('mysession')
			->willReturn($sessionFolder);

		$this->folderService
			->expects($this->once())
			->method('getFolder')
			->willReturn($rootFolder);

		$this->getService()->deleteSignatureElement(null, 'mysession', 100);
	}

	public function testDeleteSignatureElementDeletesEmptySessionFolder(): void {
		// When the last element is deleted, the empty session folder should be cleaned up

		$sessionFolder = $this->createMock(Folder::class);
		$lastFile = $this->createMock(File::class);

		$lastFile->expects($this->once())
			->method('delete');

		$sessionFolder
			->expects($this->once())
			->method('getFirstNodeById')
			->with(200)
			->willReturn($lastFile);

		// After deleting last file, folder is empty
		$sessionFolder
			->expects($this->once())
			->method('getDirectoryListing')
			->willReturn([]);

		// Empty folder SHOULD be deleted
		$sessionFolder->expects($this->once())
			->method('delete');

		$rootFolder = $this->createMock(Folder::class);
		$rootFolder
			->expects($this->once())
			->method('get')
			->with('session999')
			->willReturn($sessionFolder);

		$this->folderService
			->expects($this->once())
			->method('getFolder')
			->willReturn($rootFolder);

		$this->getService()->deleteSignatureElement(null, 'session999', 200);
	}
}
