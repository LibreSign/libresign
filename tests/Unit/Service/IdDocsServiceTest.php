<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OC\AppFramework\Utility\TimeFactory;
use OC\Http\Client\ClientService;
use OCA\Libresign\Db\AccountFileMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileTypeMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Handler\CertificateEngine\Handler as CertificateEngineHandler;
use OCA\Libresign\Handler\Pkcs12Handler;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\AccountFileService;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\RequestSignatureService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Service\SignFileService;
use OCA\Settings\Mailer\NewUserMailHelper;
use OCP\Accounts\IAccountManager;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @internal
 * @group DB
 */
final class IdDocsServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IL10N&MockObject $l10n;
	private SignRequestMapper&MockObject $signRequestMapper;
	private IUserManager&MockObject $userManager;
	private IAccountManager $accountManager;
	private IRootFolder&MockObject $root;
	private IUserMountCache&MockObject $userMountCache;
	private IMimeTypeDetector&MockObject $mimeTypeDetector;
	private FileMapper&MockObject $fileMapper;
	private FileTypeMapper&MockObject $fileTypeMapper;
	private AccountFileMapper&MockObject $accountFileMapper;
	private SignFileService&MockObject $signFile;
	private CertificateEngineHandler&MockObject $certificateEngineHandler;
	private IConfig&MockObject $config;
	private IAppConfig&MockObject $appConfig;
	private IMountProviderCollection&MockObject $mountProviderCollection;
	private NewUserMailHelper&MockObject $newUserMail;
	private IdentifyMethodService&MockObject $identifyMethodService;
	private ValidateHelper&MockObject $validateHelper;
	private IURLGenerator&MockObject $urlGenerator;
	private IGroupManager&MockObject $groupManager;
	private AccountFileService&MockObject $accountFileService;
	private SignerElementsService&MockObject $signerElementsService;
	private UserElementMapper&MockObject $userElementMapper;
	private FolderService&MockObject $folderService;
	private ClientService&MockObject $clientService;
	private TimeFactory&MockObject $timeFactory;
	private RequestSignatureService&MockObject $requestSignatureService;
	private Pkcs12Handler&MockObject $pkcs12Handler;

	public function setUp(): void {
		parent::setUp();
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->accountManager = $this->createMock(IAccountManager::class);
		$this->root = $this->createMock(IRootFolder::class);
		$this->userMountCache = $this->createMock(IUserMountCache::class);
		$this->mimeTypeDetector = $this->createMock(IMimeTypeDetector::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->fileTypeMapper = $this->createMock(FileTypeMapper::class);
		$this->accountFileMapper = $this->createMock(AccountFileMapper::class);
		$this->signFile = $this->createMock(SignFileService::class);
		$this->requestSignatureService = $this->createMock(RequestSignatureService::class);
		$this->certificateEngineHandler = $this->createMock(CertificateEngineHandler::class);
		$this->config = $this->createMock(IConfig::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->mountProviderCollection = $this->createMock(IMountProviderCollection::class);
		$this->newUserMail = $this->createMock(NewUserMailHelper::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->validateHelper = $this->createMock(ValidateHelper::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->pkcs12Handler = $this->createMock(Pkcs12Handler::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->accountFileService = $this->createMock(AccountFileService::class);
		$this->signerElementsService = $this->createMock(SignerElementsService::class);
		$this->userElementMapper = $this->createMock(UserElementMapper::class);
		$this->folderService = $this->createMock(FolderService::class);
		$this->clientService = $this->createMock(ClientService::class);
		$this->timeFactory = $this->createMock(TimeFactory::class);
	}

	private function getService(): AccountService {
		return new AccountService(
			$this->l10n,
			$this->signRequestMapper,
			$this->userManager,
			$this->accountManager,
			$this->root,
			$this->userMountCache,
			$this->mimeTypeDetector,
			$this->fileMapper,
			$this->fileTypeMapper,
			$this->accountFileMapper,
			$this->signFile,
			$this->requestSignatureService,
			$this->certificateEngineHandler,
			$this->config,
			$this->appConfig,
			$this->mountProviderCollection,
			$this->newUserMail,
			$this->identifyMethodService,
			$this->validateHelper,
			$this->urlGenerator,
			$this->pkcs12Handler,
			$this->groupManager,
			$this->accountFileService,
			$this->signerElementsService,
			$this->userElementMapper,
			$this->folderService,
			$this->clientService,
			$this->timeFactory
		);
	}

	/**
	 * @dataProvider providerTestValidateCreateToSignUsingDataProvider
	 */
	public function testValidateCreateToSignUsingDataProvider($arguments, $expectedErrorMessage):void {
		$this->markTestSkipped('Need to reimplement this test, stated to failure after add identify methods');
		if (is_callable($arguments)) {
			$arguments = $arguments($this);
		}

		$this->expectExceptionMessage($expectedErrorMessage);
		$this->getService()->validateCreateToSign($arguments);
	}

	public static function providerTestValidateCreateToSignUsingDataProvider():array {
		return [
			[ #0
				[
					'uuid' => 'invalid uuid'
				],
				'Invalid UUID'
			],
			[ #1
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
			[ #2
				function ($self):array {
					$signRequest = $self->createMock(SignRequest::class);
					$signRequest
						->method('__call')
						->with($self->equalTo('getEmail'), $self->anything())
						->will($self->returnValue('valid@test.coop'));
					$self->signRequestMapper
						->method('getByUuid')
						->will($self->returnValue($signRequest));
					return [
						'uuid' => '12345678-1234-1234-1234-123456789012',
						'user' => [
							'email' => 'invalid@test.coop',
						],
						'signPassword' => '132456789'
					];
				},
				'This is not your file'
			],
			[ #3
				function ($self):array {
					$signRequest = $self->createMock(SignRequest::class);
					$signRequest
						->method('__call')
						->with($self->equalTo('getEmail'), $self->anything())
						->will($self->returnValue('valid@test.coop'));
					$self->signRequestMapper
						->method('getByUuid')
						->will($self->returnValue($signRequest));
					$self->userManager
						->method('userExists')
						->will($self->returnValue(true));
					return [
						'uuid' => '12345678-1234-1234-1234-123456789012',
						'user' => [
							'email' => 'valid@test.coop',
						],
						'signPassword' => '123456789'
					];
				},
				'User already exists'
			],
			[ #4
				function ($self):array {
					$signRequest = $self->createMock(SignRequest::class);
					$signRequest
						->method('__call')
						->with($self->equalTo('getEmail'), $self->anything())
						->will($self->returnValue('valid@test.coop'));
					$self->signRequestMapper
						->method('getByUuid')
						->will($self->returnValue($signRequest));
					return [
						'uuid' => '12345678-1234-1234-1234-123456789012',
						'user' => [
							'email' => 'valid@test.coop',
						],
						'signPassword' => '132456789',
						'password' => ''
					];
				},
				'Password is mandatory'
			],
			[ #5
				function ($self):array {
					$signRequest = $this->createMock(SignRequest::class);
					$signRequest
						->method('__call')
						->willReturnCallback(fn (string $method) =>
							match ($method) {
								'getEmail' => 'valid@test.coop',
								'getFileId' => 171,
								'getUserId' => 'username',
							}
						);
					$file = $this->createMock(\OCA\Libresign\Db\File::class);
					$self->fileMapper
						->method('getById')
						->will($self->returnValue($file));
					$self->signRequestMapper
						->method('getByUuid')
						->will($self->returnValue($signRequest));

					$self->root
						->method('getById')
						->will($self->returnValue([]));
					$folder = $this->createMock(\OCP\Files\Folder::class);
					$folder
						->method('getById')
						->willReturn([]);
					$self->root
						->method('getUserFolder')
						->willReturn($folder);
					return [
						'uuid' => '12345678-1234-1234-1234-123456789012',
						'user' => [
							'email' => 'valid@test.coop',
						],
						'signPassword' => '132456789',
						'password' => '123456789'
					];
				},
				'File not found'
			],
		];
	}

	/**
	 * @dataProvider providerTestValidateCertificateDataUsingDataProvider
	 */
	public function testValidateCertificateDataUsingDataProvider($arguments, $expectedErrorMessage):void {
		if (is_callable($arguments)) {
			$arguments = $arguments($this);
		}

		$this->expectExceptionMessage($expectedErrorMessage);
		$this->getService()->validateCertificateData($arguments);
	}

	public static function providerTestValidateCertificateDataUsingDataProvider():array {
		return [
			[
				[
					'uuid' => '12345678-1234-1234-1234-123456789012',
					'user' => [
						'email' => '',
					],
				],
				'You must have an email. You can define the email in your profile.'
			],
			[
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
			->will($this->returnValue('valid@test.coop'));
		$this->signRequestMapper
			->method('getByUuid')
			->will($this->returnValue($signRequest));
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
			->willReturnCallback(fn (string $method) =>
				match ($method) {
					'getDisplayName' => 'John Doe',
					'getId' => 1,
				}
			);
		$this->signRequestMapper->method('getByUuid')->will($this->returnValue($signRequest));
		$userToSign = $this->createMock(\OCP\IUser::class);
		$this->userManager->method('createUser')->will($this->returnValue($userToSign));
		$this->config->method('getAppValue')->will($this->returnValue('yes'));
		$template = $this->createMock(\OCP\Mail\IEMailTemplate::class);
		$this->newUserMail->method('generateTemplate')->will($this->returnValue($template));
		$this->newUserMail->method('sendMail')->will($this->returnCallback(function ():void {
			throw new \Exception('Error Processing Request', 1);
		}));
		$this->expectExceptionMessage('Unable to send the invitation');
		$this->getService()->createToSign('uuid', 'username', 'passwordOfUser', 'passwordToSign');
	}

	public function testGetPdfByUuidWithSuccessAndSignedFile():void {
		$libresignFile = $this->createMock(\OCA\Libresign\Db\File::class);
		$this->fileMapper
			->method('getByUuid')
			->will($this->returnValue($libresignFile));
		$this->userMountCache
			->method('getMountsForFileId')
			->willreturn([]);
		$node = $this->createMock(\OCP\Files\File::class);
		$this->root
			->method('getById')
			->willReturn([$node]);

		$actual = $this->getService()->getPdfByUuid('uuid');
		$this->assertInstanceOf(\OCP\Files\File::class, $actual);
	}

	public function testGetPdfByUuidWithSuccessAndUnignedFile():void {
		$libresignFile = $this->createMock(\OCA\Libresign\Db\File::class);
		$this->fileMapper
			->method('getByUuid')
			->will($this->returnValue($libresignFile));
		$this->userMountCache
			->method('getMountsForFileId')
			->willreturn([]);
		$node = $this->createMock(\OCP\Files\File::class);
		$this->root
			->method('getById')
			->willReturn([$node]);

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
}
