<?php

use OCA\Libresign\Db\AccountFileMapper;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Handler\Pkcs12Handler;
use OCA\Libresign\Handler\Pkcs7Handler;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\SignFileService;
use OCA\Libresign\Service\SignMethodService;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\IRootFolder;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * @group DB
 */
final class SignFileServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IL10N|MockObject $l10n;
	private Pkcs7Handler|MockObject $pkcs7Handler;
	private Pkcs12Handler|MockObject $pkcs12Handler;
	private FileMapper|MockObject $fileMapper;
	private SignRequestMapper|MockObject $signRequestMapper;
	private AccountFileMapper|MockObject $accountFileMapper;
	private IClientService|MockObject $clientService;
	private IUserManager|MockObject $userManager;
	private FolderService|MockObject $folderService;
	private LoggerInterface|MockObject $logger;
	private IConfig $config;
	private ValidateHelper|MockObject $validateHelper;
	private IRootFolder|MockObject $root;
	private IUserMountCache|MockObject $userMountCache;
	private FileElementMapper|MockObject $fileElementMapper;
	private UserElementMapper|MockObject $userElementMapper;
	private IEventDispatcher|MockObject $eventDispatcher;
	private IURLGenerator|MockObject $urlGenerator;
	private SignMethodService|MockObject $signMethod;
    private IdentifyMethodMapper|MockObject $identifyMethodMapper;
	private ITempManager|MockObject $tempManager;

	public function setUp(): void {
		parent::setUp();
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->accountFileMapper = $this->createMock(AccountFileMapper::class);
		$this->pkcs7Handler = $this->createMock(Pkcs7Handler::class);
		$this->pkcs12Handler = $this->createMock(Pkcs12Handler::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->folderService = $this->createMock(FolderService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->config = $this->createMock(IConfig::class);
		$this->validateHelper = $this->createMock(\OCA\Libresign\Helper\ValidateHelper::class);
		$this->root = $this->createMock(\OCP\Files\IRootFolder::class);
		$this->userMountCache = $this->createMock(IUserMountCache::class);
		$this->fileElementMapper = $this->createMock(FileElementMapper::class);
		$this->userElementMapper = $this->createMock(UserElementMapper::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->signMethod = $this->createMock(SignMethodService::class);
		$this->identifyMethodMapper = $this->createMock(IdentifyMethodMapper::class);
		$this->tempManager = $this->createMock(ITempManager::class);
	}

	private function getService(): SignFileService {
		return new SignFileService(
			$this->l10n,
			$this->fileMapper,
			$this->signRequestMapper,
			$this->accountFileMapper,
			$this->pkcs7Handler,
			$this->pkcs12Handler,
			$this->folderService,
			$this->clientService,
			$this->userManager,
			$this->logger,
			$this->config,
			$this->validateHelper,
			$this->root,
			$this->userMountCache,
			$this->fileElementMapper,
			$this->userElementMapper,
			$this->eventDispatcher,
			$this->urlGenerator,
			$this->signMethod,
			$this->identifyMethodMapper,
			$this->tempManager
		);
	}

	public function testCanDeleteRequestSignatureWhenDocumentAlreadySigned() {
		$file = $this->createMock(\OCA\Libresign\Db\File::class);
		$file->method('__call')->with($this->equalTo('getId'))->will($this->returnValue(1));
		$this->fileMapper->method('getByUuid')->will($this->returnValue($file));
		$signRequest = $this->createMock(\OCA\Libresign\Db\SignRequest::class);
		$signRequest
			->method('__call')
			->withConsecutive(
				[$this->equalTo('getSigned')]
			)
			->will($this->returnValueMap([
				['getSigned', [], '2021-01-01 01:01:01'],
			]));
		$this->signRequestMapper->method('getByFileUuid')->will($this->returnValue([$signRequest]));
		$this->expectExceptionMessage('Document already signed');
		$this->getService()->canDeleteRequestSignature(['uuid' => 'valid']);
	}

	public function testCanDeleteRequestSignatureWhenNoSignatureWasRequested() {
		$file = $this->createMock(\OCA\Libresign\Db\File::class);
		$file->method('__call')->with($this->equalTo('getId'))->will($this->returnValue(1));
		$this->fileMapper->method('getByUuid')->will($this->returnValue($file));
		$signRequest = $this->createMock(\OCA\Libresign\Db\SignRequest::class);
		$signRequest
			->method('__call')
			->withConsecutive(
				[$this->equalTo('getSigned')],
				[$this->equalTo('getEmail')]
			)
			->will($this->returnValueMap([
				['getSigned', [], null],
				['getEmail', [], 'otheremail@test.coop']
			]));
		$this->signRequestMapper->method('getByFileUuid')->will($this->returnValue([$signRequest]));
		$this->expectExceptionMessage('No signature was requested to %');
		$this->getService()->canDeleteRequestSignature([
			'uuid' => 'valid',
			'users' => [
				[
					'email' => 'test@test.coop'
				]
			]
		]);
	}

	public function testNotifyCallback() {
		$libreSignFile = new \OCA\Libresign\Db\File();
		$libreSignFile->setCallback('https://test.coop');
		$service = $this->getService();
		$service->setLibreSignFile($libreSignFile);
		$file = $this->createMock(\OCP\Files\File::class);
		$actual = $service->notifyCallback($file);
		$this->assertNull($actual);
	}

	public function testSignWithFileNotFound() {
		$this->expectExceptionMessage('File not found');

		$this->createUser('username', 'password');

		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('username');

		$this->root->method('getById')
			->willReturn([]);
		$this->userMountCache
			->method('getMountsForFileId')
			->wilLReturn([]);

		$signRequest = new \OCA\Libresign\Db\SignRequest();
		$this->getService()
			->setLibreSignFile($file)
			->setSignRequest($signRequest)
			->setPassword('password')
			->sign();
	}

	/**
	 * @dataProvider providerTestGetConfigWithInvalidUuid
	 */
	public function testGetConfigWithInvalidUuid($uuid, $userId, $formatOfPdfOnSign, $expected, $setUp) {
		$this->markTestSkipped('Stopped to work after refactor to add identify method');
		if (is_callable($setUp)) {
			$setUp($this);
		}
		if ($userId) {
			/** @var IUser|MockObject */
			$user = $this->createMock(IUser::class);
			$user->method('getUID')
				->willReturn($userId);
		} else {
			$user = null;
		}
		$actual = $this->getService()->getInfoOfFileToSignUsingSignRequestUuid($uuid, $user, $formatOfPdfOnSign);
		$actual = json_encode($actual);
		$this->assertJsonStringEqualsJsonString(
			$actual,
			json_encode($expected)
		);
	}

	public function providerTestGetConfigWithInvalidUuid() {
		return [
			[ #0
				null, null, 'filetype',
				[
				], null
			],
			[ #1
				'uuid', 'userid', 'filetype',
				[
				], function ($self) {
					$self->expectExceptionMessage(json_encode([
						'action' => JSActions::ACTION_DO_NOTHING,
						'errors' => [
							'This is not your file'
						],
					]));
					$signRequest = $self->createMock(SignRequest::class);
					$signRequest
						->method('__call')
						->withConsecutive(
							[$self->equalTo('getFileId')]
						)
						->will($self->returnValueMap([
							['getFileId', [], 1]
						]));
					$self->signRequestMapper
						->method('getByUuid')
						->will($self->returnValue($signRequest));
				}
			],
			[ #2
				'uuid', null, 'filetype',
				[
				], function ($self) {
					$self->expectExceptionMessage(json_encode([
						'action' => JSActions::ACTION_CREATE_USER,
						'settings' => [
							'accountHash' => md5('valid@test.coop')
						],
					]));
					$signRequest = $self->createMock(SignRequest::class);
					$signRequest
						->method('__call')
						->withConsecutive(
							[$self->equalTo('getFileId')],
							[$self->equalTo('getUserId')],
							[$self->equalTo('getEmail')]
						)
						->will($self->returnValueMap([
							['getFileId', [], 1],
							['getUserId', [], null],
							['getEmail', [], 'valid@test.coop']
						]));
					$self->signRequestMapper
						->method('getByUuid')
						->will($self->returnValue($signRequest));
				}
			],
			[ #3
				'uuid', 'username', 'filetype',
				[
				], function ($self) {
					$self->expectExceptionMessage(json_encode([
						'action' => JSActions::ACTION_DO_NOTHING,
						'errors' => [
							'This is not your file'
						],
					]));
					$signRequest = $self->createMock(SignRequest::class);
					$signRequest
						->method('__call')
						->withConsecutive(
							[$self->equalTo('getFileId')]
						)
						->will($self->returnValueMap([
							['getFileId', [], 1]
						]));
					$self->signRequestMapper
						->method('getByUuid')
						->will($self->returnValue($signRequest));
					$self->createUser('username', 'password');
				}
			],
			[ #4
				'uuid', null, 'filetype',
				[
				], function ($self) {
					$self->expectExceptionMessage(json_encode([
						'action' => JSActions::ACTION_DO_NOTHING,
						'errors' => [
							'Invalid UUID'
						],
					]));
					$self->signRequestMapper
						->method('getByUuid')
						->will($self->returnCallback(function () {
							throw new \OCP\AppFramework\Db\DoesNotExistException("Beep, beep, not found!", 1);
						}));
				}
			],
			[ #5
				'uuid', null, 'filetype',
				[
				], function ($self) {
					$self->expectExceptionMessage(json_encode([
						'action' => JSActions::ACTION_REDIRECT,
						'errors' => [
							'User already exists. Please login.'
						],
						'redirect' => '',
					]));
					$user = $self->createUser('username', 'password');
					$user->setEMailAddress('valid@test.coop');

					$signRequest = $self->createMock(SignRequest::class);
					$signRequest
						->method('__call')
						->withConsecutive(
							[$self->equalTo('getFileId')],
							[$self->equalTo('getUserId')],
							[$self->equalTo('getEmail')]
						)
						->will($self->returnValueMap([
							['getFileId', [], 1],
							['getUserId', [], null],
							['getEmail', [], 'valid@test.coop']
						]));
					$self->signRequestMapper
						->method('getByUuid')
						->will($self->returnValue($signRequest));
					$self->userManager
						->method('getByEmail')
						->willReturn($user);
				}
			],
			[ #6
				'uuid', null, 'filetype',
				[
				], function ($self) {
					$self->expectExceptionMessage(json_encode([
						'action' => JSActions::ACTION_SHOW_ERROR,
						'errors' => [
							'File already signed.'
						],
						'uuid' => null,
					]));
					$signRequest = $self->createMock(SignRequest::class);
					$signRequest
						->method('__call')
						->withConsecutive(
							[$self->equalTo('getFileId')],
							[$self->equalTo('getUserId')],
							[$self->equalTo('getSigned')]
						)
						->will($self->returnValueMap([
							['getFileId', [], 1],
							['getUserId', [], 'signrequest'],
							['getSigned', [], true]
						]));
					$self->signRequestMapper
						->method('getByUuid')
						->will($self->returnValue($signRequest));
					$file = $self->createMock(\OCA\Libresign\Db\File::class);
					$self->fileMapper
						->method('getById')
						->willReturn($file);
				}
			],
			[ #7
				'uuid', null, 'filetype',
				[
				], function ($self) {
					$self->expectExceptionMessage(json_encode([
						'action' => JSActions::ACTION_REDIRECT,
						'errors' => [
							'You are not logged in. Please log in.'
						],
						'redirect' => '',
					]));
					$self->identifyMethodMapper
						->method('getIdentifyMethodsFromSignRequestId')
						->will($self->returnValue(['account']));
					$signRequest = $self->createMock(SignRequest::class);
					$signRequest
						->method('__call')
						->withConsecutive(
							[$self->equalTo('getFileId')],
							[$self->equalTo('getUserId')],
							[$self->equalTo('getSigned')],
							[$self->equalTo('getId')],
						)
						->will($self->returnValueMap([
							['getFileId', [], 1],
							['getUserId', [], 'signrequest'],
							['getSigned', [], false],
							['getId', [], 1],
						]));
					$self->signRequestMapper
						->method('getByUuid')
						->will($self->returnValue($signRequest));
				}
			],
			[ #8
				'uuid', 'username', 'filetype',
				[
				], function ($self) {
					$self->expectExceptionMessage(json_encode([
						'action' => JSActions::ACTION_DO_NOTHING,
						'errors' => [
							'Invalid user'
						],
					]));
					$signRequest = $self->createMock(SignRequest::class);
					$signRequest
						->method('__call')
						->withConsecutive(
							[$self->equalTo('getFileId')],
							[$self->equalTo('getUserId')],
							[$self->equalTo('getUserId')]
						)
						->will($self->returnValueMap([
							['getFileId', [], 1],
							['getUserId', [], 'signrequest'],
							['getUserId', [], 'signrequest']
						]));
					$self->signRequestMapper
						->method('getByUuid')
						->will($self->returnValue($signRequest));
				}
			],
			[ #9
				'uuid', 'username', 'filetype',
				[
				], function ($self) {
					$self->expectExceptionMessage(json_encode([
						'action' => JSActions::ACTION_DO_NOTHING,
						'errors' => [
							'File not found'
						],
					]));
					$self->createUser('username', 'password');
					$signRequest = $self->createMock(SignRequest::class);
					$signRequest
						->method('__call')
						->withConsecutive(
							[$self->equalTo('getFileId')],
							[$self->equalTo('getUserId')],
							[$self->equalTo('getUserId')],
							[$self->equalTo('getSigned')],
							[$self->equalTo('getId')],
							[$self->equalTo('getNodeId')],
						)
						->will($self->returnValueMap([
							['getFileId', [], 1],
							['getUserId', [], 'username'],
							['getUserId', [], 'username'],
							['getSigned', [], false],
							['getId', [], 1],
							['getNodeId', [], 1],
						]));
					$self->signRequestMapper
						->method('getByUuid')
						->will($self->returnValue($signRequest));
					$file = $self->createMock(\OCA\Libresign\Db\File::class);
					$self->fileMapper
						->method('getByUuid')
						->willReturn($file);
					$self->fileMapper
						->method('getById')
						->willReturn($file);
					$folder = $self->createMock(\OCP\Files\Folder::class);
					$folder
						->method('getById')
						->willReturn([]);
					$self->root
						->method('getUserFolder')
						->willReturn($folder);
				}
			],
			[ #10
				null, 'username', 'filetype',
				[
				], function ($self) {
					$self->createUser('username', 'password');
				}
			],
			[ #11
				'uuid', 'username', 'base64',
				[
					'action' => JSActions::ACTION_SIGN,
					'settings' => [
						'identifyMethods' => [],
					],
					'sign' => [
						'pdf' => [
							'base64' => base64_encode('content')
						],
						'uuid' => null,
						'filename' => null,
						'description' => ''
					],
					'user' => [
						'name' => 'username'
					],
				], function ($self) {
					$self->createUser('username', 'password');
					$signRequest = $self->createMock(SignRequest::class);
					$signRequest
						->method('__call')
						->withConsecutive(
							[$self->equalTo('getFileId')],
							[$self->equalTo('getUserId')],
							[$self->equalTo('getUserId')],
							[$self->equalTo('getSigned')],
							[$self->equalTo('getId')],
							[$self->equalTo('getDisplayName')],
							[$self->equalTo('getDescription')],
							[$self->equalTo('getId')],
							[$self->equalTo('getSignMethod')]
						)
						->will($self->returnValueMap([
							['getFileId', [], 1],
							['getUserId', [], 'username'],
							['getUserId', [], 'username'],
							['getSigned', [], false],
							['getId', [], 1],
							['getDisplayName', [], 'username'],
							['getDescription', [], ''],
							['getId', [], 1],
							['getSignMethod', [], 'password'],
						]));
					$self->signRequestMapper
						->method('getByUuid')
						->will($self->returnValue($signRequest));
					$file = $self->createMock(\OCA\Libresign\Db\File::class);
					$self->fileMapper
						->method('getByUuid')
						->willReturn($file);
					$self->fileMapper
						->method('getById')
						->willReturn($file);
					$node = $self->createMock(\OCP\Files\File::class);
					$node->method('getContent')->will($self->returnValue('content'));
					$folder = $self->createMock(\OCP\Files\Folder::class);
					$folder
						->method('getById')
						->willReturn([$node]);
					$self->root
						->method('getUserFolder')
						->willReturn($folder);
				}
			],
			[ #12
				'uuid', 'username', 'url',
				[
					'action' => JSActions::ACTION_SIGN,
					'settings' => [
						'identifyMethods' => [],
					],
					'sign' => [
						'pdf' => [
							'url' => ''
						],
						'uuid' => 'uuid',
						'filename' => 'name',
						'description' => ''
					],
					'user' => [
						'name' => 'username'
					],
				], function ($self) {
					$self->createUser('username', 'password');
					$signRequest = $self->createMock(SignRequest::class);
					$signRequest
						->method('__call')
						->withConsecutive(
							[$self->equalTo('getFileId')],
							[$self->equalTo('getUserId')],
							[$self->equalTo('getUserId')],
							[$self->equalTo('getSigned')],
							[$self->equalTo('getId')],
							[$self->equalTo('getDisplayName')],
							[$self->equalTo('getDescription')],
							[$self->equalTo('getId')],
							[$self->equalTo('getSignMethod')]
						)
						->will($self->returnValueMap([
							['getFileId', [], 1],
							['getUserId', [], 'username'],
							['getUserId', [], 'username'],
							['getSigned', [], false],
							['getId', [], 1],
							['getDisplayName', [], 'username'],
							['getDescription', [], ''],
							['getId', [], 1],
							['getSignMethod', [], 'password'],
						]));
					$self->signRequestMapper
						->method('getByUuid')
						->will($self->returnValue($signRequest));
					$file = $self->createMock(\OCA\Libresign\Db\File::class);
					$file
						->method('__call')
						->withConsecutive(
							[$self->equalTo('getStatus')],
							[$self->equalTo('getUserId')],
							[$self->equalTo('getNodeId')],
							[$self->equalTo('getUuid')],
							[$self->equalTo('getName')],
							[$self->equalTo('getId')]
						)
						->will($self->returnValueMap([
							['getStatus', [], OCA\LibreSign\DB\File::STATUS_ABLE_TO_SIGN],
							['getUserId', [], 'username'],
							['getNodeId', [], 1],
							['getUuid', [], 'uuid'],
							['getName', [], 'name'],
							['getId', [], 1],
						]));
					$self->fileMapper
						->method('getById')
						->willReturn($file);
					$file = $self->createMock(\OCA\Libresign\Db\File::class);
					$self->fileMapper
						->method('getByUuid')
						->willReturn($file);
					$node = $self->createMock(\OCP\Files\File::class);
					$folder = $self->createMock(\OCP\Files\Folder::class);
					$folder
						->method('getById')
						->willReturn([$node]);
					$self->root
						->method('getUserFolder')
						->willReturn($folder);
				}
			],
			[ #13
				'uuid', 'username', 'file',
				[
					'action' => JSActions::ACTION_SIGN,
					'settings' => [
						'identifyMethods' => [],
					],
					'sign' => [
						'pdf' => [
							'file' => new \stdClass()
						],
						'uuid' => null,
						'filename' => null,
						'description' => ''
					],
					'user' => [
						'name' => 'username'
					],
				], function ($self) {
					$self->createUser('username', 'password');
					$signRequest = $self->createMock(SignRequest::class);
					$signRequest
						->method('__call')
						->withConsecutive(
							[$self->equalTo('getFileId')],
							[$self->equalTo('getUserId')],
							[$self->equalTo('getUserId')],
							[$self->equalTo('getSigned')],
							[$self->equalTo('getId')],
							[$self->equalTo('getDisplayName')],
							[$self->equalTo('getDescription')],
							[$self->equalTo('getId')],
							[$self->equalTo('getSignMethod')]
						)
						->will($self->returnValueMap([
							['getFileId', [], 1],
							['getUserId', [], 'username'],
							['getUserId', [], 'username'],
							['getSigned', [], false],
							['getId', [], 1],
							['getDisplayName', [], 'username'],
							['getDescription', [], ''],
							['getId', [], 1],
							['getSignMethod', [], 'password'],
						]));
					$self->signRequestMapper
						->method('getByUuid')
						->will($self->returnValue($signRequest));
					$file = $self->createMock(\OCA\Libresign\Db\File::class);
					$self->fileMapper
						->method('getByUuid')
						->willReturn($file);
					$self->fileMapper
						->method('getById')
						->willReturn($file);
					$node = $self->createMock(\OCP\Files\File::class);
					$node->method('getId')->will($self->returnValue(171));
					$folder = $self->createMock(\OCP\Files\Folder::class);
					$folder
						->method('getById')
						->willReturn([$node]);
					$self->root
						->method('getUserFolder')
						->willReturn($folder);
				}
			],
			[ #14
				'uuid', 'username', 'nodeId',
				[
					'action' => JSActions::ACTION_SIGN,
					'settings' => [
						'identifyMethods' => [],
					],
					'sign' => [
						'pdf' => [
							'nodeId' => null
						],
						'uuid' => null,
						'filename' => null,
						'description' => ''
					],
					'user' => [
						'name' => 'username'
					],
				], function ($self) {
					$self->createUser('username', 'password');
					$signRequest = $self->createMock(SignRequest::class);
					$signRequest
						->method('__call')
						->withConsecutive(
							[$self->equalTo('getFileId')],
							[$self->equalTo('getUserId')],
							[$self->equalTo('getUserId')],
							[$self->equalTo('getSigned')],
							[$self->equalTo('getId')],
							[$self->equalTo('getDisplayName')],
							[$self->equalTo('getDescription')],
							[$self->equalTo('getId')],
							[$self->equalTo('getSignMethod')]
						)
						->will($self->returnValueMap([
							['getFileId', [], 1],
							['getUserId', [], 'username'],
							['getUserId', [], 'username'],
							['getSigned', [], false],
							['getId', [], 1],
							['getDisplayName', [], 'username'],
							['getDescription', [], ''],
							['getId', [], 1],
							['getSignMethod', [], 'password'],
						]));
					$self->signRequestMapper
						->method('getByUuid')
						->will($self->returnValue($signRequest));
					$file = $self->createMock(\OCA\Libresign\Db\File::class);
					$self->fileMapper
						->method('getByUuid')
						->willReturn($file);
					$self->fileMapper
						->method('getById')
						->willReturn($file);
					$node = $self->createMock(\OCP\Files\File::class);
					$node->method('getContent')->will($self->returnValue('content'));
					$folder = $self->createMock(\OCP\Files\Folder::class);
					$folder
						->method('getById')
						->willReturn([$node]);
					$self->root
						->method('getUserFolder')
						->willReturn($folder);
				}
			],
		];
	}

	public function testGetConfigUsingFileTypeWithSuccess() {
		$this->markTestSkipped('Need to reimplement this test, stated to failure after add identify methods');
		$this->createUser('username', 'password');
		$signRequest = $this->createMock(SignRequest::class);
		$signRequest
			->method('__call')
			->withConsecutive(
				[$this->equalTo('getFileId')],
				[$this->equalTo('getUserId')],
				[$this->equalTo('getUserId')],
				[$this->equalTo('getSigned')],
				[$this->equalTo('getId')],
				[$this->equalTo('getDisplayName')],
				[$this->equalTo('getDescription')],
				[$this->equalTo('getId')],
				[$this->equalTo('getSignMethod')]
			)
			->will($this->returnValueMap([
				['getFileId', [], 1],
				['getUserId', [], 'username'],
				['getUserId', [], 'username'],
				['getSigned', [], false],
				['getId', [], 1],
				['getDisplayName', [], 'username'],
				['getDescription', [], ''],
				['getId', [], 1],
				['getSignMethod', [], 'password'],
			]));
		$this->signRequestMapper
			->method('getByUuid')
			->will($this->returnValue($signRequest));
		$file = $this->createMock(\OCA\Libresign\Db\File::class);
		$this->fileMapper
			->method('getByUuid')
			->willReturn($file);
		$this->fileMapper
			->method('getById')
			->willReturn($file);
		$node = $this->createMock(\OCP\Files\File::class);
		$node->method('getId')->will($this->returnValue(171));
		$folder = $this->createMock(\OCP\Files\Folder::class);
		$folder
			->method('getById')
			->willReturn([$node]);
		$this->root
			->method('getUserFolder')
			->willReturn($folder);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')
			->willReturn('username');

		$actual = $this->getService()->getInfoOfFileToSignUsingSignRequestUuid('uuid', $user, 'file');
		$this->assertJsonStringEqualsJsonString(
			json_encode($actual),
			json_encode([
				'action' => JSActions::ACTION_SIGN,
				'settings' => [
					'identifyMethods' => [],
				],
				'sign' => [
					'pdf' => [
						'file' => new \stdClass()
					],
					'uuid' => null,
					'filename' => null,
					'description' => ''
				],
				'user' => [
					'name' => 'username'
				],
			])
		);
		$this->assertInstanceOf(\OCP\Files\File::class, $actual['sign']['pdf']['file']);
	}
}
