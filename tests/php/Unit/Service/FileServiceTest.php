<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileService;
use PHPUnit\Framework\Attributes\DataProvider;

final class FileServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private $fileMapper;
	private $signRequestMapper;
	private $fileElementMapper;
	private $fileElementService;
	private $folderService;
	private $idDocsMapper;
	private $identifyMethodService;
	private $userManager;
	private $urlGenerator;
	private $mimeTypeDetector;
	private $pkcs12Handler;
	private $docMdpHandler;
	private $pdfValidator;
	private $rootFolder;
	private $logger;
	private $l10n;
	private $envelopeService;
	private $signersLoader;
	private $uploadHelper;
	private $envelopeAssembler;
	private $envelopeProgressService;
	private $certificateChainService;
	private $mimeService;
	private $contentProvider;
	private $uploadProcessor;
	private $metadataLoader;
	private $settingsLoader;
	private $messagesLoader;
	private $fileStatusService;

	public function setUp(): void {
		parent::setUp();

		$this->fileMapper = $this->createMock(\OCA\Libresign\Db\FileMapper::class);
		$this->signRequestMapper = $this->createMock(\OCA\Libresign\Db\SignRequestMapper::class);
		$this->fileElementMapper = $this->createMock(\OCA\Libresign\Db\FileElementMapper::class);
		$this->fileElementService = $this->createMock(\OCA\Libresign\Service\FileElementService::class);
		$this->folderService = $this->createMock(\OCA\Libresign\Service\FolderService::class);
		$this->idDocsMapper = $this->createMock(\OCA\Libresign\Db\IdDocsMapper::class);
		$this->identifyMethodService = $this->createMock(\OCA\Libresign\Service\IdentifyMethodService::class);
		$this->userManager = $this->createMock(\OCP\IUserManager::class);
		$this->urlGenerator = $this->createMock(\OCP\IURLGenerator::class);
		$this->mimeTypeDetector = $this->createMock(\OCP\Files\IMimeTypeDetector::class);
		$this->pkcs12Handler = $this->createMock(\OCA\Libresign\Handler\SignEngine\Pkcs12Handler::class);
		$this->docMdpHandler = $this->createMock(\OCA\Libresign\Handler\DocMdpHandler::class);
		$this->pdfValidator = $this->createMock(\OCA\Libresign\Service\File\Pdf\PdfValidator::class);
		$this->rootFolder = $this->createMock(\OCP\Files\IRootFolder::class);
		$this->logger = $this->createMock(\Psr\Log\LoggerInterface::class);
		$this->l10n = $this->createMock(\OCP\IL10N::class);
		$this->envelopeService = $this->createMock(\OCA\Libresign\Service\Envelope\EnvelopeService::class);
		$this->signersLoader = $this->createMock(\OCA\Libresign\Service\File\SignersLoader::class);
		$this->uploadHelper = $this->createMock(\OCA\Libresign\Helper\FileUploadHelper::class);
		$this->envelopeAssembler = $this->createMock(\OCA\Libresign\Service\File\EnvelopeAssembler::class);
		$this->envelopeProgressService = $this->createMock(\OCA\Libresign\Service\File\EnvelopeProgressService::class);
		$this->certificateChainService = $this->createMock(\OCA\Libresign\Service\File\CertificateChainService::class);
		$this->mimeService = $this->createMock(\OCA\Libresign\Service\File\MimeService::class);
		$this->contentProvider = $this->createMock(\OCA\Libresign\Service\File\FileContentProvider::class);
		$this->uploadProcessor = $this->createMock(\OCA\Libresign\Service\File\UploadProcessor::class);
		$this->metadataLoader = $this->createMock(\OCA\Libresign\Service\File\MetadataLoader::class);
		$this->settingsLoader = $this->createMock(\OCA\Libresign\Service\File\SettingsLoader::class);
		$this->messagesLoader = $this->createMock(\OCA\Libresign\Service\File\MessagesLoader::class);
		$this->fileStatusService = $this->createMock(\OCA\Libresign\Service\FileStatusService::class);
	}

	private function createFileService(): FileService {
		return new FileService(
			$this->fileMapper,
			$this->signRequestMapper,
			$this->fileElementMapper,
			$this->fileElementService,
			$this->folderService,
			$this->idDocsMapper,
			$this->identifyMethodService,
			$this->userManager,
			$this->urlGenerator,
			$this->mimeTypeDetector,
			$this->pkcs12Handler,
			$this->docMdpHandler,
			$this->pdfValidator,
			$this->rootFolder,
			$this->logger,
			$this->l10n,
			$this->envelopeService,
			$this->signersLoader,
			$this->uploadHelper,
			$this->envelopeAssembler,
			$this->envelopeProgressService,
			$this->certificateChainService,
			$this->mimeService,
			$this->contentProvider,
			$this->uploadProcessor,
			$this->metadataLoader,
			$this->settingsLoader,
			$this->messagesLoader,
			$this->fileStatusService,
		);
	}

	public function testValidateFileContentSkipsNonPdfFiles(): void {
		$service = $this->createFileService();

		$this->expectNotToPerformAssertions();
		$service->validateFileContent('any content', 'text-file', 'txt');
		$service->validateFileContent('{"json": true}', 'payload', 'json');
	}

	public function testSetFileByIdThrowsOnInvalid(): void {
		$this->fileMapper->method('getById')->willThrowException(new \Exception('not found'));

		$service = $this->createFileService();

		$this->expectException(LibresignException::class);
		$service->setFileById(123);
	}

	public function testSetFileByIdSetsFile(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setStatus(1);

		$this->fileMapper->method('getById')->willReturn($file);

		$service = $this->createFileService();

		$returned = $service->setFileById(123);
		$this->assertInstanceOf(FileService::class, $returned);
		$this->assertSame(1, $service->getStatus());
	}

	public function testGetNodeFromDataWithNodeId(): void {
		$node = $this->createMock(\OCP\Files\File::class);
		$this->folderService->method('getUserId')->willReturn('testuser');
		$this->folderService->method('getFileByNodeId')->with(35523)->willReturn($node);

		$userManager = $this->createMock(\OCP\IUser::class);
		$userManager->method('getUID')->willReturn('testuser');

		$service = $this->createFileService();

		$data = [
			'file' => ['nodeId' => 35523],
			'userManager' => $userManager,
		];

		$result = $service->getNodeFromData($data);
		$this->assertSame($node, $result);
	}

	public function testGetNodeFromDataWithFileId(): void {
		$node = $this->createMock(\OCP\Files\File::class);
		$this->folderService->method('getUserId')->willReturn('testuser');
		$this->folderService->method('getFileByNodeId')->with(12345)->willReturn($node);

		$userManager = $this->createMock(\OCP\IUser::class);
		$userManager->method('getUID')->willReturn('testuser');

		$service = $this->createFileService();

		$data = [
			'file' => ['fileId' => 12345],
			'userManager' => $userManager,
		];

		$result = $service->getNodeFromData($data);
		$this->assertSame($node, $result);
	}

	public function testGetNodeFromDataPrefersFileIdOverNodeId(): void {
		$nodeFromFileId = $this->createMock(\OCP\Files\File::class);
		$this->folderService->method('getUserId')->willReturn('testuser');
		$this->folderService->expects($this->once())
			->method('getFileByNodeId')
			->with(12345)
			->willReturn($nodeFromFileId);

		$userManager = $this->createMock(\OCP\IUser::class);
		$userManager->method('getUID')->willReturn('testuser');

		$service = $this->createFileService();

		$data = [
			'file' => [
				'fileId' => 12345,
				'nodeId' => 35523, // This should be ignored
			],
			'userManager' => $userManager,
		];

		$result = $service->getNodeFromData($data);
		$this->assertSame($nodeFromFileId, $result);
	}

	#[DataProvider('nodeFromDataFileNameProvider')]
	public function testGetNodeFromDataResolvesFileNameWithoutDuplicateExtension(string $inputName, string $expectedName): void {
		$content = '%PDF-1.4';
		$extension = 'pdf';
		$node = $this->createMock(\OCP\Files\File::class);
		$folder = $this->createMock(\OCP\Files\Folder::class);

		$this->contentProvider->method('getContentFromData')->willReturn($content);
		$this->mimeService->method('getExtension')->willReturn($extension);
		$this->pdfValidator->expects($this->once())
			->method('validate')
			->with($content, $inputName);
		$this->folderService->method('getFolderForFile')->willReturn($folder);
		$folder->expects($this->once())
			->method('newFile')
			->with($expectedName, $content)
			->willReturn($node);

		$service = $this->createFileService();

		$data = [
			'name' => $inputName,
			'file' => [],
			'userManager' => '',
		];

		$result = $service->getNodeFromData($data);
		$this->assertSame($node, $result);
	}

	public static function nodeFromDataFileNameProvider(): array {
		return [
			'keeps extension with spaces' => ['My File.pdf', 'My_File.pdf'],
			'keeps extension with trim' => ['  invoice.pdf  ', 'invoice.pdf'],
			'keeps extension case-insensitive' => ['report.PDF', 'report.PDF'],
			'appends extension when missing' => ['My File', 'My_File.pdf'],
		];
	}

	public function testDeleteRemovesEmptyFolder(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setId(1);
		$file->setNodeId(100);
		$file->setNodeType('single');
		$file->setParentFileId(null);

		$parentFolder = $this->createMock(\OCP\Files\Folder::class);
		$parentFolder->expects($this->once())
			->method('getDirectoryListing')
			->willReturn([]);
		$parentFolder->expects($this->once())
			->method('delete');

		$nextcloudFile = $this->createMock(\OCP\Files\File::class);
		$nextcloudFile->expects($this->once())
			->method('getParent')
			->willReturn($parentFolder);
		$nextcloudFile->expects($this->once())
			->method('delete');

		$this->fileMapper->method('getById')->willReturn($file);
		$this->fileMapper->expects($this->once())->method('delete')->with($file);

		$this->folderService->expects($this->once())
			->method('getFileByNodeId')
			->with(100)
			->willReturn($nextcloudFile);

		$this->signRequestMapper->method('getByFileId')->willReturn([]);
		$this->fileElementService->expects($this->once())->method('deleteVisibleElements');
		$this->idDocsMapper->expects($this->once())->method('deleteByFileId');

		$service = $this->createFileService();

		$service->delete(1, true);
	}

	public function testDeleteKeepsNonEmptyFolder(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setId(1);
		$file->setNodeId(100);
		$file->setNodeType('single');
		$file->setParentFileId(null);

		$parentFolder = $this->createMock(\OCP\Files\Folder::class);
		$existingFile = $this->createMock(\OCP\Files\File::class);
		$parentFolder->expects($this->once())
			->method('getDirectoryListing')
			->willReturn([$existingFile]);
		$parentFolder->expects($this->never())
			->method('delete');

		$nextcloudFile = $this->createMock(\OCP\Files\File::class);
		$nextcloudFile->expects($this->once())
			->method('getParent')
			->willReturn($parentFolder);
		$nextcloudFile->expects($this->once())
			->method('delete');

		$this->fileMapper->method('getById')->willReturn($file);
		$this->fileMapper->expects($this->once())->method('delete')->with($file);

		$this->folderService->expects($this->once())
			->method('getFileByNodeId')
			->with(100)
			->willReturn($nextcloudFile);

		$this->signRequestMapper->method('getByFileId')->willReturn([]);
		$this->fileElementService->expects($this->once())->method('deleteVisibleElements');
		$this->idDocsMapper->expects($this->once())->method('deleteByFileId');

		$service = $this->createFileService();

		$service->delete(1, true);
	}

	public function testDeleteWithoutDeletingFile(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setId(1);
		$file->setNodeId(100);
		$file->setNodeType('single');
		$file->setParentFileId(null);

		$this->fileMapper->method('getById')->willReturn($file);
		$this->fileMapper->expects($this->once())->method('delete')->with($file);

		$this->folderService->expects($this->never())->method('getFileByNodeId');

		$this->signRequestMapper->method('getByFileId')->willReturn([]);
		$this->fileElementService->expects($this->once())->method('deleteVisibleElements');
		$this->idDocsMapper->expects($this->once())->method('deleteByFileId');

		$service = $this->createFileService();

		$service->delete(1, false);
	}

	#[DataProvider('providerTestVisibleElements')]
	public function testVisibleElements(bool $showVisibleElementsFlag, bool $expectedInResult): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setId(1);
		$file->setUuid('test-uuid');
		$file->setName('test.pdf');
		$file->setStatus(1);
		$file->setCreatedAt(new \DateTime());
		$file->setNodeId(100);
		$file->setSignatureFlow('');
		$file->setDocmdpLevel('');

		$file->setUserId('testuser');

		$user = $this->createMock(\OCP\IUser::class);
		$user->method('getDisplayName')->willReturn('Test User');

		$this->fileMapper->method('getById')->willReturn($file);
		$this->fileMapper->method('getTextOfStatus')->willReturn('Status text');
		$this->userManager->method('get')->willReturn($user);
		$this->signRequestMapper->method('getByMultipleFileId')->willReturn([]);
		if ($showVisibleElementsFlag) {
			$this->signRequestMapper->method('getVisibleElementsFromSigners')->willReturn([]);
		}

		$service = $this->createFileService();
		$chainedService = $service->setFile($file);

		if ($showVisibleElementsFlag) {
			$chainedService->showVisibleElements();
		}

		$result = $chainedService->toArray();

		if ($expectedInResult) {
			$this->assertArrayHasKey('visibleElements', $result);
			$this->assertIsArray($result['visibleElements']);
		} else {
			$this->assertArrayNotHasKey('visibleElements', $result);
		}
	}

	public static function providerTestVisibleElements(): array {
		return [
			'visible elements included when showVisibleElements() called' => [true, true],
			'visible elements not included when showVisibleElements() not called' => [false, false],
		];
	}

	public function testEnvelopeVisibleElementsIncludesChildFileElements(): void {
		$envelopeFile = new \OCA\Libresign\Db\File();
		$envelopeFile->setId(10);
		$envelopeFile->setUuid('envelope-uuid');
		$envelopeFile->setName('envelope.pdf');
		$envelopeFile->setStatus(1);
		$envelopeFile->setCreatedAt(new \DateTime());
		$envelopeFile->setNodeId(100);
		$envelopeFile->setNodeType('envelope');
		$envelopeFile->setSignatureFlow('');
		$envelopeFile->setDocmdpLevel('');
		$envelopeFile->setUserId('testuser');
		$envelopeFile->setMetadata(['filesCount' => 1]);

		$childFile = new \OCA\Libresign\Db\File();
		$childFile->setId(20);
		$childFile->setNodeType('file');
		$childFile->setMetadata(['d' => [['w' => 595, 'h' => 842]]]);

		$childSignRequest = new \OCA\Libresign\Db\SignRequest();
		$childSignRequest->setId(200);
		$childSignRequest->setFileId(20);

		$fileElement = new \OCA\Libresign\Db\FileElement();
		$fileElement->setId(300);
		$fileElement->setSignRequestId(200);
		$fileElement->setFileId(20);
		$fileElement->setType('signature');
		$fileElement->setPage(1);
		$fileElement->setUrx(200);
		$fileElement->setUry(400);
		$fileElement->setLlx(100);
		$fileElement->setLly(350);
		$fileElement->setMetadata([]);

		$this->fileMapper->method('getChildrenFiles')
			->with(10)
			->willReturn([$childFile]);

		$this->signRequestMapper->method('getByMultipleFileId')
			->with([10, 20])
			->willReturn([$childSignRequest]);

		$this->signRequestMapper->method('getVisibleElementsFromSigners')
			->with([$childSignRequest])
			->willReturn([200 => [$fileElement]]);

		$expectedFormatted = [[
			'elementId' => 300,
			'signRequestId' => 200,
			'fileId' => 20,
			'type' => 'signature',
			'coordinates' => [
				'page' => 1,
				'urx' => 200,
				'ury' => 400,
				'llx' => 100,
				'lly' => 350,
				'left' => 100,
				'top' => 442,
				'width' => 100,
				'height' => 50,
			],
		]];

		$this->fileElementService->method('formatVisibleElements')
			->willReturn($expectedFormatted);

		$user = $this->createMock(\OCP\IUser::class);
		$user->method('getDisplayName')->willReturn('Test User');
		$this->userManager->method('get')->willReturn($user);
		$this->fileMapper->method('getTextOfStatus')->willReturn('Status text');
		$this->signRequestMapper->method('getByFileId')->willReturn([]);

		$service = $this->createFileService();
		$result = $service
			->setFile($envelopeFile)
			->showVisibleElements()
			->toArray();

		$this->assertArrayHasKey('visibleElements', $result);
		$this->assertCount(1, $result['visibleElements']);
		$this->assertSame(300, $result['visibleElements'][0]['elementId']);
		$this->assertSame(200, $result['visibleElements'][0]['signRequestId']);
		$this->assertSame(20, $result['visibleElements'][0]['fileId']);
	}
}
