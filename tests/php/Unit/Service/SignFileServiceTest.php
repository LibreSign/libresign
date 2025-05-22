<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\Db\AccountFileMapper;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Handler\FooterHandler;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Handler\SignEngine\Pkcs7Handler;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Service\SignFileService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * @group DB
 */
final class SignFileServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IL10N&MockObject $l10n;
	private Pkcs7Handler&MockObject $pkcs7Handler;
	private Pkcs12Handler&MockObject $pkcs12Handler;
	private FooterHandler&MockObject $footerHandler;
	private FileMapper&MockObject $fileMapper;
	private SignRequestMapper&MockObject $signRequestMapper;
	private AccountFileMapper&MockObject $accountFileMapper;
	private IClientService&MockObject $clientService;
	private IUserManager&MockObject $userManager;
	private FolderService&MockObject $folderService;
	private LoggerInterface&MockObject $logger;
	private IAppConfig $appConfig;
	private ValidateHelper&MockObject $validateHelper;
	private SignerElementsService&MockObject $signerElementsService;
	private IRootFolder&MockObject $root;
	private IUserSession&MockObject $userSession;
	private IDateTimeZone $dateTimeZone;
	private FileElementMapper&MockObject $fileElementMapper;
	private UserElementMapper&MockObject $userElementMapper;
	private IEventDispatcher&MockObject $eventDispatcher;
	private IURLGenerator&MockObject $urlGenerator;
	private IdentifyMethodMapper&MockObject $identifyMethodMapper;
	private ITempManager&MockObject $tempManager;
	private IdentifyMethodService&MockObject $identifyMethodService;
	private ITimeFactory&MockObject $timeFactory;

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
		$this->footerHandler = $this->createMock(FooterHandler::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->folderService = $this->createMock(FolderService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->appConfig = $this->getMockAppConfig();
		$this->validateHelper = $this->createMock(\OCA\Libresign\Helper\ValidateHelper::class);
		$this->signerElementsService = $this->createMock(SignerElementsService::class);
		$this->root = $this->createMock(\OCP\Files\IRootFolder::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->dateTimeZone = \OCP\Server::get(IDateTimeZone::class);
		$this->fileElementMapper = $this->createMock(FileElementMapper::class);
		$this->userElementMapper = $this->createMock(UserElementMapper::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
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
			$this->footerHandler,
			$this->folderService,
			$this->clientService,
			$this->userManager,
			$this->logger,
			$this->appConfig,
			$this->validateHelper,
			$this->signerElementsService,
			$this->root,
			$this->userSession,
			$this->dateTimeZone,
			$this->fileElementMapper,
			$this->userElementMapper,
			$this->eventDispatcher,
			$this->urlGenerator,
			$this->identifyMethodMapper,
			$this->tempManager,
			$this->identifyMethodService,
			$this->timeFactory,
		);
	}

	public function testCanDeleteRequestSignatureWhenDocumentAlreadySigned():void {
		$file = $this->createMock(\OCA\Libresign\Db\File::class);
		$file->method('__call')->with($this->equalTo('getId'))->will($this->returnValue(1));
		$this->fileMapper->method('getByUuid')->will($this->returnValue($file));
		$signRequest = $this->createMock(\OCA\Libresign\Db\SignRequest::class);
		$signRequest
			->method('__call')
			->willReturnCallback(fn (string $method) =>
				match ($method) {
					'getSigned' => '2021-01-01 01:01:01',
				}
			);
		$this->signRequestMapper->method('getByFileUuid')->will($this->returnValue([$signRequest]));
		$this->expectExceptionMessage('Document already signed');
		$this->getService()->canDeleteRequestSignature(['uuid' => 'valid']);
	}

	public function testCanDeleteRequestSignatureWhenNoSignatureWasRequested():void {
		$file = $this->createMock(\OCA\Libresign\Db\File::class);
		$file->method('__call')->with($this->equalTo('getId'))->will($this->returnValue(1));
		$this->fileMapper->method('getByUuid')->will($this->returnValue($file));
		$signRequest = $this->createMock(\OCA\Libresign\Db\SignRequest::class);
		$signRequest
			->method('__call')
			->willReturnCallback(fn (string $method) =>
				match ($method) {
					'getSigned' => null,
					'getId' => 171,
				}
			);
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

	public function testNotifyCallback():void {
		$libreSignFile = new \OCA\Libresign\Db\File();
		$libreSignFile->setCallback('https://test.coop');
		$service = $this->getService();
		$service->setLibreSignFile($libreSignFile);
		$file = $this->createMock(\OCP\Files\File::class);
		$actual = $service->notifyCallback($file);
		$this->assertNull($actual);
	}

	public function testSignWithFileNotFound():void {
		$this->expectExceptionMessage('File not found');

		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('username');

		$this->root->method('getUserFolder')
			->willReturn($this->root);
		$this->root->method('getById')
			->willReturn([]);

		$signRequest = new \OCA\Libresign\Db\SignRequest();
		$this->getService()
			->setLibreSignFile($file)
			->setSignRequest($signRequest)
			->setPassword('password')
			->sign();
	}

	/**
	 * @dataProvider dataSignWithSuccess
	 */
	public function testSignWithSuccess(string $mimetype, string $filename, string $extension):void {
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('username');

		$nextcloudFile = $this->createMock(\OCP\Files\File::class);
		$nextcloudFile->method('getMimeType')->willReturn($mimetype);
		$nextcloudFile->method('getExtension')->willReturn($extension);
		$nextcloudFile->method('getPath')->willReturn($filename);
		$nextcloudFile->method('getContent')->willReturn('fake content');
		$nextcloudFile->method('getId')->willReturn(171);

		$user = $this->createMock(\OCP\IUser::class);
		$user->method('getUID')->willReturn('john.doe');
		$nextcloudFile->method('getOwner')->willReturn($user);

		$this->root->method('getUserFolder')->willReturn($this->root);
		$this->root->method('getById')->willReturn([$nextcloudFile]);
		$this->root->method('newFile')->willReturn($nextcloudFile);

		$nextcloudFolder = $this->createMock(\OCP\Files\Folder::class);
		$nextcloudFolder->method('newFile')->willReturn($nextcloudFile);
		$this->root->method('getFirstNodeById')->willReturn($nextcloudFolder);

		$this->pkcs12Handler->method('setInputFile')->willReturn($this->pkcs12Handler);
		$this->pkcs12Handler->method('setCertificate')->willReturn($this->pkcs12Handler);
		$this->pkcs12Handler->method('setVisibleElements')->willReturn($this->pkcs12Handler);
		$this->pkcs12Handler->method('setSignatureParams')->willReturn($this->pkcs12Handler);
		$this->pkcs12Handler->method('setPassword')->willReturn($this->pkcs12Handler);
		$this->pkcs12Handler->method('readCertificate')->willReturn([
			'issuer' => ['CN' => 'Acme Cooperative'],
			'subject' => ['CN' => 'John Doe'],
		]);
		$this->pkcs12Handler->method('sign')->willReturn($nextcloudFile);

		$this->pkcs7Handler->method('setInputFile')->willReturn($this->pkcs12Handler);
		$this->pkcs7Handler->method('setCertificate')->willReturn($this->pkcs12Handler);
		$this->pkcs7Handler->method('setPassword')->willReturn($this->pkcs12Handler);
		$this->pkcs7Handler->method('sign')->willReturn($nextcloudFile);

		$signRequest = new \OCA\Libresign\Db\SignRequest();
		$signRequest->setFileId(171);
		$this->getService()
			->setLibreSignFile($file)
			->setSignRequest($signRequest)
			->setPassword('password')
			->sign();
		$this->assertTrue(true);
	}

	public static function dataSignWithSuccess(): array {
		return [
			['application/pdf', 'file.PDF', 'PDF'],
			['application/pdf', 'file.pdf', 'pdf'],
		];
	}

	#[DataProvider('providerStoreUserMetadata')]
	public function testStoreUserMetadata(bool $collectMetadata, ?array $previous, array $new, ?array $expected): void {
		$signRequest = new \OCA\Libresign\Db\SignRequest();
		$this->appConfig->setValueBool('libresign', 'collect_metadata', $collectMetadata);
		$signRequest->setMetadata($previous);
		$this->getService()
			->setSignRequest($signRequest)
			->storeUserMetadata($new);
		$this->assertEquals(
			$expected,
			$signRequest->getMetadata()
		);
	}

	public static function providerStoreUserMetadata(): array {
		return [
			// don't collect metadata
			[false, null, [],                  null],
			[false, null, ['b' => 2],          null],
			[false, null, ['b' => null],       null],
			[false, null, ['b' => []],         null],
			[false, null, ['b' => ['']],       null],
			[false, null, ['b' => ['b' => 1]], null],
			// collect metadata without previous value
			[true, null, [],                  null],
			[true, null, ['b' => 2],          ['b' => 2]],
			[true, null, ['b' => null],       ['b' => null]],
			[true, null, ['b' => []],         ['b' => []]],
			[true, null, ['b' => ['']],       ['b' => ['']]],
			[true, null, ['b' => ['b' => 1]], ['b' => ['b' => 1]]],
			// collect metadata with previous value
			[true, ['a' => 1], ['a' => 2],          ['a' => 2]],
			[true, ['a' => 1], ['a' => null],       ['a' => null]],
			[true, ['a' => 1], ['a' => []],         ['a' => []]],
			[true, ['a' => 1], ['a' => ['']],       ['a' => ['']]],
			[true, ['a' => 1], ['a' => ['b' => 1]], ['a' => ['b' => 1]]],
			[true, ['a' => 1], ['b' => 2],          ['a' => 1, 'b' => 2]],
		];
	}

}
