<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use bovigo\vfs\vfsStream;
use OCA\Libresign\Db\AccountFileMapper;
use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Db\UserElement;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\FooterHandler;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Handler\SignEngine\Pkcs7Handler;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Service\SignFileService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
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

	private function getService(array $methods = []): SignFileService|MockObject {
		if ($methods) {
			return $this->getMockBuilder(SignFileService::class)
				->setConstructorArgs([
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
				])
				->onlyMethods($methods)
				->getMock();
		}
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
		$this->userManager->method('get')->willReturn($this->createMock(\OCP\IUser::class));

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
		$signRequest->setId(171);
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

	private function createSignRequestMock(array $methods): MockObject {
		$signRequest = $this->createMock(SignRequest::class);
		$signRequest->method('__call')->willReturnCallback(fn (string $method) =>
			$methods[$method] ?? null
		);
		return $signRequest;
	}

	#[DataProvider('providerGetSignatureParamsCommonName')]
	public function testGetSignatureParamsCommonName(
		array $certData,
		string $expectedIssuerCN,
		string $expectedSignerCN,
	): void {
		$service = $this->getService(['readCertificate']);

		$libreSignFile = new \OCA\Libresign\Db\File();
		$libreSignFile->setUuid('uuid');
		$service->setLibreSignFile($libreSignFile);

		$service->method('readCertificate')->willReturn($certData);
		$service->setCurrentUser(null);

		$signRequest = $this->createSignRequestMock([
			'getId' => 171,
			'getMetadata' => [],
		]);
		$service->setSignRequest($signRequest);

		$actual = $this->invokePrivate($service, 'getSignatureParams');

		$this->assertEquals($expectedIssuerCN, $actual['IssuerCommonName']);
		$this->assertEquals($expectedSignerCN, $actual['SignerCommonName']);
		$this->assertEquals('uuid', $actual['DocumentUUID']);
		$this->assertArrayHasKey('DocumentUUID', $actual);
		$this->assertArrayHasKey('LocalSignerTimezone', $actual);
		$this->assertArrayHasKey('LocalSignerSignatureDateTime', $actual);
	}

	public static function providerGetSignatureParamsCommonName(): array {
		return [
			'simple CNs' => [
				[
					'issuer' => ['CN' => 'LibreCode'],
					'subject' => ['CN' => 'Jane Doe'],
				],
				'LibreCode',
				'Jane Doe',
			],
			'empty CNs' => [
				[
					'issuer' => ['CN' => ''],
					'subject' => ['CN' => ''],
				],
				'',
				'',
			],
		];
	}

	#[DataProvider('providerGetSignatureParamsSignerEmail')]
	public function testGetSignatureParamsSignerEmail(
		array $certData,
		string $authenticatedUserEmail,
		array $expected,
	): void {
		$libreSignFile = new \OCA\Libresign\Db\File();
		$libreSignFile->setUuid('uuid');
		$service = $this->getService(['readCertificate']);
		$service->method('readCertificate')
			->willReturn($certData);
		$service->setLibreSignFile($libreSignFile);

		$signRequest = $this->createMock(SignRequest::class);
		$signRequest
			->method('__call')
			->willReturnCallback(fn (string $method) =>
				match ($method) {
					'getId' => 171,
					'getMetadata' => [],
				}
			);
		$service->setSignRequest($signRequest);

		if ($authenticatedUserEmail) {
			$user = $this->createMock(\OCP\IUser::class);
			$user->method('getEMailAddress')->willReturn($authenticatedUserEmail);
		} else {
			$user = null;
		}
		$service->setCurrentUser($user);

		$actual = $this->invokePrivate($service, 'getSignatureParams');
		if (isset($expected['SignerEmail'])) {
			$this->assertArrayHasKey('SignerEmail', $actual);
			$this->assertEquals($expected['SignerEmail'], $actual['SignerEmail']);
		} else {
			$this->assertArrayNotHasKey('SignerEmail', $actual);
		}
	}

	public static function providerGetSignatureParamsSignerEmail(): array {
		return [
			[
				[], '', [],
			],
			[
				[
					'extensions' => [
						'subjectAltName' => '',
					],
				],
				'',
				[
				],
			],
			[
				[
					'extensions' => [
						'subjectAltName' => 'email:test@email.coop',
					],
				],
				'',
				[
					'SignerEmail' => 'test@email.coop',
				],
			],
			[
				[
					'extensions' => [
						'subjectAltName' => 'email:test@email.coop,otherinfo',
					],
				],
				'',
				[
					'SignerEmail' => 'test@email.coop',
				],
			],
			[
				[
					'extensions' => [
						'subjectAltName' => 'otherinfo,email:test@email.coop',
					],
				],
				'',
				[
					'SignerEmail' => 'test@email.coop',
				],
			],
			[
				[
					'extensions' => [
						'subjectAltName' => 'otherinfo,email:test@email.coop,moreinfo',
					],
				],
				'',
				[
					'SignerEmail' => 'test@email.coop',
				],
			],
			[
				[
					'extensions' => [
						'subjectAltName' => 'test@email.coop',
					],
				],
				'',
				[
					'SignerEmail' => 'test@email.coop',
				],
			],
			[
				[],
				'test@email.coop',
				[
					'SignerEmail' => 'test@email.coop',
				],
			],
		];
	}

	#[DataProvider('providerGetSignatureParamsSignerEmailFallback')]
	public function testGetSignatureParamsSignerEmailFallback(
		string $methodName,
		string $email,
	): void {
		$service = $this->getService(['readCertificate']);

		$signRequest = $this->createMock(SignRequest::class);
		$signRequest->method('__call')->willReturn(171);
		$service->setSignRequest($signRequest);

		$identifyMethod = $this->createMock(IIdentifyMethod::class);
		$identifyMethod->method('getName')->willReturn($methodName);
		$entity = new IdentifyMethod();
		$entity->setIdentifierValue($email);
		$identifyMethod->method('getEntity')->willReturn($entity);
		$this->identifyMethodService->method('getIdentifiedMethod')->willReturn($identifyMethod);

		$actual = $this->invokePrivate($service, 'getSignatureParams');
		if (empty($email)) {
			$this->assertArrayNotHasKey('SignerEmail', $actual);
		} else {
			$this->assertArrayHasKey('SignerEmail', $actual);
			$this->assertEquals($email, $actual['SignerEmail']);
		}
	}

	public static function providerGetSignatureParamsSignerEmailFallback(): array {
		return [
			['account', '',],
			['email', 'signer@email.tld',],
		];
	}

	#[DataProvider('providerGetSignatureParamsMetadata')]
	public function testGetSignatureParamsMetadata(
		array $metadata,
		array $expected,
	): void {
		$service = $this->getService(['readCertificate']);
		$service->method('readCertificate')->willReturn([]);

		$signRequest = $this->createMock(SignRequest::class);
		$signRequest
			->method('__call')
			->willReturnCallback(fn (string $method) =>
				match ($method) {
					'getId' => 171,
					'getMetadata' => $metadata,
				}
			);
		$service->setSignRequest($signRequest);
		$actual = $this->invokePrivate($service, 'getSignatureParams');
		if (empty($expected)) {
			$this->assertArrayNotHasKey('SignerIP', $actual);
			$this->assertArrayNotHasKey('SignerUserAgent', $actual);
			return;
		}
		if (isset($expected['SignerIP'])) {
			$this->assertArrayHasKey('SignerIP', $actual);
			$this->assertEquals($expected['SignerIP'], $actual['SignerIP']);
		} else {
			$this->assertArrayNotHasKey('SignerIP', $actual);
		}
		if (isset($expected['SignerUserAgent'])) {
			$this->assertArrayHasKey('SignerUserAgent', $actual);
			$this->assertEquals($expected['SignerUserAgent'], $actual['SignerUserAgent']);
		} else {
			$this->assertArrayNotHasKey('SignerUserAgent', $actual);
		}
	}

	public static function providerGetSignatureParamsMetadata(): array {
		return [
			[[], []],
			[
				[
					'remote-address' => '',
					'user-agent' => '',
				],
				[
					'SignerIP' => '',
					'SignerUserAgent' => '',
				],
			],
			[
				[
					'remote-address' => '127.0.0.1',
					'user-agent' => 'Robot',
				],
				[
					'SignerIP' => '127.0.0.1',
					'SignerUserAgent' => 'Robot',
				],
			],
		];
	}

	#[DataProvider('providerSetVisibleElements')]
	public function testSetVisibleElements(
		array $signerList,
		array $databaseList,
		array $tempFiles,
		array $signatureFile,
		bool $canCreateSignature,
		?string $exception,
		bool $isAuthenticatedSigner,
	): void {
		$service = $this->getService();
		$signRequest = $this->createMock(SignRequest::class);
		$signRequest
			->method('__call')
			->willReturnCallback(fn (string $method) =>
				match ($method) {
					'getFileId' => 171,
					'getId' => 171,
				}
			);
		$service->setSignRequest($signRequest);

		$databaseList = array_map(function ($value) {
			$fileElement = new FileElement();
			$fileElement->setId($value['id']);
			return $fileElement;
		}, $databaseList);
		$this->fileElementMapper->method('getByFileIdAndSignRequestId')->willReturn($databaseList);

		$this->signerElementsService->method('canCreateSignature')->willReturn($canCreateSignature);

		if (!empty($signatureFile)) {
			$userElement = new UserElement();
			$userElement->setFileId(1);
			$this->userElementMapper->method('findOne')->willReturn($userElement);
		}

		$this->folderService->method('getFileById')
			->willReturnCallback(function ($id) use ($signatureFile) {
				if (isset($signatureFile[$id]) && $signatureFile[$id]) {
					$file = $this->getMockBuilder(\OCP\Files\File::class)->getMock();
					$file->method('getContent')->willReturn('');
					return $file;
				}
				throw new NotFoundException();
			});

		vfsStream::setup('home');
		$this->tempManager->method('getTemporaryFile')
			->willReturnCallback(function ($postFix) {
				preg_match('/.*(\d+).*/', $postFix, $matches);
				$path = 'vfs://home/_' . $matches[1] . '.png';
				return $path;
			});

		if ($exception) {
			$this->expectException($exception);
		}

		if ($isAuthenticatedSigner) {
			$currentUser = $this->createMock(\OCP\IUser::class);
		}
		$service->setCurrentUser($currentUser ?? null);

		$service->setVisibleElements($signerList);

		if (!$exception) {
			$visibleElements = $service->getVisibleElements();
			$this->assertCount(count($databaseList), $visibleElements);
			foreach ($databaseList as $key => $element) {
				$this->assertArrayHasKey($key, $visibleElements);
				$this->assertSame($element, $visibleElements[$key]->getFileElement());
				$this->assertEquals(
					isset($signerList[$key], $signerList[$key]['profileNodeId'], $tempFiles[$signerList[$key]['profileNodeId']])
						? $tempFiles[$signerList[$key]['profileNodeId']] . '/_' . $signerList[$key]['profileNodeId'] . '.png'
						: '',
					$visibleElements[$key]->getTempFile(),
				);
			}
		}
	}

	public static function providerSetVisibleElements(): array {
		$validDocumentId = 171;
		$validProfileNodeId = 1;
		$vfsPath = 'vfs://home';

		return [
			'empty list, can create signature' => self::createScenarioSetVisibleElements(
				signerList: [],
				databaseList: [],
				tempFiles: [],
				signatureFile: [],
				canCreateSignature: true,
				isAuthenticatedSigner: true
			),

			'empty list, cannot create signature' => self::createScenarioSetVisibleElements(
				signerList: [],
				databaseList: [],
				tempFiles: [],
				signatureFile: [],
				canCreateSignature: false,
				isAuthenticatedSigner: true
			),

			'valid signer with signature file' => self::createScenarioSetVisibleElements(
				signerList: [
					['documentElementId' => $validDocumentId, 'profileNodeId' => $validProfileNodeId],
				],
				databaseList: [
					['id' => $validDocumentId],
				],
				tempFiles: [$validProfileNodeId => $vfsPath],
				signatureFile: [$validProfileNodeId => true],
				canCreateSignature: true,
				isAuthenticatedSigner: true
			),

			'invalid signature file, with invalid field' => self::createScenarioSetVisibleElements(
				signerList: [
					['fake' => 'value', 'profileNodeId' => $validProfileNodeId],
				],
				databaseList: [
					['id' => $validDocumentId],
				],
				tempFiles: [$validProfileNodeId => $vfsPath],
				signatureFile: [$validProfileNodeId => false],
				canCreateSignature: true,
				isAuthenticatedSigner: true,
				expectedException: LibresignException::class
			),

			'invalid signature file, with invalid type of profileNodeId' => self::createScenarioSetVisibleElements(
				signerList: [
					['documentElementId' => $validDocumentId, 'profileNodeId' => 'not-a-number'],
				],
				databaseList: [
					['id' => $validDocumentId],
				],
				tempFiles: [$validProfileNodeId => $vfsPath],
				signatureFile: [$validProfileNodeId => false],
				canCreateSignature: true,
				isAuthenticatedSigner: true,
				expectedException: LibresignException::class
			),

			'invalid signature file' => self::createScenarioSetVisibleElements(
				signerList: [
					['documentElementId' => $validDocumentId, 'profileNodeId' => $validProfileNodeId],
				],
				databaseList: [
					['id' => $validDocumentId],
				],
				tempFiles: [$validProfileNodeId => $vfsPath],
				signatureFile: [$validProfileNodeId => false],
				canCreateSignature: true,
				isAuthenticatedSigner: true,
				expectedException: LibresignException::class
			),

			'missing profileNodeId throws exception' => self::createScenarioSetVisibleElements(
				signerList: [
					['documentElementId' => $validDocumentId],
				],
				databaseList: [
					['id' => $validDocumentId],
				],
				tempFiles: [],
				signatureFile: [],
				canCreateSignature: true,
				isAuthenticatedSigner: true,
				expectedException: LibresignException::class
			),

			'cannot create signature, visible element fallback' => self::createScenarioSetVisibleElements(
				signerList: [
					['documentElementId' => $validDocumentId],
				],
				databaseList: [
					['id' => $validDocumentId],
				],
				tempFiles: [],
				signatureFile: [],
				canCreateSignature: false,
				isAuthenticatedSigner: true
			),
			'no authenticated user, missing session file' => self::createScenarioSetVisibleElements(
				signerList: [['documentElementId' => $validDocumentId, 'profileNodeId' => $validProfileNodeId]],
				databaseList: [['id' => $validDocumentId]],
				tempFiles: [],
				signatureFile: [],
				canCreateSignature: true,
				isAuthenticatedSigner: false,
				expectedException: LibresignException::class,
			),

		];
	}

	private static function createScenarioSetVisibleElements(
		array $signerList,
		array $databaseList,
		array $tempFiles,
		array $signatureFile,
		bool $canCreateSignature,
		bool $isAuthenticatedSigner,
		?string $expectedException = null,
	): array {
		return [
			$signerList,
			$databaseList,
			$tempFiles,
			$signatureFile,
			$canCreateSignature,
			$expectedException,
			$isAuthenticatedSigner,
		];
	}
}
