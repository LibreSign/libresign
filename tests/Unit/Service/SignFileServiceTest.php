<?php

use OCA\Libresign\Db\AccountFileMapper;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Handler\Pkcs12Handler;
use OCA\Libresign\Handler\Pkcs7Handler;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\SignatureMethodService;
use OCA\Libresign\Service\SignFileService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\IRootFolder;
use OCP\Http\Client\IClientService;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;
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
	private IAppConfig $appConfig;
	private ValidateHelper|MockObject $validateHelper;
	private IRootFolder|MockObject $root;
	private IUserSession|MockObject $userSession;
	private IUserMountCache|MockObject $userMountCache;
	private FileElementMapper|MockObject $fileElementMapper;
	private UserElementMapper|MockObject $userElementMapper;
	private IEventDispatcher|MockObject $eventDispatcher;
	private IURLGenerator|MockObject $urlGenerator;
	private SignatureMethodService|MockObject $signMethod;
	private IdentifyMethodMapper|MockObject $identifyMethodMapper;
	private ITempManager|MockObject $tempManager;
	private IdentifyMethodService $identifyMethodService;
	private ITimeFactory $timeFactory;

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
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->validateHelper = $this->createMock(\OCA\Libresign\Helper\ValidateHelper::class);
		$this->root = $this->createMock(\OCP\Files\IRootFolder::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->userMountCache = $this->createMock(IUserMountCache::class);
		$this->fileElementMapper = $this->createMock(FileElementMapper::class);
		$this->userElementMapper = $this->createMock(UserElementMapper::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->signMethod = $this->createMock(SignatureMethodService::class);
		$this->identifyMethodMapper = $this->createMock(IdentifyMethodMapper::class);
		$this->tempManager = $this->createMock(ITempManager::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
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
			$this->appConfig,
			$this->validateHelper,
			$this->root,
			$this->userSession,
			$this->userMountCache,
			$this->fileElementMapper,
			$this->userElementMapper,
			$this->eventDispatcher,
			$this->urlGenerator,
			$this->signMethod,
			$this->identifyMethodMapper,
			$this->tempManager,
			$this->identifyMethodService,
			$this->timeFactory,
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
}
