<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File;

use OCA\Libresign\Db\File;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\File\FileContentProvider;
use OCA\Libresign\Service\File\MimeService;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class FileContentProviderTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IClientService|MockObject $client;
	private MimeService|MockObject $mimeService;
	private IRootFolder|MockObject $root;
	private LoggerInterface|MockObject $logger;
	private IL10N|MockObject $l10n;

	public function setUp(): void {
		parent::setUp();
		$this->client = $this->createMock(IClientService::class);
		$this->mimeService = $this->createMock(MimeService::class);
		$this->root = $this->createMock(IRootFolder::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnCallback(fn ($text) => $text);
	}

	private function getService(): FileContentProvider {
		return new FileContentProvider(
			$this->client,
			$this->mimeService,
			$this->root,
			$this->logger,
			$this->l10n,
		);
	}

	public function testGetContentFromUrlSuccess(): void {
		$url = 'https://example.com/file.pdf';
		$content = 'PDF content';
		$mimeType = 'application/pdf';

		$response = $this->createMock(IResponse::class);
		$response->method('getHeader')->willReturn($mimeType);
		$response->method('getBody')->willReturn($content);

		$httpClient = $this->createMock(IClient::class);
		$httpClient->method('get')->with($url)->willReturn($response);

		$this->client->method('newClient')->willReturn($httpClient);
		$this->mimeService->method('getMimeType')->with($content)->willReturn($mimeType);

		$service = $this->getService();
		$result = $service->getContentFromUrl($url);

		$this->assertEquals($content, $result);
	}

	public function testGetContentFromUrlInvalidUrl(): void {
		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Invalid URL file');

		$service = $this->getService();
		$service->getContentFromUrl('not-a-url');
	}

	public function testGetContentFromUrlMimeTypeMismatch(): void {
		$url = 'https://example.com/file.pdf';
		$content = 'PDF content';

		$response = $this->createMock(IResponse::class);
		$response->method('getHeader')->willReturn('text/html');
		$response->method('getBody')->willReturn($content);

		$httpClient = $this->createMock(IClient::class);
		$httpClient->method('get')->willReturn($response);

		$this->client->method('newClient')->willReturn($httpClient);
		$this->mimeService->method('getMimeType')->willReturn('application/pdf');

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Invalid URL file');

		$service = $this->getService();
		$service->getContentFromUrl($url);
	}

	public function testGetContentFromUrlOctetStreamIsTrusted(): void {
		$url = 'https://raw.githubusercontent.com/LibreSign/libresign/main/tests/php/fixtures/pdfs/small_valid.pdf';
		$content = '%PDF-1.4 binary content';

		$response = $this->createMock(IResponse::class);
		// GitHub returns application/octet-stream for raw files
		$response->method('getHeader')->willReturn('application/octet-stream');
		$response->method('getBody')->willReturn($content);

		$httpClient = $this->createMock(IClient::class);
		$httpClient->method('get')->with($url)->willReturn($response);

		$this->client->method('newClient')->willReturn($httpClient);
		$this->mimeService->method('getMimeType')->with($content)->willReturn('application/pdf');

		$service = $this->getService();
		$result = $service->getContentFromUrl($url);

		$this->assertEquals($content, $result);
	}

	public function testGetContentFromUrlMimeTypeWithParameters(): void {
		$url = 'https://example.com/file.pdf';
		$content = 'PDF content';

		$response = $this->createMock(IResponse::class);
		// Content-Type with charset parameter
		$response->method('getHeader')->willReturn('application/pdf; charset=utf-8');
		$response->method('getBody')->willReturn($content);

		$httpClient = $this->createMock(IClient::class);
		$httpClient->method('get')->with($url)->willReturn($response);

		$this->client->method('newClient')->willReturn($httpClient);
		$this->mimeService->method('getMimeType')->with($content)->willReturn('application/pdf');

		$service = $this->getService();
		$result = $service->getContentFromUrl($url);

		$this->assertEquals($content, $result);
	}

	public function testGetContentFromUrlEmptyContent(): void {
		$url = 'https://example.com/file.pdf';

		$response = $this->createMock(IResponse::class);
		$response->method('getHeader')->willReturn('application/pdf');
		$response->method('getBody')->willReturn('');

		$httpClient = $this->createMock(IClient::class);
		$httpClient->method('get')->willReturn($response);

		$this->client->method('newClient')->willReturn($httpClient);

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Empty file');

		$service = $this->getService();
		$service->getContentFromUrl($url);
	}

	public function testGetContentFromUrlNetworkError(): void {
		$url = 'https://example.com/file.pdf';

		$httpClient = $this->createMock(IClient::class);
		$httpClient->method('get')->willThrowException(new \Exception('Network error'));

		$this->client->method('newClient')->willReturn($httpClient);

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Invalid URL file');

		$service = $this->getService();
		$service->getContentFromUrl($url);
	}

	public function testGetContentFromBase64WithMimeType(): void {
		$base64 = 'data:application/pdf;base64,SGVsbG8gV29ybGQ=';
		$expectedContent = 'Hello World';

		$this->mimeService
			->expects($this->once())
			->method('getMimeType')
			->willReturn('application/pdf');

		$this->mimeService
			->expects($this->once())
			->method('setMimeType')
			->with('application/pdf');

		$service = $this->getService();
		$result = $service->getContentFromBase64($base64);

		$this->assertEquals($expectedContent, $result);
	}

	public function testGetContentFromBase64WithoutMimeType(): void {
		$base64 = 'SGVsbG8gV29ybGQ=';
		$expectedContent = 'Hello World';

		$this->mimeService
			->expects($this->once())
			->method('getMimeType')
			->willReturn('text/plain');

		$service = $this->getService();
		$result = $service->getContentFromBase64($base64);

		$this->assertEquals($expectedContent, $result);
	}

	public function testGetContentFromBase64MimeTypeMismatch(): void {
		$base64 = 'data:application/pdf;base64,SGVsbG8gV29ybGQ=';

		$this->mimeService
			->method('getMimeType')
			->willReturn('text/plain');

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Invalid URL file');

		$service = $this->getService();
		$service->getContentFromBase64($base64);
	}

	public function testGetContentFromDataWithUrl(): void {
		$url = 'https://example.com/file.pdf';
		$content = 'PDF content';
		$data = ['file' => ['url' => $url]];

		$response = $this->createMock(IResponse::class);
		$response->method('getHeader')->willReturn('application/pdf');
		$response->method('getBody')->willReturn($content);

		$httpClient = $this->createMock(IClient::class);
		$httpClient->method('get')->willReturn($response);

		$this->client->method('newClient')->willReturn($httpClient);
		$this->mimeService->method('getMimeType')->willReturn('application/pdf');

		$service = $this->getService();
		$result = $service->getContentFromData($data);

		$this->assertEquals($content, $result);
	}

	public function testGetContentFromDataWithBase64(): void {
		$base64 = 'data:application/pdf;base64,SGVsbG8gV29ybGQ=';
		$data = ['file' => ['base64' => $base64]];

		$this->mimeService->method('getMimeType')->willReturn('application/pdf');
		$this->mimeService->method('setMimeType');

		$service = $this->getService();
		$result = $service->getContentFromData($data);

		$this->assertEquals('Hello World', $result);
	}

	public function testGetContentFromDataNoSource(): void {
		$data = ['file' => []];

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('No file source provided');

		$service = $this->getService();
		$service->getContentFromData($data);
	}

	public function testGetContentFromLibresignFileSuccess(): void {
		$file = new File();
		$file->setId(1);
		$file->setUserId('user123');
		$file->setSignedNodeId(123);

		$fileNode = $this->createMock(\OCP\Files\File::class);
		$fileNode->method('getContent')->willReturn('PDF content');

		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('getFirstNodeById')->with(123)->willReturn($fileNode);

		$this->root->method('getUserFolder')->with('user123')->willReturn($userFolder);

		$service = $this->getService();
		$result = $service->getContentFromLibresignFile($file);

		$this->assertEquals('PDF content', $result);
	}

	public function testGetContentFromLibresignFileUsesNodeId(): void {
		$file = new File();
		$file->setId(1);
		$file->setUserId('user123');
		$file->setSignedNodeId(null);
		$file->setNodeId(456);

		$fileNode = $this->createMock(\OCP\Files\File::class);
		$fileNode->method('getContent')->willReturn('PDF content');

		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('getFirstNodeById')->with(456)->willReturn($fileNode);

		$this->root->method('getUserFolder')->with('user123')->willReturn($userFolder);

		$service = $this->getService();
		$result = $service->getContentFromLibresignFile($file);

		$this->assertEquals('PDF content', $result);
	}

	public function testGetContentFromLibresignFileNotFound(): void {
		$file = new File();
		$file->setId(1);
		$file->setUserId('user123');
		$file->setSignedNodeId(null);
		$file->setNodeId(456);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('getFirstNodeById')->willReturn(null);

		$this->root->method('getUserFolder')->with('user123')->willReturn($userFolder);

		$this->expectException(LibresignException::class);

		$service = $this->getService();
		$service->getContentFromLibresignFile($file);
	}

	public function testGetContentFromLibresignFileIsFolder(): void {
		$file = new File();
		$file->setId(1);
		$file->setUserId('user123');
		$file->setSignedNodeId(123);

		$folderNode = $this->createMock(Folder::class);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('getFirstNodeById')->with(123)->willReturn($folderNode);

		$this->root->method('getUserFolder')->with('user123')->willReturn($userFolder);

		$this->expectException(LibresignException::class);

		$service = $this->getService();
		$service->getContentFromLibresignFile($file);
	}
}
