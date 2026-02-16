<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use bovigo\vfs\vfsStream;
use OC\User\NoUserException;
use OCA\Libresign\BackgroundJob\SignSingleFileJob;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Db\UserElement;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Enum\DocMdpLevel;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\FileStatus as FileStatusEnum;
use OCA\Libresign\Events\SignedEvent;
use OCA\Libresign\Events\SignedEventFactory;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\DocMdpHandler;
use OCA\Libresign\Handler\FooterHandler;
use OCA\Libresign\Handler\PdfTk\Pdf;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Handler\SignEngine\Pkcs7Handler;
use OCA\Libresign\Handler\SignEngine\SignEngineFactory;
use OCA\Libresign\Handler\SignEngine\SignEngineHandler;
use OCA\Libresign\Helper\JavaHelper;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\CertificateValidityPolicy;
use OCA\Libresign\Service\Envelope\EnvelopeStatusDeterminer;
use OCA\Libresign\Service\FileStatusService;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\ISignatureMethod;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\PdfSignatureDetectionService;
use OCA\Libresign\Service\PfxProvider;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Service\SignFileService;
use OCA\Libresign\Service\SigningCoordinatorService;
use OCA\Libresign\Service\SignRequest\SignRequestService;
use OCA\Libresign\Service\SignRequest\StatusService;
use OCA\Libresign\Service\SubjectAlternativeNameService;
use OCA\Libresign\Service\TsaValidationService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Security\ICredentialsManager;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * @group DB
 */
final class SignFileServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IL10N&MockObject $l10n;
	private FooterHandler&MockObject $footerHandler;
	private FileMapper&MockObject $fileMapper;
	private SignRequestMapper&MockObject $signRequestMapper;
	private IdDocsMapper&MockObject $idDocsMapper;
	private IClientService&MockObject $clientService;
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
	private ISecureRandom $secureRandom;
	private IURLGenerator&MockObject $urlGenerator;
	private IdentifyMethodMapper&MockObject $identifyMethodMapper;
	private ITempManager|MockObject $tempManager;
	private IdentifyMethodService&MockObject $identifyMethodService;
	private ITimeFactory&MockObject $timeFactory;
	private JavaHelper&MockObject $javaHelper;
	private SignEngineFactory&MockObject $signEngineFactory;
	private SignedEventFactory&MockObject $signedEventFactory;
	private Pdf&MockObject $pdf;
	private DocMdpHandler $docMdpHandler;
	private PdfSignatureDetectionService&MockObject $pdfSignatureDetectionService;
	private \OCA\Libresign\Service\SequentialSigningService&MockObject $sequentialSigningService;
	private FileStatusService&MockObject $fileStatusService;
	private SigningCoordinatorService&MockObject $signingCoordinatorService;
	private IJobList&MockObject $jobList;
	private ICredentialsManager&MockObject $credentialsManager;
	private StatusService&MockObject $statusService;
	private EnvelopeStatusDeterminer&MockObject $envelopeStatusDeterminer;
	private TsaValidationService&MockObject $tsaValidationService;
	private CertificateValidityPolicy $certificateValidityPolicy;
	private PfxProvider $pfxProvider;
	private SubjectAlternativeNameService&MockObject $subjectAlternativeNameService;
	private SignRequestService&MockObject $signRequestService;

	public function setUp(): void {
		parent::setUp();
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->willReturnArgument(0);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->idDocsMapper = $this->createMock(IdDocsMapper::class);
		$this->footerHandler = $this->createMock(FooterHandler::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->folderService = $this->createMock(FolderService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->appConfig = $this->getMockAppConfigWithReset();
		$this->validateHelper = $this->createMock(\OCA\Libresign\Helper\ValidateHelper::class);
		$this->signerElementsService = $this->createMock(SignerElementsService::class);
		$this->root = $this->createMock(\OCP\Files\IRootFolder::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->dateTimeZone = \OCP\Server::get(IDateTimeZone::class);
		$this->fileElementMapper = $this->createMock(FileElementMapper::class);
		$this->userElementMapper = $this->createMock(UserElementMapper::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->secureRandom = \OCP\Server::get(\OCP\Security\ISecureRandom::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->identifyMethodMapper = $this->createMock(IdentifyMethodMapper::class);
		$this->tempManager = $this->createMock(ITempManager::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->javaHelper = $this->createMock(JavaHelper::class);
		$this->signEngineFactory = $this->createMock(SignEngineFactory::class);
		$this->signedEventFactory = $this->createMock(SignedEventFactory::class);
		$this->pdf = $this->createMock(Pdf::class);
		$this->docMdpHandler = new DocMdpHandler($this->l10n);
		$this->pdfSignatureDetectionService = $this->createMock(PdfSignatureDetectionService::class);
		$this->subjectAlternativeNameService = $this->createMock(SubjectAlternativeNameService::class);
		$this->sequentialSigningService = $this->createMock(\OCA\Libresign\Service\SequentialSigningService::class);
		$this->fileStatusService = $this->createMock(FileStatusService::class);
		$this->signingCoordinatorService = $this->createMock(SigningCoordinatorService::class);
		$this->jobList = $this->createMock(IJobList::class);
		$this->credentialsManager = $this->createMock(ICredentialsManager::class);
		$this->statusService = $this->createMock(StatusService::class);
		$this->envelopeStatusDeterminer = $this->createMock(EnvelopeStatusDeterminer::class);
		$this->tsaValidationService = $this->createMock(TsaValidationService::class);
		$this->certificateValidityPolicy = new CertificateValidityPolicy();
		$this->pfxProvider = new PfxProvider(
			$this->certificateValidityPolicy,
			$this->eventDispatcher,
			$this->secureRandom,
		);
		$this->signRequestService = $this->createMock(SignRequestService::class);
	}

	public function testClickToSignUsesShortLivedCertificate(): void {
		$service = $this->getService();
		$service
			->setSignWithoutPassword(true)
			->setSignatureMethod(ISignatureMethod::SIGNATURE_METHOD_CLICK_TO_SIGN)
			->setUserUniqueIdentifier('user@example.com')
			->setFriendlyName('User Example');

		$engine = $this->getMockBuilder(SignEngineHandler::class)
			->setConstructorArgs([
				$this->l10n,
				$this->folderService,
				$this->logger,
			])
			->onlyMethods([
				'getCertificate',
				'generateCertificate',
				'getPfxOfCurrentSigner',
				'setLeafExpiryOverrideInDays',
			])
			->getMockForAbstractClass();

		$engine->method('getCertificate')->willReturn('');
		$expiryCalls = [];
		$engine->expects($this->exactly(2))
			->method('setLeafExpiryOverrideInDays')
			->willReturnCallback(function ($value) use (&$expiryCalls, $engine) {
				$expiryCalls[] = $value;
				return $engine;
			});

		$engine->expects($this->once())
			->method('generateCertificate')
			->with(
				$this->callback(function (array $user): bool {
					return $user['host'] === 'user@example.com'
						&& $user['uid'] === 'user@example.com'
						&& $user['name'] === 'User Example';
				}),
				$this->isType('string'),
				'User Example',
			)
			->willReturn('cert');

		$engine->method('getPfxOfCurrentSigner')->willReturn('pfx');

		$result = self::invokePrivate($service, 'getOrGeneratePfxContent', [$engine]);

		$this->assertSame('pfx', $result);
		$this->assertSame([1, null], $expiryCalls);
	}

	public function testGetJobArgumentsWithoutCredentialsIncludesContext(): void {
		$service = $this->getService();

		$fileElement = new FileElement();
		$fileElement->setId(5);
		$elementAssoc = new \OCA\Libresign\DataObjects\VisibleElementAssoc($fileElement);

		$signRequest = new SignRequest();
		$signRequest->setId(9);
		$signRequest->setMetadata(['remote-address' => '127.0.0.1']);

		$user = $this->createMock(\OCP\IUser::class);
		$user->method('getUID')->willReturn('user1');

		$service->setUserUniqueIdentifier('account:user1');
		$service->setFriendlyName('User One');
		$service->setSignRequest($signRequest);
		$service->setCurrentUser($user);

		self::invokePrivate($service, 'elements', [[5 => $elementAssoc]]);

		$args = $service->getJobArgumentsWithoutCredentials();

		$this->assertSame('account:user1', $args['userUniqueIdentifier']);
		$this->assertSame('User One', $args['friendlyName']);
		$this->assertSame('user1', $args['userId']);
		$this->assertSame(['remote-address' => '127.0.0.1'], $args['metadata']);
		$this->assertArrayHasKey('visibleElements', $args);
	}

	public function testValidateSigningRequirementsDelegatesToTsaValidation(): void {
		$service = $this->getService();
		$this->tsaValidationService->expects($this->once())
			->method('validateConfiguration');

		$service->validateSigningRequirements();
	}

	public function testEnqueueParallelSigningJobsSkipsIneligibleFiles(): void {
		$service = $this->getService();

		$signedFile = new File();
		$signedFile->setId(1);
		$signedFile->setSignedHash('hash');
		$signedFile->setNodeId(10);
		$signedFile->setUserId('user1');

		$validFile = new File();
		$validFile->setId(2);
		$validFile->setNodeId(20);
		$validFile->setUserId('user1');

		$signRequestA = new SignRequest();
		$signRequestA->setId(100);
		$signRequestA->setUuid('uuid-a');

		$signRequestB = new SignRequest();
		$signRequestB->setId(200);
		$signRequestB->setUuid('uuid-b');

		$folder = $this->createMock(\OCP\Files\Folder::class);
		$folder->method('getFirstNodeById')
			->with(20)
			->willReturn($this->createMock(\OCP\Files\File::class));

		$this->root->method('getUserFolder')
			->with('user1')
			->willReturn($folder);

		$this->jobList->expects($this->once())
			->method('add')
			->with(
				SignSingleFileJob::class,
				$this->callback(function (array $args): bool {
					return $args['fileId'] === 2
						&& $args['signRequestId'] === 200
						&& $args['signRequestUuid'] === 'uuid-b';
				})
			);

		$enqueued = $service->enqueueParallelSigningJobs([
			['file' => $signedFile, 'signRequest' => $signRequestA],
			['file' => $validFile, 'signRequest' => $signRequestB],
		]);

		$this->assertSame(1, $enqueued);
	}

	public function testValidateDocMdpAllowsSignaturesThrowsWhenCertifiedNoChanges(): void {
		$service = $this->getService();

		$file = new File();
		$file->setDocmdpLevelEnum(DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED);
		$service->setLibreSignFile($file);

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('This document has been certified with no changes allowed. You cannot add more signers to this document.');

		self::invokePrivate($service, 'validateDocMdpAllowsSignatures');
	}

	public function testValidateDocMdpAllowsSignaturesChecksDocMdpHandler(): void {
		$this->docMdpHandler = $this->createMock(DocMdpHandler::class);
		$this->docMdpHandler->expects($this->once())
			->method('allowsAdditionalSignatures')
			->willReturn(false);

		$service = $this->getService(['getLibreSignFileAsResource']);
		$resource = fopen('php://memory', 'r+');
		fwrite($resource, 'pdf');
		rewind($resource);

		$service->method('getLibreSignFileAsResource')
			->willReturn($resource);

		$file = new File();
		$file->setDocmdpLevelEnum(DocMdpLevel::NOT_CERTIFIED);
		$service->setLibreSignFile($file);

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('This document has been certified with no changes allowed. You cannot add more signers to this document.');

		self::invokePrivate($service, 'validateDocMdpAllowsSignatures');
	}

	public function testEnqueueParallelSigningJobsStoresCredentialsWhenPasswordless(): void {
		$service = $this->getService();
		$service->setSignWithoutPassword(true);
		$service->setCurrentUser($this->createMock(\OCP\IUser::class));

		$file = new File();
		$file->setId(2);
		$file->setNodeId(20);
		$file->setUserId('user1');

		$signRequest = new SignRequest();
		$signRequest->setId(200);
		$signRequest->setUuid('uuid-b');

		$folder = $this->createMock(Folder::class);
		$folder->method('getFirstNodeById')
			->with(20)
			->willReturn($this->createMock(\OCP\Files\File::class));
		$this->root->method('getUserFolder')->willReturn($folder);

		$this->credentialsManager->expects($this->once())
			->method('store')
			->with(
				'',
				$this->stringContains('libresign_sign_'),
				$this->callback(function (array $payload): bool {
					return $payload['signWithoutPassword'] === true
						&& isset($payload['expires']);
				})
			);

		$this->jobList->expects($this->once())
			->method('add')
			->with(
				SignSingleFileJob::class,
				$this->callback(function (array $args): bool {
					return isset($args['credentialsId']);
				})
			);

		$enqueued = $service->enqueueParallelSigningJobs([
			['file' => $file, 'signRequest' => $signRequest],
		]);

		$this->assertSame(1, $enqueued);
	}

	private function getService(array $methods = []): SignFileService|MockObject {
		if ($methods) {
			return $this->getMockBuilder(SignFileService::class)
				->setConstructorArgs([
					$this->l10n,
					$this->fileMapper,
					$this->signRequestMapper,
					$this->idDocsMapper,
					$this->footerHandler,
					$this->folderService,
					$this->clientService,
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
					$this->secureRandom,
					$this->urlGenerator,
					$this->identifyMethodMapper,
					$this->tempManager,
					$this->signingCoordinatorService,
					$this->identifyMethodService,
					$this->timeFactory,
					$this->signEngineFactory,
					$this->signedEventFactory,
					$this->pdf,
					$this->docMdpHandler,
					$this->pdfSignatureDetectionService,
					$this->sequentialSigningService,
					$this->fileStatusService,
					$this->statusService,
					$this->jobList,
					$this->credentialsManager,
					$this->envelopeStatusDeterminer,
					$this->tsaValidationService,
					$this->pfxProvider,
					$this->subjectAlternativeNameService,
					$this->signRequestService,
				])
				->onlyMethods($methods)
				->getMock();
		}
		return new SignFileService(
			$this->l10n,
			$this->fileMapper,
			$this->signRequestMapper,
			$this->idDocsMapper,
			$this->footerHandler,
			$this->folderService,
			$this->clientService,
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
			$this->secureRandom,
			$this->urlGenerator,
			$this->identifyMethodMapper,
			$this->tempManager,
			$this->signingCoordinatorService,
			$this->identifyMethodService,
			$this->timeFactory,
			$this->signEngineFactory,
			$this->signedEventFactory,
			$this->pdf,
			$this->docMdpHandler,
			$this->pdfSignatureDetectionService,
			$this->sequentialSigningService,
			$this->fileStatusService,
			$this->statusService,
			$this->jobList,
			$this->credentialsManager,
			$this->envelopeStatusDeterminer,
			$this->tsaValidationService,
			$this->pfxProvider,
			$this->subjectAlternativeNameService,
			$this->signRequestService,
		);
	}

	public function testCanDeleteRequestSignatureWhenDocumentAlreadySigned():void {
		$file = $this->createMock(\OCA\Libresign\Db\File::class);
		$file->method('__call')->with($this->equalTo('getId'))->willReturn(1);
		$this->fileMapper->method('getByUuid')->willReturn($file);
		$signRequest = $this->createMock(\OCA\Libresign\Db\SignRequest::class);
		$signRequest
			->method('__call')
			->willReturnCallback(fn (string $method)
				=> match ($method) {
					'getSigned' => '2021-01-01 01:01:01',
				}
			);
		$this->signRequestMapper->method('getByFileUuid')->willReturn([$signRequest]);
		$this->expectExceptionMessage('Document already signed');
		$this->getService()->canDeleteRequestSignature(['uuid' => 'valid']);
	}

	public function testCanDeleteRequestSignatureWhenNoSignatureWasRequested():void {
		$file = $this->createMock(\OCA\Libresign\Db\File::class);
		$file->method('__call')->with($this->equalTo('getId'))->willReturn(1);
		$this->fileMapper->method('getByUuid')->willReturn($file);
		$signRequest = $this->createMock(\OCA\Libresign\Db\SignRequest::class);
		$signRequest
			->method('__call')
			->willReturnCallback(fn (string $method)
				=> match ($method) {
					'getSigned' => null,
					'getId' => 171,
				}
			);
		$this->signRequestMapper->method('getByFileUuid')->willReturn([$signRequest]);
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

		$signRequest = new \OCA\Libresign\Db\SignRequest();
		$this->getService()
			->setLibreSignFile($file)
			->setSignRequest($signRequest)
			->setPassword('password')
			->sign();
	}

	#[DataProvider('dataSignGenerateASha256OfSignedFile')]
	public function testSignGenerateASha256OfSignedFile(string $signedContent):void {
		$service = $this->getService([
			'getEngine',
			'setNewStatusIfNecessary',
			'getNextcloudFiles',
			'validateDocMdpAllowsSignatures',
		]);

		$nextcloudFile = $this->createMock(\OCP\Files\File::class);
		$nextcloudFile->method('getContent')->willReturn($signedContent);
		$nextcloudFile->method('getId')->willReturn(123);
		$service->method('getNextcloudFiles')->willReturn([$nextcloudFile]);
		$service->method('validateDocMdpAllowsSignatures');

		$pkcs12Handler = $this->createMock(Pkcs12Handler::class);
		$pkcs12Handler->method('sign')->willReturn($nextcloudFile);
		$pkcs12Handler->method('getLastSignedDate')->willReturn(new \DateTime());
		$pkcs12Handler->method('getInputFile')->willReturn($nextcloudFile);
		$service->method('getEngine')->willReturn($pkcs12Handler);

		$expectedHash = hash('sha256', $signedContent);

		$totalCalls = 0;
		$hashCallback = function ($method, $args) use ($expectedHash, &$totalCalls) {
			switch ($method) {
				case 'setSignedHash':
					$this->assertEquals($expectedHash, $args[0], 'Hash of signed file should match expected SHA-256 value');
					$totalCalls++;
					break;
				case 'getFileId':
					return 1;
				case 'getSigningOrder':
					return 1;
				case 'getDocmdpLevelEnum':
					return \OCA\Libresign\Enum\DocMdpLevel::NOT_CERTIFIED;
				case 'isEnvelope':
					return false;
				default: return null;
			}
		};
		$signRequest = $this->createMock(SignRequest::class);
		$signRequest->method('__call')->willReturnCallback($hashCallback);

		$libreSignFile = $this->createMock(\OCA\Libresign\Db\File::class);
		$libreSignFile->method('__call')->willReturnCallback($hashCallback);

		$service
			->setSignRequest($signRequest)
			->setLibreSignFile($libreSignFile)
			->sign();
		$this->assertEquals(2, $totalCalls, 'setSignedHash should be called twice');
	}

	public static function dataSignGenerateASha256OfSignedFile(): array {
		return [
			['signed content'],
			['another signed content'],
		];
	}

	public function testUpdateDatabaseWhenSign(): void {
		$service = $this->getService([
			'getEngine',
			'setNewStatusIfNecessary',
			'computeHash',
			'getNextcloudFiles',
			'validateDocMdpAllowsSignatures',
		]);

		$this->signingCoordinatorService
			->method('shouldUseParallelProcessing')
			->willReturn(false);

		$nextcloudFile = $this->createMock(\OCP\Files\File::class);
		$nextcloudFile->method('getContent')->willReturn('pdf content');
		$nextcloudFile->method('getId')->willReturn(456);
		$service->method('getNextcloudFiles')->willReturn([$nextcloudFile]);
		$service->method('validateDocMdpAllowsSignatures');
		$service->method('computeHash')->willReturn('hash');

		$this->fileStatusService->expects($this->once())
			->method('update')
			->willReturnArgument(0);
		$this->signRequestMapper->expects($this->once())->method('update');

		$pkcs12Handler = $this->createMock(Pkcs12Handler::class);
		$pkcs12Handler->method('sign')->willReturn($nextcloudFile);
		$pkcs12Handler->method('getLastSignedDate')->willReturn(new \DateTime());
		$service->method('getEngine')->willReturn($pkcs12Handler);

		$signRequest = $this->createMock(SignRequest::class);
		$signRequest->method('__call')->willReturnCallback(function ($method, $args) {
			switch ($method) {
				case 'getFileId':
					return 1;
				case 'getSigningOrder':
					return 1;
				default: return null;
			}
		});
		$libreSignFile = $this->createMock(\OCA\Libresign\Db\File::class);
		$libreSignFile->method('__call')->willReturnCallback(function ($method) {
			if ($method === 'getDocmdpLevelEnum') {
				return \OCA\Libresign\Enum\DocMdpLevel::NOT_CERTIFIED;
			} elseif ($method === 'isEnvelope') {
				return false;
			}
			return null;
		});

		$service
			->setSignRequest($signRequest)
			->setLibreSignFile($libreSignFile)
			->sign();
	}

	public function testDispatchEventWhenSign(): void {
		$service = $this->getService([
			'getEngine',
			'setNewStatusIfNecessary',
			'computeHash',
			'getNextcloudFiles',
			'validateDocMdpAllowsSignatures',
		]);

		$nextcloudFile = $this->createMock(\OCP\Files\File::class);
		$nextcloudFile->method('getContent')->willReturn('pdf content');
		$nextcloudFile->method('getId')->willReturn(789);
		$service->method('getNextcloudFiles')->willReturn([$nextcloudFile]);
		$service->method('validateDocMdpAllowsSignatures');
		$service->method('computeHash')->willReturn('hash');

		$this->eventDispatcher
			->expects($this->once())
			->method('dispatchTyped')
			->with($this->isInstanceOf(SignedEvent::class));

		$pkcs12Handler = $this->createMock(Pkcs12Handler::class);
		$pkcs12Handler->method('sign')->willReturn($nextcloudFile);
		$pkcs12Handler->method('getLastSignedDate')->willReturn(new \DateTime());
		$pkcs12Handler->method('getInputFile')->willReturn($nextcloudFile);
		$service->method('getEngine')->willReturn($pkcs12Handler);

		$signRequest = $this->createMock(SignRequest::class);
		$signRequest->method('__call')->willReturnCallback(function ($method, $args) {
			switch ($method) {
				case 'getFileId':
					return 1;
				case 'getSigningOrder':
					return 1;
				default: return null;
			}
		});
		$libreSignFile = $this->createMock(\OCA\Libresign\Db\File::class);
		$libreSignFile->method('__call')->willReturnCallback(function ($method) {
			if ($method === 'getDocmdpLevelEnum') {
				return \OCA\Libresign\Enum\DocMdpLevel::NOT_CERTIFIED;
			} elseif ($method === 'isEnvelope') {
				return false;
			}
			return null;
		});

		$service
			->setSignRequest($signRequest)
			->setLibreSignFile($libreSignFile)
			->sign();
	}

	public function testUpdateEnvelopeStatusAddsStatusChangedAt(): void {
		$service = $this->getService();

		$envelope = new File();
		$envelope->setId(77);
		$envelope->setStatus(FileStatus::DRAFT->value);

		$child = new File();
		$child->setId(99);

		$signRequest = new SignRequest();
		$signRequest->setFileId($child->getId());
		$signRequest->setSigned(new DateTime());

		$this->fileMapper->expects($this->once())
			->method('getChildrenFiles')
			->with($envelope->getId())
			->willReturn([$child]);

		$this->signRequestMapper->expects($this->once())
			->method('getByFileId')
			->with($child->getId())
			->willReturn([$signRequest]);

		$this->envelopeStatusDeterminer->expects($this->once())
			->method('determineStatus')
			->with([$child], [$child->getId() => [$signRequest]])
			->willReturn(FileStatusEnum::SIGNED->value);

		$this->signRequestMapper->expects($this->once())
			->method('update')
			->with($signRequest);

		$this->fileMapper->expects($this->once())
			->method('update')
			->with($this->callback(function (File $updated) {
				$metadata = $updated->getMetadata();
				$this->assertIsArray($metadata);
				$this->assertArrayHasKey('status_changed_at', $metadata);
				return true;
			}));

		$this->invokePrivate(
			$service
				->setLibreSignFile($envelope)
				->setSignRequest($signRequest),
			'updateEnvelopeStatus',
			[$envelope, $signRequest, new DateTime()]
		);

		$this->assertEquals(FileStatusEnum::SIGNED->value, $envelope->getStatus());
	}

	public function testEnqueueParallelSigningJobsStoresPerFileCredentials(): void {
		$service = $this->getService();

		$envelope = new File();
		$envelope->setId(1);
		$envelope->setNodeType('envelope');
		$envelope->setUserId('user1');

		$fileA = new File();
		$fileA->setId(10);
		$fileA->setNodeId(100);
		$fileA->setParentFileId($envelope->getId());
		$fileA->setUserId('user1');
		$signRequestA = new SignRequest();
		$signRequestA->setId(100);
		$signRequestA->setFileId($fileA->getId());
		$signRequestA->setMetadata(['key' => 'value']);

		$fileB = new File();
		$fileB->setId(11);
		$fileB->setNodeId(101);
		$fileB->setParentFileId($envelope->getId());
		$fileB->setUserId('user1');
		$signRequestB = new SignRequest();
		$signRequestB->setId(101);
		$signRequestB->setFileId($fileB->getId());
		$signRequestB->setMetadata(['key' => 'value']);

		$user = $this->createMock(\OCP\IUser::class);
		$user->method('getUID')->willReturn('user1');

		// Mock root folder for verifyFileExists
		$mockUserFolder = $this->createMock(\OCP\Files\Folder::class);
		$mockFile = $this->createMock(\OCP\Files\File::class);
		$mockUserFolder->method('getFirstNodeById')->willReturn($mockFile);
		$this->root->method('getUserFolder')->willReturn($mockUserFolder);

		$capturedCredentials = [];
		$this->credentialsManager->expects($this->exactly(2))
			->method('store')
			->willReturnCallback(function (string $uid, string $credentialsId, array $data) use (&$capturedCredentials) {
				$this->assertSame('user1', $uid);
				$this->assertStringStartsWith('libresign_sign_', $credentialsId);
				$this->assertSame('s3cret', $data['password']);
				$capturedCredentials[] = $credentialsId;
				return true;
			});

		$callIndex = 0;
		$this->jobList->expects($this->exactly(2))
			->method('add')
			->with(
				SignSingleFileJob::class,
				$this->callback(function (array $args) use (&$capturedCredentials, &$callIndex, $signRequestA, $signRequestB, $fileA, $fileB) {
					$expected = [
						['fileId' => $fileA->getId(), 'signRequestId' => $signRequestA->getId()],
						['fileId' => $fileB->getId(), 'signRequestId' => $signRequestB->getId()],
					];
					$this->assertSame($expected[$callIndex]['fileId'], $args['fileId']);
					$this->assertSame($expected[$callIndex]['signRequestId'], $args['signRequestId']);
					$this->assertArrayNotHasKey('password', $args);
					$this->assertArrayHasKey('credentialsId', $args);
					$this->assertContains($args['credentialsId'], $capturedCredentials);
					$this->assertArrayHasKey('userUniqueIdentifier', $args);
					$this->assertArrayHasKey('userId', $args);
					$this->assertArrayHasKey('isExternalSigner', $args);
					$callIndex++;
					return true;
				})
			);

		// Pre-build signRequests as the method now requires them as parameter
		$signRequests = [
			['file' => $fileA, 'signRequest' => $signRequestA],
			['file' => $fileB, 'signRequest' => $signRequestB],
		];

		$service
			->setLibreSignFile($envelope)
			->setSignRequest($signRequestA)
			->setCurrentUser($user)
			->setUserUniqueIdentifier('account:user1')
			->setFriendlyName('User One')
			->setPassword('s3cret');

		$jobArguments = $service->getJobArgumentsWithoutCredentials();
		$enqueued = $service->enqueueParallelSigningJobs($signRequests, $jobArguments);

		$this->assertSame(2, $enqueued);
		$this->assertCount(2, array_unique($capturedCredentials));
	}

	#[DataProvider('providerCheckStatusAfterSign')]
	public function testCheckStatusAfterSign(array $inputSigners, int $fileStatus, int $finalStatus): void {
		$service = $this->getService([
			'getEngine',
			'computeHash',
			'getSigners',
			'getNextcloudFiles',
		]);

		$nextcloudFile = $this->createMock(\OCP\Files\File::class);
		$nextcloudFile->method('getContent')->willReturn('pdf content');
		$nextcloudFile->method('getId')->willReturn(999);
		$service->method('getNextcloudFiles')->willReturn([$nextcloudFile]);
		$service->method('computeHash')->willReturn('hash');

		$service->method('getSigners')->willReturn($inputSigners);

		$pkcs12Handler = $this->createMock(Pkcs12Handler::class);
		$pkcs12Handler->method('sign')->willReturn($nextcloudFile);
		$pkcs12Handler->method('getLastSignedDate')->willReturn(new \DateTime());
		$service->method('getEngine')->willReturn($pkcs12Handler);

		$signRequestCallback = function ($method, $args) {
			switch ($method) {
				case 'getFileId':
					return 1;
				case 'getSigningOrder':
					return 1;
				default: return null;
			}
		};
		$signRequest = $this->createMock(SignRequest::class);
		$signRequest->method('__call')->willReturnCallback($signRequestCallback);
		$libreSignFile = new \OCA\Libresign\Db\File();
		$libreSignFile->setStatus($fileStatus);
		$libreSignFile->resetUpdatedFields();

		$service
			->setSignRequest($signRequest)
			->setLibreSignFile($libreSignFile)
			->sign();

		$this->assertEquals($finalStatus, $libreSignFile->getStatus());

		$updatedFields = $libreSignFile->getUpdatedFields();
		if ($fileStatus !== $finalStatus) {
			$this->assertArrayHasKey('status', $updatedFields);
			$this->assertTrue($updatedFields['status']);
		} else {
			$this->assertArrayNotHasKey('status', $updatedFields);
		}
	}

	public static function providerCheckStatusAfterSign(): array {
		return [
			[self::generateSigners(5, 1), FileStatus::ABLE_TO_SIGN->value, FileStatus::PARTIAL_SIGNED->value],
			[self::generateSigners(5, 1), FileStatus::PARTIAL_SIGNED->value, FileStatus::PARTIAL_SIGNED->value],
			[self::generateSigners(5, 5), FileStatus::ABLE_TO_SIGN->value, FileStatus::SIGNED->value],
			[self::generateSigners(3, 0), FileStatus::ABLE_TO_SIGN->value, FileStatus::ABLE_TO_SIGN->value],
			[self::generateSigners(3, 3), FileStatus::PARTIAL_SIGNED->value, FileStatus::SIGNED->value],
			[self::generateSigners(2, 2), FileStatus::SIGNED->value, FileStatus::SIGNED->value],
			[self::generateSigners(4, 3), FileStatus::ABLE_TO_SIGN->value, FileStatus::PARTIAL_SIGNED->value],
			[self::generateSigners(4, 4), FileStatus::PARTIAL_SIGNED->value, FileStatus::SIGNED->value],
			[self::generateSigners(1, 1), FileStatus::ABLE_TO_SIGN->value, FileStatus::SIGNED->value],
			[self::generateSigners(0, 0), FileStatus::ABLE_TO_SIGN->value, FileStatus::ABLE_TO_SIGN->value],
		];
	}

	private static function generateSigners(int $total, int $signed): array {
		$signers = [];
		for ($i = 0; $i < $total; $i ++) {
			$signers[] = new SignRequest();
		}
		for ($i = 0; $i < $signed; $i ++) {
			$signers[$i]->setSigned(new DateTime());
		}
		return $signers;
	}

	#[DataProvider('providerGetEngineWillWorkWithLazyLoadedEngine')]
	public function testGetEngineWillWorkWithLazyLoadedEngine(string $extension, string $instanceOf): void {
		$expectedEngine = $this->createMock($instanceOf);

		$this->signEngineFactory->method('resolve')
			->willReturn($expectedEngine);

		$service = $this->getService([
			'updateSignRequest',
			'updateLibreSignFile',
			'dispatchSignedEvent',
			'getFileToSign',
			'configureEngine',
			'getSignatureParams',
		]);

		$file = $this->createMock(\OCP\Files\File::class);
		$file->method('getExtension')->willReturn($extension);
		$service->method('getFileToSign')->willReturn($file);

		$engine = self::invokePrivate($service, 'getEngine');

		$this->assertInstanceOf($instanceOf, $engine);
	}

	public static function providerGetEngineWillWorkWithLazyLoadedEngine(): array {
		return [
			['pdf', Pkcs12Handler::class],
			['PDF', Pkcs12Handler::class],
			['odt', Pkcs7Handler::class],
			['ODT', Pkcs7Handler::class],
			['jpg', Pkcs7Handler::class],
			['JPG', Pkcs7Handler::class],
			['png', Pkcs7Handler::class],
			['PNG', Pkcs7Handler::class],
			['txt', Pkcs7Handler::class],
			['TXT', Pkcs7Handler::class],
		];
	}

	#[DataProvider('providerGetOrGeneratePfxContent')]
	public function testGetOrGeneratePfxContent(bool $signWithoutPassword, string $occurrency): void {
		$service = $this->getService([
			'getFileToSign',
			'identifyEngine',
			'computeHash',
			'updateSignRequest',
			'updateLibreSignFile',
			'dispatchSignedEvent',
			'validateDocMdpAllowsSignatures',
			'getNextcloudFiles',
		]);

		$signEngineHandler = $this->getMockBuilder(Pkcs12Handler::class)
			->disableOriginalConstructor()
			->onlyMethods([
				'getCertificate',
				'getPfxOfCurrentSigner',
				'generateCertificate',
				'sign',
				'getLastSignedDate',
			])
			->getMock();

		$signEngineHandler->method('getCertificate')->willReturn('');
		$signEngineHandler->method('getPfxOfCurrentSigner')->willReturn('pfx');
		$mockFile = $this->createMock(\OCP\Files\File::class);
		$mockFile->method('getId')->willReturn(555);

		$signEngineHandler->expects($this->{$occurrency}())->method('generateCertificate');
		$signEngineHandler->method('sign')->willReturn($mockFile);
		$signEngineHandler->method('getLastSignedDate')->willReturn(new \DateTime());
		$service->method('identifyEngine')->willReturn($signEngineHandler);
		$service->method('getNextcloudFiles')->willReturn([$mockFile]);

		$libreSignFile = $this->createMock(\OCA\Libresign\Db\File::class);
		$libreSignFile->method('__call')->willReturnCallback(function ($method) {
			switch ($method) {
				case 'isEnvelope':
					return false;
				case 'getDocmdpLevelEnum':
					return \OCA\Libresign\Enum\DocMdpLevel::NOT_CERTIFIED;
				default:
					return null;
			}
		});

		$signRequest = $this->createMock(SignRequest::class);
		$signRequest->method('__call')->willReturnCallback(function ($method) {
			switch ($method) {
				case 'getFileId':
					return 1;
				case 'getSigningOrder':
					return 1;
				default:
					return null;
			}
		});

		$service
			->setLibreSignFile($libreSignFile)
			->setSignRequest($signRequest)
			->setSignWithoutPassword($signWithoutPassword)
			->sign();
	}

	public static function providerGetOrGeneratePfxContent(): array {
		return [
			[true, 'once'],
			[false, 'never'],
		];
	}

	public function testGetSignRequestsToSignWhenFileHasParentEnvelope(): void {
		$service = $this->getService();

		$envelopeId = 99;
		$childFile = new File();
		$childFile->setId(10);
		$childFile->setParentFileId($envelopeId);

		$siblingFile = new File();
		$siblingFile->setId(11);
		$siblingFile->setParentFileId($envelopeId);

		$signRequest = new SignRequest();
		$signRequest->setId(200);
		$signRequest->setFileId($childFile->getId());

		$siblingSignRequest = new SignRequest();
		$siblingSignRequest->setId(201);
		$siblingSignRequest->setFileId($siblingFile->getId());

		$this->fileMapper
			->expects($this->once())
			->method('getChildrenFiles')
			->with($envelopeId)
			->willReturn([$childFile, $siblingFile]);

		$this->signRequestMapper
			->expects($this->once())
			->method('getByEnvelopeChildrenAndIdentifyMethod')
			->with($envelopeId, $signRequest->getId())
			->willReturn([$signRequest, $siblingSignRequest]);

		$result = self::invokePrivate(
			$service
				->setLibreSignFile($childFile)
				->setSignRequest($signRequest),
			'getSignRequestsToSign'
		);

		$this->assertCount(2, $result);
		$this->assertSame($childFile, $result[0]['file']);
		$this->assertSame($signRequest, $result[0]['signRequest']);
		$this->assertSame($siblingFile, $result[1]['file']);
		$this->assertSame($siblingSignRequest, $result[1]['signRequest']);
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

	private function createSignRequestMock(array $methods): SignRequest {
		$signRequest = $this->createMock(SignRequest::class);
		$signRequest->method('__call')->willReturnCallback(fn (string $method)
			=> $methods[$method] ?? null
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

		$expectedEmail = isset($expected['SignerEmail']) ? $expected['SignerEmail'] : null;
		$this->subjectAlternativeNameService
			->method('extractEmailFromCertificate')
			->willReturn($expectedEmail);

		$service = $this->getService(['readCertificate']);
		$service->method('readCertificate')
			->willReturn($certData);
		$service->setLibreSignFile($libreSignFile);

		$signRequest = $this->createMock(SignRequest::class);
		$signRequest
			->method('__call')
			->willReturnCallback(fn (string $method)
				=> match ($method) {
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
			->willReturnCallback(fn (string $method)
				=> match ($method) {
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
		array $fileElements,
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
			->willReturnCallback(fn (string $method)
				=> match ($method) {
					'getFileId' => 171,
					'getId' => 171,
				}
			);
		$service->setSignRequest($signRequest);

		$fileElements = array_map(function ($value) {
			$fileElement = new FileElement();
			$fileElement->setId($value['id']);
			return $fileElement;
		}, $fileElements);
		$this->fileElementMapper->method('getByFileIdAndSignRequestId')->willReturn($fileElements);

		$this->signerElementsService->method('canCreateSignature')->willReturn($canCreateSignature);

		$this->userElementMapper->method('findOne')->willReturnCallback(function () use ($signatureFile) {
			if (!empty($signatureFile)) {
				$userElement = new UserElement();
				$userElement->setFileId(1);
				return $userElement;
			}
			throw new DoesNotExistException('User element not found');
		});

		$this->folderService->method('getFileByNodeId')
			->willReturnCallback(function ($id) use ($signatureFile) {
				if (isset($signatureFile[$id]) && $signatureFile[$id]['valid']) {
					$file = $this->getMockBuilder(\OCP\Files\File::class)->getMock();
					$file->method('getContent')->willReturn($signatureFile[$id]['content']);
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
			$this->assertCount(count($fileElements), $visibleElements);
			foreach ($fileElements as $key => $element) {
				$elementId = $element->getId();
				$this->assertArrayHasKey($elementId, $visibleElements);
				$this->assertSame($element, $visibleElements[$elementId]->getFileElement());
				$this->assertEquals(
					isset($signerList[$key], $signerList[$key]['profileNodeId'], $tempFiles[$signerList[$key]['profileNodeId']])
						? $tempFiles[$signerList[$key]['profileNodeId']] . '/_' . $signerList[$key]['profileNodeId'] . '.png'
						: '',
					$visibleElements[$elementId]->getTempFile(),
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
				fileElements: [],
				tempFiles: [],
				signatureFile: [],
				canCreateSignature: true,
				isAuthenticatedSigner: true,
			),

			'empty list, cannot create signature' => self::createScenarioSetVisibleElements(
				signerList: [],
				fileElements: [],
				tempFiles: [],
				signatureFile: [],
				canCreateSignature: false,
				isAuthenticatedSigner: true,
			),

			'valid signer with signature file, valid content of signature file' => self::createScenarioSetVisibleElements(
				signerList: [
					['documentElementId' => $validDocumentId, 'profileNodeId' => $validProfileNodeId],
				],
				fileElements: [
					['id' => $validDocumentId],
				],
				tempFiles: [$validProfileNodeId => $vfsPath],
				signatureFile: [$validProfileNodeId => ['valid' => true, 'content' => 'valid content']],
				canCreateSignature: true,
				isAuthenticatedSigner: true,
			),

			'valid signer with signature file, invalid content of signature file' => self::createScenarioSetVisibleElements(
				signerList: [
					['documentElementId' => $validDocumentId, 'profileNodeId' => $validProfileNodeId],
				],
				fileElements: [
					['id' => $validDocumentId],
				],
				tempFiles: [$validProfileNodeId => false],
				signatureFile: [$validProfileNodeId => ['valid' => true, 'content' => '']],
				canCreateSignature: true,
				isAuthenticatedSigner: true,
				expectedException: LibresignException::class,
			),

			'invalid signature file, with invalid user element' => self::createScenarioSetVisibleElements(
				signerList: [
					['documentElementId' => $validDocumentId, 'profileNodeId' => $validProfileNodeId],
				],
				fileElements: [
					['id' => $validDocumentId],
				],
				tempFiles: [$validProfileNodeId => $vfsPath],
				signatureFile: [$validProfileNodeId => ['valid' => false, 'content' => 'valid content']],
				canCreateSignature: true,
				isAuthenticatedSigner: true,
				expectedException: LibresignException::class,
			),

			'invalid signature file, with invalid type of profileNodeId' => self::createScenarioSetVisibleElements(
				signerList: [
					['documentElementId' => $validDocumentId, 'profileNodeId' => 'not-a-number'],
				],
				fileElements: [
					['id' => $validDocumentId],
				],
				tempFiles: [$validProfileNodeId => $vfsPath],
				signatureFile: [$validProfileNodeId => ['valid' => false, 'content' => 'valid content']],
				canCreateSignature: true,
				isAuthenticatedSigner: true,
				expectedException: LibresignException::class,
			),

			'invalid signature file' => self::createScenarioSetVisibleElements(
				signerList: [
					['documentElementId' => $validDocumentId, 'profileNodeId' => $validProfileNodeId],
				],
				fileElements: [
					['id' => $validDocumentId],
				],
				tempFiles: [$validProfileNodeId => $vfsPath],
				signatureFile: [$validProfileNodeId => ['valid' => false, 'content' => 'valid content']],
				canCreateSignature: true,
				isAuthenticatedSigner: true,
				expectedException: LibresignException::class,
			),

			'missing profileNodeId throws exception' => self::createScenarioSetVisibleElements(
				signerList: [
					['documentElementId' => $validDocumentId],
				],
				fileElements: [
					['id' => $validDocumentId],
				],
				tempFiles: [],
				signatureFile: [],
				canCreateSignature: true,
				isAuthenticatedSigner: true,
				expectedException: LibresignException::class,
			),

			'cannot create signature, visible element fallback' => self::createScenarioSetVisibleElements(
				signerList: [
					['documentElementId' => $validDocumentId],
				],
				fileElements: [
					['id' => $validDocumentId],
				],
				tempFiles: [],
				signatureFile: [],
				canCreateSignature: false,
				isAuthenticatedSigner: true,
			),
			'no authenticated user, missing session file' => self::createScenarioSetVisibleElements(
				signerList: [['documentElementId' => $validDocumentId, 'profileNodeId' => $validProfileNodeId]],
				fileElements: [['id' => $validDocumentId]],
				tempFiles: [],
				signatureFile: [],
				canCreateSignature: true,
				isAuthenticatedSigner: false,
				expectedException: LibresignException::class,
			),
			'user fallback with valid user element' => self::createScenarioSetVisibleElements(
				signerList: [
					['documentElementId' => $validDocumentId, 'profileNodeId' => $validProfileNodeId],
				],
				fileElements: [
					['id' => $validDocumentId],
				],
				tempFiles: [$validProfileNodeId => $vfsPath],
				signatureFile: [$validProfileNodeId => ['valid' => true, 'content' => 'valid content']],
				canCreateSignature: true,
				isAuthenticatedSigner: true,
			),

			'authenticated user, file not found' => self::createScenarioSetVisibleElements(
				signerList: [
					['documentElementId' => $validDocumentId, 'profileNodeId' => $validProfileNodeId],
				],
				fileElements: [
					['id' => $validDocumentId],
				],
				tempFiles: [],
				signatureFile: [],
				canCreateSignature: true,
				isAuthenticatedSigner: true,
				expectedException: LibresignException::class,
			),

			'unauthenticated signer with file in folder service (WhatsApp scenario)' => self::createScenarioSetVisibleElements(
				signerList: [
					['documentElementId' => $validDocumentId, 'profileNodeId' => $validProfileNodeId],
				],
				fileElements: [
					['id' => $validDocumentId],
				],
				tempFiles: [$validProfileNodeId => $vfsPath],
				signatureFile: [$validProfileNodeId => ['valid' => true, 'content' => 'valid content']],
				canCreateSignature: true,
				isAuthenticatedSigner: false,
			),
		];
	}

	private static function createScenarioSetVisibleElements(
		array $signerList = [],
		array $fileElements = [],
		array $tempFiles = [],
		array $signatureFile = [],
		bool $canCreateSignature = false,
		bool $isAuthenticatedSigner = false,
		?string $expectedException = null,
	): array {
		return [
			$signerList,
			$fileElements,
			$tempFiles,
			$signatureFile,
			$canCreateSignature,
			$expectedException,
			$isAuthenticatedSigner,
		];
	}

	#[DataProvider('providerGetSignedFile')]
	public function testGetSignedFile(
		int $timesCalled,
		string $managerUid,
		?string $ownerUid = null,
		?int $nodeId = null,
	): void {
		$service = $this->getService(['getNodeByIdUsingUid']);

		$libreSignFile = new \OCA\Libresign\Db\File();
		$libreSignFile->setSignedNodeId($nodeId);
		$libreSignFile->setUserId($managerUid);
		$service->setLibreSignFile($libreSignFile);

		$fileToSign = $this->createMock(\OCP\Files\File::class);
		$user = $this->createMock(\OCP\IUser::class);
		$user->method('getUID')->willReturn($ownerUid);
		$fileToSign->method('getOwner')->willReturn($user);
		$service
			->expects($this->exactly($timesCalled))
			->method('getNodeByIdUsingUid')
			->willReturn($fileToSign);

		$this->invokePrivate($service, 'getSignedFile');
	}

	public static function providerGetSignedFile(): array {
		return [
			[0, 'managerUid', '', null],
			[1, 'managerUid', 'managerUid', 1],
			[2, 'managerUid', 'johndoe', 1],
		];
	}

	#[DataProvider('providerGetNodeByIdUsingUid')]
	public function testGetNodeByIdUsingUid(
		string $typeOfNode,
		string $exceptionMessage,
	): void {
		$service = $this->getService();
		if ($exceptionMessage) {
			$this->expectExceptionMessageMatches($exceptionMessage);
		}
		$leaf = $this->createMock($typeOfNode);
		$userFolder = $this->createMock(\OCP\Files\Folder::class);
		$userFolder->method('getFirstNodeById')->willReturn($leaf);
		$this->root->method('getUserFolder')->willReturnCallback(function () use ($userFolder, $exceptionMessage) {
			switch ($exceptionMessage) {
				case '/User not found/':
					throw new NoUserException();
				case '/not have permission/':
					throw new NotPermittedException();
				case '/File not found/':
					return $userFolder;
				default:
					return $userFolder;
			}
		});
		$actual = $this->invokePrivate($service, 'getNodeByIdUsingUid', ['', 1]);
		$this->assertEquals($leaf, $actual);
	}

	public static function providerGetNodeByIdUsingUid(): array {
		return [
			[\OCP\Files\Folder::class, '/User not found/'],
			[\OCP\Files\Folder::class, '/not have permission/'],
			[\OCP\Files\Folder::class, '/File not found/'],
			[\OCP\Files\File::class, ''],
		];
	}

	public function testSignThrowsExceptionWhenDocMdpLevel1Detected(): void {
		$this->expectException(LibresignException::class);
		$service = $this->getService(['getNextcloudFiles', 'getEngine']);

		$nextcloudFile = $this->createMock(\OCP\Files\File::class);
		$nextcloudFile->method('getContent')->willReturn(file_get_contents(__DIR__ . '/../../fixtures/pdfs/real_jsignpdf_level1.pdf'));
		$service->method('getNextcloudFiles')->willReturn([$nextcloudFile]);

		$engineMock = $this->createMock(Pkcs12Handler::class);
		$service->method('getEngine')->willReturn($engineMock);

		$signRequest = $this->createMock(SignRequest::class);
		$signRequest->method('__call')->willReturnCallback(function ($method) {
			switch ($method) {
				case 'getFileId':
					return 1;
				case 'getSigningOrder':
					return 1;
				default: return null;
			}
		});

		$libreSignFile = $this->createMock(\OCA\Libresign\Db\File::class);
		$libreSignFile->method('getDocmdpLevelEnum')->willReturn(\OCA\Libresign\Enum\DocMdpLevel::NOT_CERTIFIED);

		$service
			->setSignRequest($signRequest)
			->setLibreSignFile($libreSignFile)
			->sign();
	}

	#[DataProvider('provideValidateDocMdpAllowsSignaturesScenarios')]
	public function testValidateDocMdpAllowsSignaturesWithVariousPdfFixtures(
		callable $pdfContentGenerator,
		bool $shouldThrowException,
	): void {
		if (!$shouldThrowException) {
			$this->expectNotToPerformAssertions();
		} else {
			$this->expectException(LibresignException::class);
		}

		$service = $this->getService(['getLibreSignFileAsResource']);

		$pdfContent = $pdfContentGenerator($this);
		$resource = fopen('php://memory', 'r+');
		fwrite($resource, $pdfContent);
		rewind($resource);

		$service->method('getLibreSignFileAsResource')->willReturn($resource);

		$libreSignFile = $this->createMock(\OCA\Libresign\Db\File::class);
		$libreSignFile->method('getDocmdpLevelEnum')->willReturn(\OCA\Libresign\Enum\DocMdpLevel::NOT_CERTIFIED);
		$service->setLibreSignFile($libreSignFile);

		self::invokePrivate($service, 'validateDocMdpAllowsSignatures');
	}

	public static function provideValidateDocMdpAllowsSignaturesScenarios(): array {
		return [
			'Unsigned PDF - should NOT throw exception' => [
				'pdfContentGenerator' => fn (self $test) => \OCA\Libresign\Tests\Fixtures\PdfGenerator::createMinimalPdf(),
				'shouldThrowException' => false,
			],
			'DocMDP level 0 (not certified) - should NOT throw exception' => [
				'pdfContentGenerator' => fn (self $test) => \OCA\Libresign\Tests\Fixtures\PdfGenerator::createPdfWithDocMdp(0, false),
				'shouldThrowException' => false,
			],
			'DocMDP level 1 (no changes allowed) - SHOULD throw exception' => [
				'pdfContentGenerator' => fn (self $test) => \OCA\Libresign\Tests\Fixtures\PdfGenerator::createPdfWithDocMdp(1, false),
				'shouldThrowException' => true,
			],
			'DocMDP level 2 (form filling allowed) - should NOT throw exception' => [
				'pdfContentGenerator' => fn (self $test) => \OCA\Libresign\Tests\Fixtures\PdfGenerator::createPdfWithDocMdp(2, false),
				'shouldThrowException' => false,
			],
			'DocMDP level 3 (annotations allowed) - should NOT throw exception' => [
				'pdfContentGenerator' => fn (self $test) => \OCA\Libresign\Tests\Fixtures\PdfGenerator::createPdfWithDocMdp(3, false),
				'shouldThrowException' => false,
			],
			'DocMDP level 1 with modifications - SHOULD throw exception' => [
				'pdfContentGenerator' => fn (self $test) => \OCA\Libresign\Tests\Fixtures\PdfGenerator::createPdfWithDocMdp(1, true),
				'shouldThrowException' => true,
			],
		];
	}

	#[DataProvider('providerSetVisibleElementsValidation')]
	public function testSetVisibleElementsValidation(
		array $signerList,
		?int $fileId,
		?int $signRequestId,
		?string $expectedException,
	): void {
		$service = $this->getService();
		$signRequest = $this->createMock(SignRequest::class);
		$signRequest
			->method('__call')
			->willReturnCallback(fn (string $method)
				=> match ($method) {
					'getFileId' => $fileId,
					'getId' => $signRequestId,
				}
			);
		$service->setSignRequest($signRequest);

		$this->fileElementMapper->method('getByFileIdAndSignRequestId')->willReturn([]);
		$this->signerElementsService->method('canCreateSignature')->willReturn(false);

		if ($expectedException) {
			$this->expectException($expectedException);
		}

		$result = $service->setVisibleElements($signerList);

		$this->assertSame($service, $result);
	}

	public static function providerSetVisibleElementsValidation(): array {
		return [
			[[], null, 171, null],
			[[], 171, null, null],
			[[], null, null, null],
			[[['documentElementId' => 171]], null, 171, LibresignException::class],
			[[['documentElementId' => 171]], 171, null, LibresignException::class],
			[[['documentElementId' => 171]], null, null, LibresignException::class],
		];
	}
	public function testGetSignRequestsToSignForStandaloneFile(): void {
		$service = $this->getService();

		$file = new File();
		$file->setId(1);
		$file->setNodeType('file');

		$signRequest = new SignRequest();
		$signRequest->setId(10);
		$signRequest->setFileId(1);

		$service->setLibreSignFile($file);
		$service->setSignRequest($signRequest);

		$result = self::invokePrivate($service, 'getSignRequestsToSign');
		$this->assertSame($signRequest, $result[0]['signRequest']);
	}

	public function testGetSignRequestsToSignForEnvelopeWithChildren(): void {
		$service = $this->getService();

		$envelope = new File();
		$envelope->setId(1);
		$envelope->setNodeType('envelope');

		$signRequest = new SignRequest();
		$signRequest->setId(10);
		$signRequest->setFileId(1);

		$child1 = new File();
		$child1->setId(2);
		$child2 = new File();
		$child2->setId(3);

		$childSr1 = new SignRequest();
		$childSr1->setId(20);
		$childSr1->setFileId(2);

		$childSr2 = new SignRequest();
		$childSr2->setId(21);
		$childSr2->setFileId(3);

		$this->fileMapper->method('getChildrenFiles')
			->with(1)
			->willReturn([$child1, $child2]);

		$this->signRequestMapper->method('getByEnvelopeChildrenAndIdentifyMethod')
			->with(1, 10)
			->willReturn([$childSr1, $childSr2]);

		$service->setLibreSignFile($envelope);
		$service->setSignRequest($signRequest);

		$result = self::invokePrivate($service, 'getSignRequestsToSign');
		$this->assertSame(20, $result[0]['signRequest']->getId());
		$this->assertSame(3, $result[1]['file']->getId());
		$this->assertSame(21, $result[1]['signRequest']->getId());
	}

	public function testGetSignRequestsToSignThrowsWhenNoChildrenFiles(): void {
		$service = $this->getService();

		$envelope = new File();
		$envelope->setId(1);
		$envelope->setNodeType('envelope');

		$signRequest = new SignRequest();
		$signRequest->setId(10);

		$this->fileMapper->method('getChildrenFiles')
			->with(1)
			->willReturn([]);

		$service->setLibreSignFile($envelope);
		$service->setSignRequest($signRequest);

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('No files found in envelope');

		self::invokePrivate($service, 'getSignRequestsToSign');
	}

	public function testGetSignRequestsToSignThrowsWhenNoSignRequests(): void {
		$service = $this->getService();

		$envelope = new File();
		$envelope->setId(1);
		$envelope->setNodeType('envelope');

		$signRequest = new SignRequest();
		$signRequest->setId(10);

		$child = new File();
		$child->setId(2);

		$this->fileMapper->method('getChildrenFiles')
			->willReturn([$child]);

		$this->signRequestMapper->method('getByEnvelopeChildrenAndIdentifyMethod')
			->willReturn([]);

		$service->setLibreSignFile($envelope);
		$service->setSignRequest($signRequest);

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('No sign requests found for envelope files');

		self::invokePrivate($service, 'getSignRequestsToSign');
	}

	public static function getEnvelopeContextProvider(): array {
		return [
			'standalone file' => [null, 1, 'file', null],
			'envelope itself' => [null, 1, 'envelope', 1],
			'child file' => [1, 2, 'file', 1],
		];
	}

	#[DataProvider('getEnvelopeContextProvider')]
	public function testGetEnvelopeContext(?int $parentFileId, int $fileId, string $nodeType, ?int $expectedEnvelopeId): void {
		$service = $this->getService();

		$file = new File();
		$file->setId($fileId);
		$file->setNodeType($nodeType);
		$file->setParentFileId($parentFileId);

		$service->setLibreSignFile($file);

		if ($nodeType === 'file' && $parentFileId === null) {
			$signRequest = new SignRequest();
			$signRequest->setId(10);
			$service->setSignRequest($signRequest);
			$result = self::invokePrivate($service, 'getEnvelopeContext');
			$this->assertNull($result['envelopeSignRequest']);
		} elseif ($nodeType === 'envelope') {
			$signRequest = new SignRequest();
			$signRequest->setId(10);
			$service->setSignRequest($signRequest);
			$result = self::invokePrivate($service, 'getEnvelopeContext');
			$this->assertArrayHasKey('envelope', $result);
		} elseif ($nodeType === 'file' && $parentFileId !== null) {
			$envelope = new File();
			$envelope->setId($expectedEnvelopeId);
			$envelope->setNodeType('envelope');
			$this->fileMapper->method('getById')
				->with($expectedEnvelopeId)
				->willReturn($envelope);

			$identifyMethod = $this->createMock(IIdentifyMethod::class);
			$this->identifyMethodService->method('getIdentifiedMethod')
				->willReturn($identifyMethod);

			$envelopeSignRequest = new SignRequest();
			$envelopeSignRequest->setId(100);
			$envelopeSignRequest->setFileId($expectedEnvelopeId);

			$this->signRequestMapper->method('getByIdentifyMethodAndFileId')
				->with($identifyMethod, $expectedEnvelopeId)
				->willReturn($envelopeSignRequest);

			$childSignRequest = new SignRequest();
			$childSignRequest->setId(20);
			$childSignRequest->setFileId($fileId);
			$service->setSignRequest($childSignRequest);

			$result = self::invokePrivate($service, 'getEnvelopeContext');

			$this->assertSame($expectedEnvelopeId, $result['envelope']->getId());
		}
	}

	public function testGetEnvelopeContextReturnsNullWhenEnvelopeNotFound(): void {
		$service = $this->getService();

		$child = new File();
		$child->setId(2);
		$child->setNodeType('file');
		$child->setParentFileId(1);

		$childSignRequest = new SignRequest();
		$childSignRequest->setId(20);

		$this->fileMapper->method('getById')
			->willThrowException(new DoesNotExistException(''));

		$service->setLibreSignFile($child);
		$service->setSignRequest($childSignRequest);

		$result = self::invokePrivate($service, 'getEnvelopeContext');

		$this->assertNull($result['envelope']);
		$this->assertNull($result['envelopeSignRequest']);
	}

	public function testStoreCertificateInfoInMetadata(): void {
		$service = $this->getService();

		$signRequest = new SignRequest();
		$signRequest->setId(10);
		$signRequest->setFileId(1);
		$signRequest->setMetadata(['existing' => 'data']);

		$service->setSignRequest($signRequest);

		$certificateInfo = [
			'serialNumber' => '12345',
			'serialNumberHex' => 'abc123',
			'hash' => 'sha256hash',
			'subject' => [
				'CN' => 'John Doe',
				'C' => 'US',
			],
		];

		$engine = $this->createMock(SignEngineHandler::class);
		$engine->method('readCertificate')->willReturn($certificateInfo);

		self::invokePrivate($service, 'engine', [$engine]);
		self::invokePrivate($service, 'storeCertificateInfoInMetadata', [$certificateInfo]);

		$meta = $signRequest->getMetadata();
		$this->assertArrayHasKey('certificate_info', $meta);
		$this->assertSame('12345', $meta['certificate_info']['serialNumber']);
		$this->assertSame('abc123', $meta['certificate_info']['serialNumberHex']);
		$this->assertSame('sha256hash', $meta['certificate_info']['hash']);
		$this->assertArrayHasKey('subject', $meta['certificate_info']);
		$this->assertSame('John Doe', $meta['certificate_info']['subject']['CN']);
		$this->assertSame('US', $meta['certificate_info']['subject']['C']);
		$this->assertArrayHasKey('existing', $meta);
		$this->assertSame('data', $meta['existing']);
	}

	public function testStoreCertificateInfoDoesNotOverwriteExistingMetadata(): void {
		$service = $this->getService();

		$signRequest = new SignRequest();
		$signRequest->setId(10);
		$signRequest->setMetadata([
			'existing_key' => 'existing_value',
			'other_data' => 'preserved',
		]);

		$service->setSignRequest($signRequest);

		$certificateInfo = [
			'serialNumber' => '99999',
		];

		self::invokePrivate($service, 'storeCertificateInfoInMetadata', [$certificateInfo]);

		$meta = $signRequest->getMetadata();
		$this->assertArrayHasKey('existing_key', $meta);
		$this->assertSame('existing_value', $meta['existing_key']);
		$this->assertArrayHasKey('other_data', $meta);
	}

	public function testBuildSignRequestsMapGroupsByFileId(): void {
		$service = $this->getService();

		$child1 = new File();
		$child1->setId(10);

		$child2 = new File();
		$child2->setId(20);

		$signRequest1a = new SignRequest();
		$signRequest1a->setId(1);
		$signRequest1a->setFileId(10);

		$signRequest1b = new SignRequest();
		$signRequest1b->setId(2);
		$signRequest1b->setFileId(10);

		$signRequest2 = new SignRequest();
		$signRequest2->setId(3);
		$signRequest2->setFileId(20);

		$this->signRequestMapper->method('getByFileId')
			->willReturnMap([
				[10, [$signRequest1a, $signRequest1b]],
				[20, [$signRequest2]],
			]);

		$result = self::invokePrivate($service, 'buildSignRequestsMap', [[$child1, $child2]]);

		$this->assertCount(2, $result);
		$this->assertArrayHasKey(10, $result);
		$this->assertArrayHasKey(20, $result);
		$this->assertCount(2, $result[10]);
		$this->assertCount(1, $result[20]);

		$this->assertCount(2, $result);
		$this->assertArrayHasKey(10, $result);
		$this->assertArrayHasKey(20, $result);
		$this->assertCount(2, $result[10]);
		$this->assertCount(1, $result[20]);
	}

	public function testHandleSignedEnvelopeSignRequestUpdatesStatusAndReleases(): void {
		$service = $this->getService();

		$envelope = new File();
		$envelope->setId(1);
		$envelope->setNodeType('envelope');

		$envelopeSignRequest = new SignRequest();
		$envelopeSignRequest->setId(10);
		$envelopeSignRequest->setFileId(1);
		$envelopeSignRequest->setSigningOrder(1);

		$signedDate = new \DateTime('2026-01-15 10:00:00');

		$this->signRequestMapper->expects($this->once())
			->method('update')
			->with($this->callback(function (SignRequest $sr) use ($signedDate) {
				$this->assertEquals($signedDate, $sr->getSigned());
				$this->assertEquals(\OCA\Libresign\Enum\SignRequestStatus::SIGNED, $sr->getStatusEnum());
				return true;
			}));

		$this->sequentialSigningService->expects($this->once())
			->method('setFile')
			->with($envelope)
			->willReturnSelf();

		$this->sequentialSigningService->expects($this->once())
			->method('releaseNextOrder')
			->with(1, 1);

		self::invokePrivate($service, 'handleSignedEnvelopeSignRequest', [$envelope, $envelopeSignRequest, $signedDate, FileStatus::PARTIAL_SIGNED->value]);
	}

	public function testHandleSignedEnvelopeSignRequestSkipsWhenNoSignRequest(): void {
		$service = $this->getService();

		$envelope = new File();
		$envelope->setId(1);

		$this->signRequestMapper->expects($this->never())
			->method('update');

		self::invokePrivate($service, 'handleSignedEnvelopeSignRequest', [$envelope, null, null, FileStatus::DRAFT->value]);
	}

	public function testUpdateEnvelopeMetadataAddsTimestamp(): void {
		$service = $this->getService();

		$envelope = new File();
		$envelope->setId(1);
		$envelope->setMetadata(['existing' => 'value']);

		self::invokePrivate($service, 'updateEnvelopeMetadata', [$envelope]);

		$meta = $envelope->getMetadata();
		$this->assertArrayHasKey('existing', $meta);
		$this->assertNotEmpty($meta['status_changed_at']);

		// Validate ISO 8601 format
		$timestamp = \DateTime::createFromFormat(\DateTimeInterface::ATOM, $meta['status_changed_at']);
		$this->assertNotFalse($timestamp);
	}

	public static function verifyFileExistsProvider(): array {
		return [
			'accessible file' => ['user1', 123, true, 'file'],
			'node is folder' => ['user1', 123, false, 'folder'],
			'user exception' => ['user1', 123, false, 'exception'],
			'null uid' => [null, 123, false, 'skip'],
			'zero node id' => ['user1', 0, false, 'skip'],
		];
	}

	#[DataProvider('verifyFileExistsProvider')]
	public function testVerifyFileExists(?string $uid, int $nodeId, bool $expected, string $scenario): void {
		$service = $this->getService();

		if ($scenario === 'exception') {
			$this->root->method('getUserFolder')
				->willThrowException(new NoUserException());
		} elseif ($scenario === 'file') {
			$mockFile = $this->createMock(\OCP\Files\File::class);
			$mockFolder = $this->createMock(\OCP\Files\Folder::class);
			$mockFolder->method('getFirstNodeById')->willReturn($mockFile);
			$this->root->method('getUserFolder')->willReturn($mockFolder);
		} elseif ($scenario === 'folder') {
			$mockFolder = $this->createMock(\OCP\Files\Folder::class);
			$mockFolder->method('getFirstNodeById')->willReturn($mockFolder);
			$this->root->method('getUserFolder')->willReturn($mockFolder);
		}

		$result = self::invokePrivate($service, 'verifyFileExists', [$uid, $nodeId]);

		$this->assertSame($expected, $result);
	}

	public static function cleanupUnsignedSignedFileProvider(): array {
		return [
			'delete success' => [true, null],
			'delete with error' => [true, new \Exception('Delete failed')],
			'no file' => [false, null],
		];
	}

	#[DataProvider('cleanupUnsignedSignedFileProvider')]
	public function testCleanupUnsignedSignedFile(bool $hasFile, ?\Exception $deleteException): void {
		$service = $this->getService();

		if ($hasFile) {
			$mockFile = $this->createMock(\OCP\Files\File::class);

			if ($deleteException) {
				$mockFile->method('delete')
					->willThrowException($deleteException);
			} else {
				$mockFile->expects($this->once())
					->method('delete');
			}

			self::invokePrivate($service, 'createdSignedFile', [$mockFile]);
		}

		self::invokePrivate($service, 'cleanupUnsignedSignedFile');

		$this->assertNull(self::invokePrivate($service, 'createdSignedFile'));
	}
}
