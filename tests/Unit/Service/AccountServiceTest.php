<?php

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUser;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Handler\CfsslHandler;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Tests\Unit\UserTrait;
use OCA\Settings\Mailer\NewUserMailHelper;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;

/**
 * @internal
 */
final class AccountServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	use UserTrait;
	/** @var IL10N */
	private $l10n;
	/** @var FileUserMapper */
	private $fileUserMapper;
	/** @var IUserManager */
	private $userManagerInstance;
	/** @var FolderService */
	private $folder;
	/** @var IConfig */
	private $config;
	/** @var NewUserMailHelper */
	private $newUserMail;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var CfsslHandler */
	private $cfsslHandler;
	/** @var AccountService */
	private $service;

	public function setUp(): void {
		$this->userSetUp();
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));
		$this->fileUserMapper = $this->createMock(FileUserMapper::class);
		$this->userManagerInstance = $this->createMock(IUserManager::class);
		$this->folder = $this->createMock(FolderService::class);
		$this->root = $this->createMock(IRootFolder::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->config = $this->createMock(IConfig::class);
		$this->newUserMail = $this->createMock(NewUserMailHelper::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->cfsslHandler = $this->createMock(CfsslHandler::class);

		$this->service = new AccountService(
			$this->l10n,
			$this->fileUserMapper,
			$this->userManagerInstance,
			$this->folder,
			$this->root,
			$this->fileMapper,
			$this->config,
			$this->newUserMail,
			$this->urlGenerator,
			$this->cfsslHandler
		);
	}

	/**
	 * @dataProvider providerTestValidateCreateToSignUsingDataProvider
	 */
	public function testValidateCreateToSignUsingDataProvider($arguments, $expectedErrorMessage) {
		if (is_callable($arguments)) {
			$arguments = $arguments($this);
		}

		$this->service = new AccountService(
			$this->l10n,
			$this->fileUserMapper,
			$this->userManagerInstance,
			$this->folder,
			$this->root,
			$this->fileMapper,
			$this->config,
			$this->newUserMail,
			$this->urlGenerator,
			$this->cfsslHandler
		);
		$this->expectExceptionMessage($expectedErrorMessage);
		$this->service->validateCreateToSign($arguments);
	}

	public function providerTestValidateCreateToSignUsingDataProvider() {
		return [
			[
				[
					'uuid' => 'invalid uuid'
				],
				'Invalid UUID'
			],
			[
				function ($self) {
					$uuid = '12345678-1234-1234-1234-123456789012';
					$self->fileUserMapper = $self->createMock(FileUserMapper::class);
					$self->fileUserMapper
						->method('getByUuid')
						->will($self->returnCallback(function () {
							throw new \Exception("Beep, beep, not found!", 1);
						}));
					return [
						'uuid' => $uuid
					];
				},
				'UUID not found'
			],
			[
				function ($self) {
					$fileUser = $self->createMock(FileUser::class);
					$fileUser
						->method('__call')
						->with($self->equalTo('getEmail'), $self->anything())
						->will($self->returnValue('valid@test.coop'));
					$self->fileUserMapper
						->method('getByUuid')
						->will($self->returnValue($fileUser));
					return [
						'uuid' => '12345678-1234-1234-1234-123456789012',
						'email' => 'invalid@test.coop',
						'signPassword' => '132456789'
					];
				},
				'This is not your file'
			],
			[
				function ($self) {
					$fileUser = $self->createMock(FileUser::class);
					$fileUser
						->method('__call')
						->with($self->equalTo('getEmail'), $self->anything())
						->will($self->returnValue('valid@test.coop'));
					$self->fileUserMapper
						->method('getByUuid')
						->will($self->returnValue($fileUser));
					$self->userManagerInstance
						->method('userExists')
						->will($self->returnValue(true));
					return [
						'uuid' => '12345678-1234-1234-1234-123456789012',
						'email' => 'valid@test.coop',
						'signPassword' => '123456789'
					];
				},
				'User already exists'
			],
			[
				function ($self) {
					$fileUser = $self->createMock(FileUser::class);
					$fileUser
						->method('__call')
						->with($self->equalTo('getEmail'), $self->anything())
						->will($self->returnValue('valid@test.coop'));
					$self->fileUserMapper
						->method('getByUuid')
						->will($self->returnValue($fileUser));
					return [
						'uuid' => '12345678-1234-1234-1234-123456789012',
						'email' => 'valid@test.coop',
						'signPassword' => '132456789',
						'password' => ''
					];
				},
				'Password is mandatory'
			],
			[
				function ($self) {
					$fileUser = $this->createMock(FileUser::class);
					$fileUser
						->method('__call')
						->withConsecutive(
							[$this->equalTo('getEmail')],
							[$this->equalTo('getFileId')],
							[$this->equalTo('getNodeId')],
							[$this->equalTo('getUserId')],
						)
						->will($this->returnValueMap([
							['getEmail', [], 'valid@test.coop'],
							['getFileId', [], 171],
							['getNodeId', [], 171],
							['getUserId', [], 'username'],
						]));
					$self->fileMapper
						->method('getById')
						->will($self->returnValue($fileUser));
					$self->fileUserMapper
						->method('getByUuid')
						->will($self->returnValue($fileUser));

					$self->root
						->method('getById')
						->will($self->returnValue([]));
					return [
						'uuid' => '12345678-1234-1234-1234-123456789012',
						'email' => 'valid@test.coop',
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
	public function testValidateCertificateDataUsingDataProvider($arguments, $expectedErrorMessage) {
		if (is_callable($arguments)) {
			$arguments = $arguments($this);
		}

		$this->service = new AccountService(
			$this->l10n,
			$this->fileUserMapper,
			$this->userManagerInstance,
			$this->folder,
			$this->root,
			$this->fileMapper,
			$this->config,
			$this->newUserMail,
			$this->urlGenerator,
			$this->cfsslHandler
		);
		$this->expectExceptionMessage($expectedErrorMessage);
		$this->service->validateCertificateData($arguments);
	}

	public function providerTestValidateCertificateDataUsingDataProvider() {
		return [
			[
				[
					'uuid' => '12345678-1234-1234-1234-123456789012',
					'email' => ''
				],
				'You must have an email. You can define the email in your profile.'
			],
			[
				[
					'uuid' => '12345678-1234-1234-1234-123456789012',
					'email' => 'invalid'
				],
				'Invalid email'
			],
			[
				function ($self) {
					$fileUser = $self->createMock(FileUser::class);
					$fileUser
						->method('__call')
						->with($self->equalTo('getEmail'), $self->anything())
						->will($self->returnValue('valid@test.coop'));
					$self->fileUserMapper
						->method('getByUuid')
						->will($self->returnValue($fileUser));
					return [
						'uuid' => '12345678-1234-1234-1234-123456789012',
						'email' => 'valid@test.coop',
						'password' => '123456789',
						'signPassword' => '',
					];
				},
				'Password to sign is mandatory'
			]
		];
	}

	private function mockValidateWithSuccess() {
		$backend = $this->createMock(\OC\User\Database::class);
		$backend->method('implementsActions')
			->willReturn(true);
		$backend->method('userExists')
			->willReturn(true);
		$backend->method('getRealUID')
			->willReturn('userId');
		$userManager = \OC::$server->getUserManager();
		$userManager->clearBackends();
		$userManager->registerBackend($backend);

		$fileUser = $this->createMock(FileUser::class);
		$fileUser
			->method('__call')
			->withConsecutive(
				[$this->equalTo('getEmail')],
				[$this->equalTo('getFileId')],
				[$this->equalTo('getNodeId')],
				[$this->equalTo('getUserId')],
			)
			->will($this->returnValueMap([
				['getEmail', [], 'valid@test.coop'],
				['getFileId', [], 171],
				['getNodeId', [], 171],
				['getUserId', [], 'username'],
			]));
		$this->fileMapper
			->method('getById')
			->will($this->returnValue($fileUser));
		$this->fileUserMapper
			->method('getByUuid')
			->will($this->returnValue($fileUser));

		$this->root
			->method('getById')
			->will($this->returnValue(['fileToSign']));
	}

	public function testValidateCreateToSignSuccess() {
		$this->mockValidateWithSuccess();

		$this->service = new AccountService(
			$this->l10n,
			$this->fileUserMapper,
			$this->userManagerInstance,
			$this->folder,
			$this->root,
			$this->fileMapper,
			$this->config,
			$this->newUserMail,
			$this->urlGenerator,
			$this->cfsslHandler
		);
		$actual = $this->service->validateCreateToSign([
			'uuid' => '12345678-1234-1234-1234-123456789012',
			'email' => 'valid@test.coop',
			'password' => '123456789',
			'signPassword' => '123456789',
		]);
		$this->assertNull($actual);
	}

	public function testGenerateCertificateWithInvalidData() {
		$this->cfsslHandler
			->method('__call')
			->will($this->returnValue($this->cfsslHandler));
		$this->expectErrorMessage('Failure on generate certificate');
		$this->service->generateCertificate('uid', 'password', 'username');
	}

	public function testGenerateCertificateAndSaveToAFolderAndNotAFile() {
		$node = $this->createMock(\OCP\Files\Folder::class);
		$node->method('nodeExists')->will($this->returnValue(true));
		$node->method('get')->will($this->returnValue($node));
		$this->folder->method('getFolder')->will($this->returnValue($node));

		$backend = $this->createMock(\OC\User\Database::class);
		$backend->method('implementsActions')
			->willReturn(true);
		$backend->method('userExists')
			->willReturn(true);
		$backend->method('getRealUID')
			->willReturn('userId');
		$userManager = \OC::$server->getUserManager();
		$userManager->clearBackends();
		$userManager->registerBackend($backend);

		$this->cfsslHandler
			->method('__call')
			->will($this->returnValue($this->cfsslHandler));
		$this->cfsslHandler
			->method('generateCertificate')
			->will($this->returnValue('raw content of pfx file'));
		$this->expectErrorMessage('path signature.pfx already exists and is not a file!');
		$this->expectExceptionCode(400);
		$this->service->generateCertificate('uid', 'password', 'username');
	}

	public function testGenerateCertificateAndSuccessfullySavedToAnExistingFile() {
		$node = $this->createMock(\OCP\Files\Folder::class);
		$node->method('nodeExists')->will($this->returnValue(true));
		$file = $this->createMock(\OCP\Files\File::class);
		$node->method('get')->will($this->returnValue($file));
		$this->folder->method('getFolder')->will($this->returnValue($node));

		$backend = $this->createMock(\OC\User\Database::class);
		$backend->method('implementsActions')
			->willReturn(true);
		$backend->method('userExists')
			->willReturn(true);
		$backend->method('getRealUID')
			->willReturn('userId');
		$userManager = \OC::$server->getUserManager();
		$userManager->clearBackends();
		$userManager->registerBackend($backend);

		$this->cfsslHandler
			->method('__call')
			->will($this->returnValue($this->cfsslHandler));
		$this->cfsslHandler
			->method('generateCertificate')
			->will($this->returnValue('raw content of pfx file'));
		$actual = $this->service->generateCertificate('uid', 'password', 'username');
		$this->assertInstanceOf('\OCP\Files\File', $actual);
	}

	public function testGenerateCertificateAndSuccessfullySavedToANewFile() {
		$node = $this->createMock(\OCP\Files\Folder::class);
		$node->method('nodeExists')->will($this->returnValue(false));
		$file = $this->createMock(\OCP\Files\File::class);
		$node->method('newFile')->will($this->returnValue($file));
		$this->folder->method('getFolder')->will($this->returnValue($node));

		$backend = $this->createMock(\OC\User\Database::class);
		$backend->method('implementsActions')
			->willReturn(true);
		$backend->method('userExists')
			->willReturn(true);
		$backend->method('getRealUID')
			->willReturn('userId');
		$userManager = \OC::$server->getUserManager();
		$userManager->clearBackends();
		$userManager->registerBackend($backend);

		$this->cfsslHandler
			->method('__call')
			->will($this->returnValue($this->cfsslHandler));
		$this->cfsslHandler
			->method('generateCertificate')
			->will($this->returnValue('raw content of pfx file'));
		$actual = $this->service->generateCertificate('uid', 'password', 'username');
		$this->assertInstanceOf('\OCP\Files\File', $actual);
	}

	public function testGetPfxWithInvalidUser() {
		$this->expectErrorMessage('Backends provided no user object for invalidUser');
		$this->service->getPfx('invalidUser');
	}

	public function testGetPfxWithInvalidPfx() {
		$backend = $this->createMock(\OC\User\Database::class);
		$backend->method('implementsActions')
			->willReturn(true);
		$backend->method('userExists')
			->willReturn(true);
		$backend->method('getRealUID')
			->willReturn('userId');
		$userManager = \OC::$server->getUserManager();
		$userManager->clearBackends();
		$userManager->registerBackend($backend);

		$node = $this->createMock(\OCP\Files\Folder::class);
		$node->method('nodeExists')->will($this->returnValue(false));
		$this->folder->method('getFolder')->will($this->returnValue($node));
		$this->expectErrorMessage('Password to sign not defined. Create a password to sign');
		$this->expectExceptionCode(400);
		$this->service->getPfx('userId');
	}

	public function testGetPfxOk() {
		$backend = $this->createMock(\OC\User\Database::class);
		$backend->method('implementsActions')
			->willReturn(true);
		$backend->method('userExists')
			->willReturn(true);
		$backend->method('getRealUID')
			->willReturn('userId');
		$userManager = \OC::$server->getUserManager();
		$userManager->clearBackends();
		$userManager->registerBackend($backend);

		$node = $this->createMock(\OCP\Files\Folder::class);
		$node->method('nodeExists')->will($this->returnValue(true));
		$node->method('get')->will($this->returnValue($node));
		$this->folder->method('getFolder')->will($this->returnValue($node));
		$actual = $this->service->getPfx('userId');
		$this->assertInstanceOf('\OCP\Files\Node', $actual);
	}

	public function testCreateToSignWithErrorInSendingEmail() {
		$fileUser = $this->createMock(\OCA\Libresign\Db\FileUser::class);
		$this->fileUserMapper->method('getByUuid')->will($this->returnValue($fileUser));
		$userToSign = $this->createMock(\OCP\IUser::class);
		$this->userManagerInstance->method('createUser')->will($this->returnValue($userToSign));
		$this->config->method('getAppValue')->will($this->returnValue('yes'));
		$template = $this->createMock(\OCP\Mail\IEMailTemplate::class);
		$this->newUserMail->method('generateTemplate')->will($this->returnValue($template));
		$this->newUserMail->method('sendMail')->will($this->returnCallback(function () {
			throw new \Exception("Error Processing Request", 1);
		}));
		$this->expectErrorMessage('Unable to send the invitation');
		$this->service->createToSign('uuid', 'username', 'passwordOfUser', 'passwordToSign');
	}

	public function testCreateToSignSuccess() {
		$fileUser = $this->createMock(\OCA\Libresign\Db\FileUser::class);
		$this->fileUserMapper->method('getByUuid')->will($this->returnValue($fileUser));
		$userToSign = $this->createMock(\OCP\IUser::class);
		$userToSign->method('getUID')->will($this->returnValue('userToSignUid'));
		$this->userManagerInstance->method('createUser')->will($this->returnValue($userToSign));
		$this->config->method('getAppValue')->will($this->returnValue('no'));

		$node = $this->createMock(\OCP\Files\Folder::class);
		$node->method('nodeExists')->will($this->returnValue(false));
		$file = $this->createMock(\OCP\Files\File::class);
		$node->method('newFile')->will($this->returnValue($file));
		$this->folder->method('getFolder')->will($this->returnValue($node));

		$backend = $this->createMock(\OC\User\Database::class);
		$backend->method('implementsActions')
			->willReturn(true);
		$backend->method('userExists')
			->willReturn(true);
		$backend->method('getRealUID')
			->willReturn('userId');
		$userManager = \OC::$server->getUserManager();
		$userManager->clearBackends();
		$userManager->registerBackend($backend);

		$this->cfsslHandler
			->method('__call')
			->will($this->returnValue($this->cfsslHandler));
		$this->cfsslHandler
			->method('generateCertificate')
			->will($this->returnValue('raw content of pfx file'));

		$actual = $this->service->createToSign('uuid', 'username', 'passwordOfUser', 'passwordToSign');
		$this->assertNull($actual);
	}

	/**
	 * @dataProvider providerTestGetConfigWithInvalidUuuid
	 */
	public function testGetConfigWithInvalidUuuid($uuid, $userId, $formatOfPdfOnSign, $expected, $setUp) {
		if (is_callable($setUp)) {
			$setUp($this);
		}
		$this->service = new AccountService(
			$this->l10n,
			$this->fileUserMapper,
			$this->userManagerInstance,
			$this->folder,
			$this->root,
			$this->fileMapper,
			$this->config,
			$this->newUserMail,
			$this->urlGenerator,
			$this->cfsslHandler
		);
		$actual = $this->service->getConfig($uuid, $userId, $formatOfPdfOnSign);
		$actual = json_encode($actual);
		$this->assertJsonStringEqualsJsonString(
			$actual,
			json_encode($expected)
		);
	}

	public function providerTestGetConfigWithInvalidUuuid() {
		return [
			[ // #0
				null, null, 'filetype',
				[
					'settings' => [
						'hasSignatureFile' => false
					]
				], null
			],
			[ // #1
				'uuid', 'userid', 'filetype',
				[
					'action' => JSActions::ACTION_DO_NOTHING,
					'errors' => [
						'This is not your file'
					],
					'settings' => [
						'hasSignatureFile' => false
					]
				], null
			],
			[ // #2
				'uuid', null, 'filetype',
				[
					'action' => JSActions::ACTION_CREATE_USER,
					'settings' => [
						'hasSignatureFile' => false
					]
				], null
			],
			[ // #3
				'uuid', 'username', 'filetype',
				[
					'action' => JSActions::ACTION_DO_NOTHING,
					'errors' => [
						'This is not your file'
					],
					'settings' => [
						'hasSignatureFile' => false
					]
				], function ($self) {
					$self->createUser('username', 'password');
				}
			],
			[ // #4
				'uuid', null, 'filetype',
				[
					'action' => JSActions::ACTION_DO_NOTHING,
					'errors' => [
						'Invalid UUID'
					],
					'settings' => [
						'hasSignatureFile' => false
					]
				], function ($self) {
					$self->fileUserMapper
						->method('getByUuid')
						->will($this->returnCallback(function () {
							throw new \Exception("Beep, beep, not found!", 1);
						}));
				}
			],
			[ // #5
				'uuid', null, 'filetype',
				[
					'action' => JSActions::ACTION_REDIRECT,
					'errors' => [
						'User already exists. Please login.'
					],
					'redirect' => "",
					'settings' => [
						'hasSignatureFile' => false
					]
				], function ($self) {
					$user = $self->createUser('username', 'password');
					$user->setEMailAddress('valid@test.coop');

					$fileUser = $self->createMock(FileUser::class);
					$fileUser
						->method('__call')
						->withConsecutive(
							[$this->equalTo('getUserId')],
							[$this->equalTo('getEmail')]
						)
						->will($this->returnValueMap([
							['getUserId', [], null],
							['getEmail', [], 'valid@test.coop']
						]));
					$self->fileUserMapper
						->method('getByUuid')
						->will($self->returnValue($fileUser));
					$self->userManagerInstance
						->method('userExists')
						->willReturn(true);
				}
			],
			[ // #6
				'uuid', null, 'filetype',
				[
					'action' => JSActions::ACTION_SHOW_ERROR,
					'errors' => [
						'File already signed.'
					],
					'settings' => [
						'hasSignatureFile' => false
					]
				], function ($self) {
					$fileUser = $self->createMock(FileUser::class);
					$fileUser
						->method('__call')
						->withConsecutive(
							[$this->equalTo('getUserId')],
							[$this->equalTo('getSigned')]
						)
						->will($this->returnValueMap([
							['getUserId', [], 171],
							['getSigned', [], true]
						]));
					$self->fileUserMapper
						->method('getByUuid')
						->will($self->returnValue($fileUser));
					$self->userManagerInstance
						->method('userExists')
						->willReturn(true);
				}
			]
		];
	}
}
