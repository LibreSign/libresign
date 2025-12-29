<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File;

use OCA\Libresign\Db\File;
use OCA\Libresign\Service\File\FileContentProvider;
use OCA\Libresign\Service\File\MetadataLoader;
use OCP\Files\Folder;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\IURLGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use stdClass;

final class MetadataLoaderTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IRootFolder|MockObject $root;
	private IMimeTypeDetector|MockObject $mimeTypeDetector;
	private IURLGenerator|MockObject $urlGenerator;
	private FileContentProvider|MockObject $contentProvider;
	private LoggerInterface|MockObject $logger;

	public function setUp(): void {
		parent::setUp();
		$this->root = $this->createMock(IRootFolder::class);
		$this->mimeTypeDetector = $this->createMock(IMimeTypeDetector::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->contentProvider = $this->createMock(FileContentProvider::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	private function getService(): MetadataLoader {
		return new MetadataLoader(
			$this->root,
			$this->mimeTypeDetector,
			$this->urlGenerator,
			$this->contentProvider,
			$this->logger,
		);
	}

	public function testLoadMetadataNull(): void {
		$fileData = new stdClass();

		$service = $this->getService();
		$service->loadMetadata(null, $fileData);

		$this->assertFalse(property_exists($fileData, 'size'));
		$this->assertFalse(property_exists($fileData, 'mime'));
	}

	public function testLoadMetadataWithFileSize(): void {
		$file = new File();
		$file->setId(1);
		$file->setUserId('user123');
		$file->setSignedNodeId(123);
		$file->setMetadata(['p' => 2, 'd' => [100, 200]]);
		$file->setUuid('uuid-123');

		$fileNode = $this->createMock(\OCP\Files\File::class);
		$fileNode->method('getSize')->willReturn(5000);
		$fileNode->method('getMimeType')->willReturn('application/pdf');

		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('getFirstNodeById')->with(123)->willReturn($fileNode);

		$this->root->method('getUserFolder')->with('user123')->willReturn($userFolder);

		$this->urlGenerator->method('linkToRoute')->willReturn('http://example.com/page.pdf');

		$fileData = new stdClass();

		$service = $this->getService();
		$service->loadMetadata($file, $fileData);

		$this->assertEquals(5000, $fileData->size);
		$this->assertEquals('application/pdf', $fileData->mime);
	}

	public function testLoadMetadataWithPages(): void {
		$file = new File();
		$file->setId(1);
		$file->setUserId('user123');
		$file->setSignedNodeId(123);
		$file->setMetadata(['p' => 2, 'd' => [100, 200]]);
		$file->setUuid('uuid-123');

		$fileNode = $this->createMock(\OCP\Files\File::class);
		$fileNode->method('getSize')->willReturn(5000);
		$fileNode->method('getMimeType')->willReturn('application/pdf');

		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('getFirstNodeById')->with(123)->willReturn($fileNode);

		$this->root->method('getUserFolder')->with('user123')->willReturn($userFolder);

		$this->urlGenerator->method('linkToRoute')->willReturnCallback(
			fn ($route, $params) => "http://example.com/page/{$params['page']}"
		);

		$fileData = new stdClass();

		$service = $this->getService();
		$service->loadMetadata($file, $fileData);

		$this->assertCount(2, $fileData->pages);
		$this->assertEquals('http://example.com/page/1', $fileData->pages[0]['url']);
		$this->assertEquals(100, $fileData->pages[0]['resolution']);
		$this->assertEquals('http://example.com/page/2', $fileData->pages[1]['url']);
		$this->assertEquals(200, $fileData->pages[1]['resolution']);
	}

	public function testLoadMetadataLogsWarningOnError(): void {
		$file = new File();
		$file->setId(1);
		$file->setUserId('user123');
		$file->setSignedNodeId(123);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('getFirstNodeById')->willThrowException(new \Exception('File not found'));

		$this->root->method('getUserFolder')->with('user123')->willReturn($userFolder);

		$this->logger
			->expects($this->once())
			->method('warning')
			->with($this->stringContains('Failed to load file metadata'));

		$fileData = new stdClass();

		$service = $this->getService();
		$service->loadMetadata($file, $fileData);

		// Should not throw exception
		$this->assertTrue(true);
	}

	#[DataProvider('provideNodeIdPrecedenceScenarios')]
	public function testNodeIdPrecedenceAndFallback(?int $signedNodeId, ?int $nodeId, int $expectedNodeId): void {
		$file = new File();
		$file->setId(1);
		$file->setUserId('user123');
		$file->setSignedNodeId($signedNodeId);
		$file->setNodeId($nodeId);
		$file->setMetadata(['p' => 0, 'd' => []]);

		$fileNode = $this->createMock(\OCP\Files\File::class);
		$fileNode->method('getSize')->willReturn(5000);
		$fileNode->method('getMimeType')->willReturn('application/pdf');

		$userFolder = $this->createMock(Folder::class);
		$userFolder
			->expects($this->once())
			->method('getFirstNodeById')
			->with($expectedNodeId)
			->willReturn($fileNode);

		$this->root->method('getUserFolder')->with('user123')->willReturn($userFolder);

		$this->urlGenerator->method('linkToRoute')->willReturn('http://example.com/page.pdf');

		$fileData = new stdClass();

		$service = $this->getService();
		$service->loadMetadata($file, $fileData);

		$this->assertEquals(5000, $fileData->size);
	}

	public static function provideNodeIdPrecedenceScenarios(): array {
		return [
			'signedNodeId takes precedence when present' => [123, 456, 123],
			'fallback to nodeId when signedNodeId is null' => [null, 456, 456],
		];
	}

	#[DataProvider('provideMetadataFieldScenarios')]
	public function testLoadMetadataDefaults(int $pageCount, ?string $pdfVersion, int $expectedPages, string $expectedPdfVersion): void {
		$file = new File();
		$file->setId(1);
		$file->setUserId('user123');
		$file->setSignedNodeId(123);
		$metadata = ['p' => $pageCount];
		if ($pdfVersion !== null) {
			$metadata['pdfVersion'] = $pdfVersion;
		}
		$file->setMetadata($metadata);
		$file->setUuid('uuid-123');

		$fileNode = $this->createMock(\OCP\Files\File::class);
		$fileNode->method('getSize')->willReturn(5000);
		$fileNode->method('getMimeType')->willReturn('application/pdf');

		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('getFirstNodeById')->with(123)->willReturn($fileNode);

		$this->root->method('getUserFolder')->with('user123')->willReturn($userFolder);

		$this->urlGenerator->method('linkToRoute')->willReturn('http://example.com/page.pdf');

		$fileData = new stdClass();

		$service = $this->getService();
		$service->loadMetadata($file, $fileData);

		$this->assertEquals($expectedPages, $fileData->totalPages);
		$this->assertEquals($expectedPdfVersion, $fileData->pdfVersion);
	}

	public static function provideMetadataFieldScenarios(): array {
		return [
			'no pages with no pdfVersion' => [0, null, 0, ''],
			'multiple pages with pdf version' => [5, '1.7', 5, '1.7'],
			'single page with pdf version' => [1, '1.5', 1, '1.5'],
		];
	}
}
