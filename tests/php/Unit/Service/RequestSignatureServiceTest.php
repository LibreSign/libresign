<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\DocMdpLevel;
use OCA\Libresign\Enum\SignatureFlow;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\DocMdpHandler;
use OCA\Libresign\Helper\FileUploadHelper;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\DocMdp\ConfigService as DocMdpConfigService;
use OCA\Libresign\Service\Envelope\EnvelopeFileRelocator;
use OCA\Libresign\Service\Envelope\EnvelopeService;
use OCA\Libresign\Service\File\Pdf\PdfMetadataExtractor;
use OCA\Libresign\Service\FileElementService;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\FileStatusService;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\DocMdp\DocMdpPolicy;
use OCA\Libresign\Service\Policy\Provider\Signature\SignatureFlowPolicy;
use OCA\Libresign\Service\RequestSignatureService;
use OCA\Libresign\Service\SequentialSigningService;
use OCA\Libresign\Service\SignRequest\SignRequestService;
use OCA\Libresign\Service\SignRequest\StatusCacheService;
use OCA\Libresign\Service\SignRequest\StatusService;
use OCA\Libresign\Service\SignRequest\StatusUpdatePolicy;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Folder;
use OCP\Files\IMimeTypeDetector;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class RequestSignatureServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private FileService&MockObject $fileService;
	private IL10N&MockObject $l10n;
	private FileMapper&MockObject $fileMapper;
	private SignRequestMapper&MockObject $signRequestMapper;
	private IdentifyMethodMapper&MockObject $identifyMethodMapper;
	private IUser&MockObject $user;
	private IClientService&MockObject $clientService;
	private IUserManager&MockObject $userManager;
	private FolderService&MockObject $folderService;
	private ValidateHelper&MockObject $validateHelper;
	private FileElementMapper&MockObject $fileElementMapper;
	private FileElementService&MockObject $fileElementService;
	private IdentifyMethodService&MockObject $identifyMethodService;
	private PdfMetadataExtractor&MockObject $pdfMetadataExtractor;
	private IMimeTypeDetector&MockObject $mimeTypeDetector;
	private IClientService&MockObject $client;
	private DocMdpHandler&MockObject $docMdpHandler;
	private LoggerInterface&MockObject $loggerInterface;
	private SequentialSigningService&MockObject $sequentialSigningService;
	private IAppConfig&MockObject $appConfig;
	private IEventDispatcher&MockObject $eventDispatcher;
	private FileStatusService&MockObject $fileStatusService;
	private DocMdpConfigService&MockObject $docMdpConfigService;
	private EnvelopeService&MockObject $envelopeService;
	private EnvelopeFileRelocator&MockObject $envelopeFileRelocator;
	private FileUploadHelper&MockObject $uploadHelper;
	private SignRequestService&MockObject $signRequestService;
	private PolicyService&MockObject $policyService;

	public function setUp(): void {
		parent::setUp();
		$this->fileService = $this->createMock(FileService::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->willReturnArgument(0);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->identifyMethodMapper = $this->createMock(IdentifyMethodMapper::class);
		$this->user = $this->createMock(IUser::class);
		$this->folderService = $this->createMock(FolderService::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->folderService = $this->createMock(FolderService::class);
		$this->validateHelper = $this->createMock(ValidateHelper::class);
		$this->fileElementMapper = $this->createMock(FileElementMapper::class);
		$this->fileElementService = $this->createMock(FileElementService::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->pdfMetadataExtractor = $this->createMock(PdfMetadataExtractor::class);
		$this->mimeTypeDetector = $this->createMock(IMimeTypeDetector::class);
		$this->client = $this->createMock(IClientService::class);
		$this->docMdpHandler = $this->createMock(DocMdpHandler::class);
		$this->loggerInterface = $this->createMock(LoggerInterface::class);
		$this->sequentialSigningService = $this->createMock(SequentialSigningService::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->fileStatusService = $this->createMock(FileStatusService::class);
		$this->docMdpConfigService = $this->createMock(DocMdpConfigService::class);
		$this->envelopeService = $this->createMock(EnvelopeService::class);
		$this->envelopeFileRelocator = $this->createMock(EnvelopeFileRelocator::class);
		$this->uploadHelper = $this->createMock(FileUploadHelper::class);
		$this->signRequestService = $this->createMock(SignRequestService::class);
		$this->policyService = $this->createMock(PolicyService::class);
	}

	private function getService(array $methods = []): RequestSignatureService|MockObject {
		if ($methods) {
			return $this->getMockBuilder(RequestSignatureService::class)
				->setConstructorArgs([
					$this->fileService,
					$this->l10n,
					$this->identifyMethodService,
					$this->signRequestMapper,
					$this->userManager,
					$this->fileMapper,
					$this->identifyMethodMapper,
					$this->pdfMetadataExtractor,
					$this->fileElementService,
					$this->fileElementMapper,
					$this->folderService,
					$this->mimeTypeDetector,
					$this->validateHelper,
					$this->client,
					$this->docMdpHandler,
					$this->loggerInterface,
					$this->sequentialSigningService,
					$this->appConfig,
					$this->eventDispatcher,
					$this->fileStatusService,
					$this->docMdpConfigService,
					$this->envelopeService,
					$this->envelopeFileRelocator,
					$this->uploadHelper,
					$this->signRequestService,
					$this->policyService,
				])
				->onlyMethods($methods)
				->getMock();
		}

		return new RequestSignatureService(
			$this->fileService,
			$this->l10n,
			$this->identifyMethodService,
			$this->signRequestMapper,
			$this->userManager,
			$this->fileMapper,
			$this->identifyMethodMapper,
			$this->pdfMetadataExtractor,
			$this->fileElementService,
			$this->fileElementMapper,
			$this->folderService,
			$this->mimeTypeDetector,
			$this->validateHelper,
			$this->client,
			$this->docMdpHandler,
			$this->loggerInterface,
			$this->sequentialSigningService,
			$this->appConfig,
			$this->eventDispatcher,
			$this->fileStatusService,
			$this->docMdpConfigService,
			$this->envelopeService,
			$this->envelopeFileRelocator,
			$this->uploadHelper,
			$this->signRequestService,
			$this->policyService,
		);
	}

	public function testSaveFilesUsesSaveForSingleFile(): void {
		$service = $this->getService(['save']);

		$fileEntity = new \OCA\Libresign\Db\File();
		$fileEntity->setId(123);

		$service->expects($this->once())
			->method('save')
			->with($this->callback(function (array $payload): bool {
				return $payload['name'] === 'My File'
					&& $payload['status'] === \OCA\Libresign\Enum\FileStatus::DRAFT->value
					&& $payload['settings'] === ['path' => '/docs'];
			}))
			->willReturn($fileEntity);

		$result = $service->saveFiles([
			'files' => [[
				'name' => 'My File',
				'uploadedFile' => 'payload',
			]],
			'name' => 'My File',
			'userManager' => $this->user,
			'settings' => ['path' => '/docs'],
		]);

		$this->assertSame($fileEntity, $result['file']);
		$this->assertSame([$fileEntity], $result['children']);
	}

	public function testSaveFilesUsesEnvelopeForMultipleFiles(): void {
		$service = $this->getService(['saveEnvelope']);

		$envelope = new \OCA\Libresign\Db\File();
		$envelope->setId(77);
		$fileA = new \OCA\Libresign\Db\File();
		$fileB = new \OCA\Libresign\Db\File();

		$service->expects($this->once())
			->method('saveEnvelope')
			->willReturn([
				'envelope' => $envelope,
				'files' => [$fileA, $fileB],
			]);

		$result = $service->saveFiles([
			'files' => [
				['name' => 'A', 'uploadedFile' => 'payload-a'],
				['name' => 'B', 'uploadedFile' => 'payload-b'],
			],
			'name' => 'Envelope',
			'userManager' => $this->user,
			'settings' => [],
		]);

		$this->assertSame($envelope, $result['file']);
		$this->assertSame([$fileA, $fileB], $result['children']);
	}

	public function testValidateNameIsMandatory():void {
		$this->expectExceptionMessage('File name is required');

		$this->getService()->validateNewRequestToFile([
			'file' => ['url' => 'qwert'],
			'userManager' => $this->user
		]);
	}

	public function testValidateEmptyUserCollection():void {
		$this->expectExceptionMessage('Empty signers list');

		$response = $this->createMock(IResponse::class);
		$response
			->method('getHeaders')
			->willReturn(['Content-Type' => ['application/pdf']]);
		$client = $this->createMock(IClient::class);
		$client
			->method('get')
			->willReturn($response);
		$this->clientService
			->method('newClient')
			->willReturn($client);

		$this->getService()->validateNewRequestToFile([
			'file' => ['url' => 'http://test.coop'],
			'name' => 'test',
			'userManager' => $this->user
		]);
	}

	public function testValidateEmptyUsersCollection():void {
		$this->expectExceptionMessage('Empty signers list');

		$this->getService()->validateNewRequestToFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/pdfs/small_valid.pdf'))],
			'name' => 'test',
			'userManager' => $this->user
		]);
	}

	public function testValidateUserCollectionNotArray():void {
		$this->expectExceptionMessage('Signers list needs to be an array');

		$this->getService()->validateNewRequestToFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/pdfs/small_valid.pdf'))],
			'name' => 'test',
			'signers' => 'asdfg',
			'userManager' => $this->user
		]);
	}

	public function testValidateUserEmptyCollection():void {
		$this->expectExceptionMessage('Empty signers list');

		$this->getService()->validateNewRequestToFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/pdfs/small_valid.pdf'))],
			'name' => 'test',
			'signers' => null,
			'userManager' => $this->user
		]);
	}

	public function testValidateSuccess():void {
		$actual = $this->getService()->validateNewRequestToFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/pdfs/small_valid.pdf'))],
			'name' => 'test',
			'signers' => [
				['identifyMethods' => [['method' => 'email', 'value' => 'jhondoe@test.coop']]]
			],
			'userManager' => $this->user
		]);
		$this->assertNull($actual);
	}

	public function testValidateSignersAllowsIdentifyMethodsPayload(): void {
		$service = $this->getService();
		$service->validateSigners([
			'signers' => [
				[
					'identifyMethods' => [
						['method' => 'email', 'value' => 'test@example.com'],
					],
				],
			],
		]);
		$this->addToAssertionCount(1);
	}

	public function testValidateSignersRejectsLegacyIdentifyPayload(): void {
		$this->expectExceptionMessage('No identify methods for signer');
		$this->validateHelper
			->method('validateIdentifySigners')
			->willThrowException(new LibresignException('No identify methods for signer'));

		$service = $this->getService();
		$service->validateSigners([
			'signers' => [
				['identify' => ['email' => 'test@example.com']],
			],
		]);
	}

	public function testAssociateToSignersCreatesSignRequestsUsingIdentifyMethods(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setId(77);

		$data = [
			'status' => 9,
			'signers' => [[
				'displayName' => 'John Doe',
				'description' => 'Needs review',
				'notify' => 0,
				'status' => 4,
				'signingOrder' => 3,
				'identifyMethods' => [
					['method' => 'email', 'value' => 'john@example.com'],
					['method' => 'account', 'value' => 'john'],
				],
			]],
		];

		$this->validateHelper
			->method('normalizeRequestSigners')
			->willReturnCallback(static fn (array $signers): array => $signers);

		$this->signRequestMapper
			->method('getByFileId')
			->with(77)
			->willReturn([]);

		$this->identifyMethodService
			->expects($this->once())
			->method('clearCache');

		$this->sequentialSigningService
			->expects($this->once())
			->method('resetOrderCounter');

		$this->sequentialSigningService
			->expects($this->once())
			->method('determineSigningOrder')
			->with(3)
			->willReturn(3);

		$expectedCalls = [
			[['email' => 'john@example.com'], 'John Doe', 'Needs review', false, 77, 3, 9, 4],
			[['account' => 'john'], 'John Doe', 'Needs review', false, 77, 3, 9, 4],
		];

		$this->signRequestService
			->expects($this->exactly(2))
			->method('createOrUpdateSignRequest')
			->willReturnCallback(function (
				array $identifyMethods,
				string $displayName,
				string $description,
				bool $notify,
				int $fileId,
				int $signingOrder,
				?int $fileStatus,
				?int $signerStatus,
			) use (&$expectedCalls): SignRequest {
				$expectedCall = array_shift($expectedCalls);
				$this->assertNotNull($expectedCall);
				[$expectedIdentifyMethods, $expectedDisplayName, $expectedDescription, $expectedNotify, $expectedFileId, $expectedSigningOrder, $expectedFileStatus, $expectedSignerStatus] = $expectedCall;
				$this->assertSame($expectedIdentifyMethods, $identifyMethods);
				$this->assertSame($expectedDisplayName, $displayName);
				$this->assertSame($expectedDescription, $description);
				$this->assertSame($expectedNotify, $notify);
				$this->assertSame($expectedFileId, $fileId);
				$this->assertSame($expectedSigningOrder, $signingOrder);
				$this->assertSame($expectedFileStatus, $fileStatus);
				$this->assertSame($expectedSignerStatus, $signerStatus);

				return new SignRequest();
			});

		$actual = self::invokePrivate($this->getService(), 'associateToSigners', [$data, $file]);

		$this->assertCount(2, $actual);
		$this->assertSame([], $expectedCalls);
	}

	public function testDeleteIdentifyMethodIfNotExitsKeepsMatchingIdentifyMethods(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setId(77);

		$signRequest = new SignRequest();
		$signRequest->setId(501);

		$entity = new IdentifyMethod();
		$entity->setIdentifierKey('email');
		$entity->setIdentifierValue('john@example.com');

		$identifyMethod = $this->createMock(\OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod::class);
		$identifyMethod->method('getEntity')->willReturn($entity);

		$this->validateHelper
			->expects($this->once())
			->method('normalizeRequestSigners')
			->with([['identifyMethods' => [['method' => 'email', 'value' => 'john@example.com']]]])
			->willReturn([['identifyMethods' => [['method' => 'email', 'value' => 'john@example.com']]]]);

		$this->signRequestMapper
			->expects($this->once())
			->method('getByFileId')
			->with(77)
			->willReturn([$signRequest]);

		$this->identifyMethodService
			->expects($this->once())
			->method('getIdentifyMethodsFromSignRequestId')
			->with(501)
			->willReturn(['email' => [$identifyMethod]]);

		$service = $this->getMockBuilder(RequestSignatureService::class)
			->setConstructorArgs([
				$this->fileService,
				$this->l10n,
				$this->identifyMethodService,
				$this->signRequestMapper,
				$this->userManager,
				$this->fileMapper,
				$this->identifyMethodMapper,
				$this->pdfMetadataExtractor,
				$this->fileElementService,
				$this->fileElementMapper,
				$this->folderService,
				$this->mimeTypeDetector,
				$this->validateHelper,
				$this->client,
				$this->docMdpHandler,
				$this->loggerInterface,
				$this->sequentialSigningService,
				$this->appConfig,
				$this->eventDispatcher,
				$this->fileStatusService,
				$this->docMdpConfigService,
				$this->envelopeService,
				$this->envelopeFileRelocator,
				$this->uploadHelper,
				$this->signRequestService,
				$this->policyService,
			])
			->onlyMethods(['unassociateToUser'])
			->getMock();

		$service->expects($this->never())
			->method('unassociateToUser');

		self::invokePrivate($service, 'deleteIdentifyMethodIfNotExits', [
			[['identifyMethods' => [['method' => 'email', 'value' => 'john@example.com']]]],
			$file,
		]);
	}

	public function testDeleteIdentifyMethodIfNotExitsRemovesMissingIdentifyMethods(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setId(77);

		$signRequest = new SignRequest();
		$signRequest->setId(501);

		$entity = new IdentifyMethod();
		$entity->setIdentifierKey('email');
		$entity->setIdentifierValue('old@example.com');

		$identifyMethod = $this->createMock(\OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod::class);
		$identifyMethod->method('getEntity')->willReturn($entity);

		$this->validateHelper
			->expects($this->once())
			->method('normalizeRequestSigners')
			->with([['identifyMethods' => [['method' => 'email', 'value' => 'john@example.com']]]])
			->willReturn([['identifyMethods' => [['method' => 'email', 'value' => 'john@example.com']]]]);

		$this->signRequestMapper
			->expects($this->once())
			->method('getByFileId')
			->with(77)
			->willReturn([$signRequest]);

		$this->identifyMethodService
			->expects($this->once())
			->method('getIdentifyMethodsFromSignRequestId')
			->with(501)
			->willReturn(['email' => [$identifyMethod]]);

		$service = $this->getMockBuilder(RequestSignatureService::class)
			->setConstructorArgs([
				$this->fileService,
				$this->l10n,
				$this->identifyMethodService,
				$this->signRequestMapper,
				$this->userManager,
				$this->fileMapper,
				$this->identifyMethodMapper,
				$this->pdfMetadataExtractor,
				$this->fileElementService,
				$this->fileElementMapper,
				$this->folderService,
				$this->mimeTypeDetector,
				$this->validateHelper,
				$this->client,
				$this->docMdpHandler,
				$this->loggerInterface,
				$this->sequentialSigningService,
				$this->appConfig,
				$this->eventDispatcher,
				$this->fileStatusService,
				$this->docMdpConfigService,
				$this->envelopeService,
				$this->envelopeFileRelocator,
				$this->uploadHelper,
				$this->signRequestService,
				$this->policyService,
			])
			->onlyMethods(['unassociateToUser'])
			->getMock();

		$service->expects($this->once())
			->method('unassociateToUser')
			->with(77, 501);

		self::invokePrivate($service, 'deleteIdentifyMethodIfNotExits', [
			[['identifyMethods' => [['method' => 'email', 'value' => 'john@example.com']]]],
			$file,
		]);
	}

	/**
	 * @dataProvider dataGetFileMetadata
	 */
	public function testGetFileMetadata(string $extension, array $expected): void {
		$inputFile = $this->createMock(\OC\Files\Node\File::class);
		$inputFile
			->method('getExtension')
			->willReturn($extension);
		$this->pdfMetadataExtractor
			->method('setFile')
			->willReturn($this->pdfMetadataExtractor);
		$this->pdfMetadataExtractor
			->method('getPageDimensions')
			->willReturn(['isValid' => true]);
		$actual = self::invokePrivate($this->getService(), 'getFileMetadata', [$inputFile]);
		$this->assertEquals($expected, $actual);
	}

	public static function dataGetFileMetadata(): array {
		return [
			['pdfff', ['extension' => 'pdfff']],
			['', []],
			['PDF', ['extension' => 'pdf', 'isValid' => true, 'pdfVersion' => '']],
		];
	}

	/**
	 * @dataProvider dataSaveVisibleElements
	 */
	public function testSaveVisibleElements($elements):void {
		$libreSignFile = new \OCA\Libresign\Db\File();
		if (!empty($elements)) {
			$libreSignFile->setId(1);
			$this->fileElementService
				->expects($this->exactly(count($elements)))
				->method('saveVisibleElement');
		}
		$actual = self::invokePrivate($this->getService(), 'saveVisibleElements', [
			['visibleElements' => $elements], $libreSignFile
		]);
		$this->assertSameSize($elements, $actual);
	}

	public static function dataSaveVisibleElements():array {
		return [
			[[]],
			[[['uid' => 1]]],
			[[['uid' => 1], ['uid' => 1]]],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('providerSaveVisibleElementsOfEnvelope')]
	public function testSaveVisibleElementsOfEnvelopeResolvesSignRequestId(
		int $inputSignRequestId,
		int $resolvedSignRequestFileId,
		int $expectedPersistedSignRequestId,
		array $childrenSignRequests,
		bool $shouldTranslate,
	): void {
		$libreSignFile = new \OCA\Libresign\Db\File();
		$libreSignFile->setId(544);
		$libreSignFile->setNodeType('envelope');

		$resolvedSignRequest = new SignRequest();
		$resolvedSignRequest->setId($inputSignRequestId);
		$resolvedSignRequest->setFileId($resolvedSignRequestFileId);

		$this->signRequestMapper
			->expects($this->once())
			->method('getById')
			->with($inputSignRequestId)
			->willReturn($resolvedSignRequest);

		if ($shouldTranslate) {
			$this->signRequestMapper
				->expects($this->once())
				->method('getByEnvelopeChildrenAndIdentifyMethod')
				->with(544, $inputSignRequestId)
				->willReturn($childrenSignRequests);
		} else {
			$this->signRequestMapper
				->expects($this->never())
				->method('getByEnvelopeChildrenAndIdentifyMethod');
		}

		$this->fileElementService
			->expects($this->once())
			->method('saveVisibleElement')
			->with($this->callback(function (array $element) use ($expectedPersistedSignRequestId): bool {
				return $element['fileId'] === 545
					&& $element['signRequestId'] === $expectedPersistedSignRequestId;
			}))
			->willReturn(new FileElement());

		$actual = self::invokePrivate($this->getService(), 'saveVisibleElements', [[
			'visibleElements' => [[
				'fileId' => 545,
				'signRequestId' => $inputSignRequestId,
				'coordinates' => ['page' => 1, 'left' => 100, 'top' => 20, 'width' => 300, 'height' => 100],
			]],
		], $libreSignFile]);

		$this->assertCount(1, $actual);
	}

	public static function providerSaveVisibleElementsOfEnvelope(): array {
		$childSignRequest603 = new SignRequest();
		$childSignRequest603->setId(603);
		$childSignRequest603->setFileId(545);

		$otherChildSignRequest = new SignRequest();
		$otherChildSignRequest->setId(999);
		$otherChildSignRequest->setFileId(999);

		return [
			'keeps child signRequestId when already points to file child' => [
				'inputSignRequestId' => 603,
				'resolvedSignRequestFileId' => 545,
				'expectedPersistedSignRequestId' => 603,
				'childrenSignRequests' => [],
				'shouldTranslate' => false,
			],
			'translates envelope signRequestId to matching child signRequestId' => [
				'inputSignRequestId' => 602,
				'resolvedSignRequestFileId' => 544,
				'expectedPersistedSignRequestId' => 603,
				'childrenSignRequests' => [$otherChildSignRequest, $childSignRequest603],
				'shouldTranslate' => true,
			],
		];
	}

	public function testParallelFlowIgnoresSignerDraftStatusWhenFileIsAbleToSign(): void {
		$sequentialSigningService = $this->createMock(SequentialSigningService::class);
		$sequentialSigningService
			->method('isOrderedNumericFlow')
			->willReturn(false); // Parallel flow

		$fileStatusService = $this->createMock(FileStatusService::class);
		$statusCacheService = $this->createMock(StatusCacheService::class);
		$statusService = new StatusService(
			$sequentialSigningService,
			$fileStatusService,
			$statusCacheService,
			new StatusUpdatePolicy()
		);

		// File status is ABLE_TO_SIGN (1)
		$fileStatus = \OCA\Libresign\Enum\FileStatus::ABLE_TO_SIGN->value;

		// Signer status is DRAFT (0) - as sent by frontend
		$signerStatus = \OCA\Libresign\Enum\SignRequestStatus::DRAFT->value;

		$result = $statusService->determineInitialStatus(
			1, // signingOrder
			123, // fileId
			$fileStatus,
			$signerStatus,
			null, // currentStatus
		);

		// In parallel flow with ABLE_TO_SIGN file status, signer should be ABLE_TO_SIGN
		$this->assertEquals(
			\OCA\Libresign\Enum\SignRequestStatus::ABLE_TO_SIGN,
			$result,
			'Parallel flow should set all signers to ABLE_TO_SIGN when file status is ABLE_TO_SIGN'
		);
	}

	public function testOrderedFlowRespectsSigningOrderWhenFileIsAbleToSign(): void {
		$sequentialSigningService = $this->createMock(SequentialSigningService::class);
		$sequentialSigningService
			->method('isOrderedNumericFlow')
			->willReturn(true); // Ordered flow

		$fileStatusService = $this->createMock(FileStatusService::class);
		$statusCacheService = $this->createMock(StatusCacheService::class);
		$statusService = new StatusService(
			$sequentialSigningService,
			$fileStatusService,
			$statusCacheService,
			new StatusUpdatePolicy()
		);

		$fileStatus = \OCA\Libresign\Enum\FileStatus::ABLE_TO_SIGN->value;
		$signerStatus = \OCA\Libresign\Enum\SignRequestStatus::DRAFT->value;

		// First signer (order 1) should be ABLE_TO_SIGN
		$result1 = $statusService->determineInitialStatus(
			1, 123, $fileStatus, $signerStatus, null
		);
		$this->assertEquals(
			\OCA\Libresign\Enum\SignRequestStatus::ABLE_TO_SIGN,
			$result1,
			'First signer in ordered flow should be ABLE_TO_SIGN'
		);

		// Second signer (order 2) should remain DRAFT
		$result2 = $statusService->determineInitialStatus(
			2, 123, $fileStatus, $signerStatus, null
		);
		$this->assertEquals(
			\OCA\Libresign\Enum\SignRequestStatus::DRAFT,
			$result2,
			'Second signer in ordered flow should remain DRAFT until first signs'
		);
	}

	/**
	 * Regression test for issue #7343.
	 *
	 * processFileData() must extract the inner `file` descriptor
	 * and pass it to FileService::getNodeFromData(), not the whole
	 * item (which would create a double-nested `file.file.path`
	 * and trigger "No file source provided").
	 */
	public function testSaveEnvelopeExtractsFileDescriptorFromNestedFilesArrayItems(): void {
		$service = $this->getService(['saveFile']);

		$envelope = new \OCA\Libresign\Db\File();
		$envelope->setId(10);

		$folder = $this->createMock(Folder::class);
		$folder->method('getId')->willReturn(99);

		$nodeA = $this->createMock(\OCP\Files\Node::class);
		$nodeB = $this->createMock(\OCP\Files\Node::class);

		$childA = new \OCA\Libresign\Db\File();
		$childA->setId(11);
		$childB = new \OCA\Libresign\Db\File();
		$childB->setId(12);

		$this->user->method('getUID')->willReturn('testuser');
		$this->envelopeService->method('validateEnvelopeConstraints');
		$this->envelopeService->method('createEnvelope')->willReturn($envelope);
		$this->envelopeService->method('getEnvelopeFolder')->willReturn($folder);
		$this->envelopeService->method('addFileToEnvelope')->willReturn(new \OCA\Libresign\Db\File());

		// KEY assertion: getNodeFromData must receive the inner file descriptor,
		// not the doubled-nested wrapper { file: { path }, name }.
		$this->fileService->expects($this->exactly(2))
			->method('getNodeFromData')
			->with($this->callback(function (array $data): bool {
				return isset($data['file']['path']) && !isset($data['file']['file']);
			}))
			->willReturnOnConsecutiveCalls($nodeA, $nodeB);

		$this->envelopeFileRelocator->method('ensureFileInEnvelopeFolder')
			->willReturnOnConsecutiveCalls($nodeA, $nodeB);

		$service->method('saveFile')->willReturnOnConsecutiveCalls($childA, $childB);

		$result = $service->saveEnvelope([
			'files' => [
				['file' => ['path' => '/A/file1.pdf'], 'name' => 'file1'],
				['file' => ['path' => '/B/file2.pdf'], 'name' => 'file2'],
			],
			'name' => 'My Envelope',
			'userManager' => $this->user,
			'settings' => [],
			'signers' => [],
			'status' => \OCA\Libresign\Enum\FileStatus::DRAFT->value,
			'visibleElements' => [],
			'signatureFlow' => null,
		]);

		$this->assertSame($envelope, $result['envelope']);
		$this->assertCount(2, $result['files']);
	}

	public function testSetSignatureFlowPrefersPayloadOverGlobalConfig(): void {
		$file = new \OCA\Libresign\Db\File();
		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(SignatureFlowPolicy::KEY, null, [SignatureFlowPolicy::KEY => SignatureFlow::PARALLEL->value])
			->willReturn($this->createResolvedPolicy(SignatureFlow::PARALLEL->value));

		self::invokePrivate($this->getService(), 'setSignatureFlow', [
			$file,
			['signatureFlow' => SignatureFlow::PARALLEL->value],
		]);

		$this->assertSame(SignatureFlow::PARALLEL, $file->getSignatureFlowEnum());
	}

	public function testSetSignatureFlowUsesGlobalConfigWhenPayloadMissing(): void {
		$file = new \OCA\Libresign\Db\File();
		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(SignatureFlowPolicy::KEY, null, [])
			->willReturn($this->createResolvedPolicy(SignatureFlow::ORDERED_NUMERIC->value));

		self::invokePrivate($this->getService(), 'setSignatureFlow', [
			$file,
			[],
		]);

		$this->assertSame(SignatureFlow::ORDERED_NUMERIC, $file->getSignatureFlowEnum());
	}

	public function testSetSignatureFlowDefaultsToNoneWithoutPayloadOrGlobalConfig(): void {
		$file = new \OCA\Libresign\Db\File();
		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(SignatureFlowPolicy::KEY, null, [])
			->willReturn($this->createResolvedPolicy(SignatureFlow::NONE->value));

		self::invokePrivate($this->getService(), 'setSignatureFlow', [
			$file,
			[],
		]);

		$this->assertSame(SignatureFlow::NONE, $file->getSignatureFlowEnum());
	}

	public function testSetSignatureFlowThrowsWhenRequestOverrideIsBlocked(): void {
		$file = new \OCA\Libresign\Db\File();
		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(SignatureFlowPolicy::KEY, null, [SignatureFlowPolicy::KEY => SignatureFlow::PARALLEL->value])
			->willReturn($this->createResolvedPolicy(
				SignatureFlow::ORDERED_NUMERIC->value,
				sourceScope: 'group',
				canUseAsRequestOverride: false,
				blockedBy: 'group',
			));

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);

		self::invokePrivate($this->getService(), 'setSignatureFlow', [
			$file,
			['signatureFlow' => SignatureFlow::PARALLEL->value],
		]);
	}

	public function testSetSignatureFlowStoresResolvedPolicySnapshotInMetadata(): void {
		$file = new \OCA\Libresign\Db\File();
		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(SignatureFlowPolicy::KEY, null, [])
			->willReturn($this->createResolvedPolicy(
				SignatureFlow::ORDERED_NUMERIC->value,
				sourceScope: 'group',
			));

		self::invokePrivate($this->getService(), 'setSignatureFlow', [
			$file,
			[],
		]);

		$this->assertSame(SignatureFlow::ORDERED_NUMERIC, $file->getSignatureFlowEnum());
		$this->assertSame([
			'policy_snapshot' => [
				'signature_flow' => [
					'effectiveValue' => SignatureFlow::ORDERED_NUMERIC->value,
					'sourceScope' => 'group',
				],
			],
		], $file->getMetadata());
	}

	public function testUpdateSignatureFlowIfAllowedForcesGlobalConfigOverFileValue(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('john');
		$file->setSignatureFlowEnum(SignatureFlow::PARALLEL);
		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(SignatureFlowPolicy::KEY, 'john', [SignatureFlowPolicy::KEY => SignatureFlow::PARALLEL->value])
			->willReturn($this->createResolvedPolicy(SignatureFlow::ORDERED_NUMERIC->value));

		$this->fileService
			->expects($this->once())
			->method('update')
			->with($this->identicalTo($file));

		self::invokePrivate($this->getService(), 'updateSignatureFlowIfAllowed', [
			$file,
			['signatureFlow' => SignatureFlow::PARALLEL->value],
		]);

		$this->assertSame(SignatureFlow::ORDERED_NUMERIC, $file->getSignatureFlowEnum());
	}

	public function testUpdateSignatureFlowIfAllowedUsesPayloadWhenGlobalConfigNotForced(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('john');
		$file->setSignatureFlowEnum(SignatureFlow::NONE);
		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(SignatureFlowPolicy::KEY, 'john', [SignatureFlowPolicy::KEY => SignatureFlow::PARALLEL->value])
			->willReturn($this->createResolvedPolicy(SignatureFlow::PARALLEL->value));

		$this->fileService
			->expects($this->once())
			->method('update')
			->with($this->identicalTo($file));

		self::invokePrivate($this->getService(), 'updateSignatureFlowIfAllowed', [
			$file,
			['signatureFlow' => SignatureFlow::PARALLEL->value],
		]);

		$this->assertSame(SignatureFlow::PARALLEL, $file->getSignatureFlowEnum());
	}

	public function testUpdateSignatureFlowIfAllowedThrowsWhenRequestOverrideIsBlocked(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('john');
		$file->setSignatureFlowEnum(SignatureFlow::NONE);
		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(SignatureFlowPolicy::KEY, 'john', [SignatureFlowPolicy::KEY => SignatureFlow::PARALLEL->value])
			->willReturn($this->createResolvedPolicy(
				SignatureFlow::ORDERED_NUMERIC->value,
				sourceScope: 'group',
				canUseAsRequestOverride: false,
				blockedBy: 'group',
			));

		$this->fileService
			->expects($this->never())
			->method('update');

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);

		self::invokePrivate($this->getService(), 'updateSignatureFlowIfAllowed', [
			$file,
			['signatureFlow' => SignatureFlow::PARALLEL->value],
		]);
	}

	public function testUpdateSignatureFlowIfAllowedKeepsCurrentValueWithoutPayloadOrForcedGlobal(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('john');
		$file->setSignatureFlowEnum(SignatureFlow::PARALLEL);
		$file->setMetadata([
			'policy_snapshot' => [
				'signature_flow' => [
					'effectiveValue' => SignatureFlow::PARALLEL->value,
					'sourceScope' => 'system',
				],
			],
		]);
		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(SignatureFlowPolicy::KEY, 'john', [])
			->willReturn($this->createResolvedPolicy(SignatureFlow::PARALLEL->value));

		$this->fileService
			->expects($this->never())
			->method('update');

		self::invokePrivate($this->getService(), 'updateSignatureFlowIfAllowed', [
			$file,
			[],
		]);

		$this->assertSame(SignatureFlow::PARALLEL, $file->getSignatureFlowEnum());
	}

	public function testUpdateSignatureFlowIfAllowedStoresResolvedPolicySnapshotWhenMissing(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('john');
		$file->setSignatureFlowEnum(SignatureFlow::PARALLEL);
		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(SignatureFlowPolicy::KEY, 'john', [])
			->willReturn($this->createResolvedPolicy(
				SignatureFlow::PARALLEL->value,
				sourceScope: 'group',
			));

		$this->fileService
			->expects($this->once())
			->method('update')
			->with($this->identicalTo($file));

		self::invokePrivate($this->getService(), 'updateSignatureFlowIfAllowed', [
			$file,
			[],
		]);

		$this->assertSame([
			'policy_snapshot' => [
				'signature_flow' => [
					'effectiveValue' => SignatureFlow::PARALLEL->value,
					'sourceScope' => 'group',
				],
			],
		], $file->getMetadata());
	}

	public function testSetDocMdpLevelUsesResolvedPolicyValue(): void {
		$file = new \OCA\Libresign\Db\File();
		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(DocMdpPolicy::KEY, null, [])
			->willReturn($this->createResolvedPolicy(
				2,
				policyKey: DocMdpPolicy::KEY,
			));

		self::invokePrivate($this->getService(), 'setDocMdpLevelFromPolicy', [
			$file,
			[],
		]);

		$this->assertSame(DocMdpLevel::CERTIFIED_FORM_FILLING, $file->getDocmdpLevelEnum());
	}

	public function testSetDocMdpLevelStoresResolvedPolicySnapshotInMetadata(): void {
		$file = new \OCA\Libresign\Db\File();
		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(DocMdpPolicy::KEY, null, [DocMdpPolicy::KEY => 3])
			->willReturn($this->createResolvedPolicy(
				3,
				sourceScope: 'group',
				policyKey: DocMdpPolicy::KEY,
			));

		self::invokePrivate($this->getService(), 'setDocMdpLevelFromPolicy', [
			$file,
			['docmdpLevel' => '3'],
		]);

		$this->assertSame([
			'policy_snapshot' => [
				'docmdp' => [
					'effectiveValue' => 3,
					'sourceScope' => 'group',
				],
			],
		], $file->getMetadata());
	}

	public function testUpdateDocMdpLevelFromPolicyUpdatesFileWhenEffectiveValueChanges(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('john');
		$file->setDocmdpLevelEnum(DocMdpLevel::NOT_CERTIFIED);

		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(DocMdpPolicy::KEY, 'john', [DocMdpPolicy::KEY => 1])
			->willReturn($this->createResolvedPolicy(
				1,
				policyKey: DocMdpPolicy::KEY,
			));

		$this->fileService
			->expects($this->once())
			->method('update')
			->with($this->identicalTo($file));

		self::invokePrivate($this->getService(), 'updateDocMdpLevelFromPolicy', [
			$file,
			['docmdpLevel' => 1],
		]);

		$this->assertSame(DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED, $file->getDocmdpLevelEnum());
	}

	public function testUpdateDocMdpLevelFromPolicyDoesNotPersistWhenNothingChangedAndSnapshotExists(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('john');
		$file->setDocmdpLevelEnum(DocMdpLevel::CERTIFIED_FORM_FILLING);
		$file->setMetadata([
			'policy_snapshot' => [
				'docmdp' => [
					'effectiveValue' => 2,
					'sourceScope' => 'system',
				],
			],
		]);

		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(DocMdpPolicy::KEY, 'john', [])
			->willReturn($this->createResolvedPolicy(
				2,
				policyKey: DocMdpPolicy::KEY,
			));

		$this->fileService
			->expects($this->never())
			->method('update');

		self::invokePrivate($this->getService(), 'updateDocMdpLevelFromPolicy', [
			$file,
			[],
		]);
	}

	private function createResolvedPolicy(
		mixed $effectiveValue,
		string $sourceScope = 'system',
		bool $canUseAsRequestOverride = true,
		?string $blockedBy = null,
		string $policyKey = 'signature_flow',
	): ResolvedPolicy {
		return (new ResolvedPolicy())
			->setPolicyKey($policyKey)
			->setEffectiveValue($effectiveValue)
			->setSourceScope($sourceScope)
			->setCanUseAsRequestOverride($canUseAsRequestOverride)
			->setBlockedBy($blockedBy);
	}
}
