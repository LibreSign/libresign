<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Install;

use bovigo\vfs\vfsStream;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\Install\DependencyDownloader;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class DependencyDownloaderTest extends TestCase {
	private IClientService&MockObject $clientService;
	private LoggerInterface&MockObject $logger;
	private DependencyDownloader $downloader;

	#[\Override]
	protected function setUp(): void {
		$this->clientService = $this->createMock(IClientService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->downloader = new DependencyDownloader($this->clientService, $this->logger);
		vfsStream::setup('download');
	}

	#[DataProvider('checksumListProvider')]
	public function testFetchChecksumFindsArtifact(string $content, string $file, string $expected): void {
		$response = $this->createMock(IResponse::class);
		$response->method('getBody')->willReturn($content);
		$client = $this->createMock(IClient::class);
		$client->expects($this->once())
			->method('get')
			->with('https://example.invalid/checksums.txt')
			->willReturn($response);
		$this->clientService->method('newClient')->willReturn($client);

		$this->assertSame(
			$expected,
			$this->downloader->fetchChecksum($file, 'https://example.invalid/checksums.txt'),
		);
	}

	public static function checksumListProvider(): array {
		return [
			'single entry' => [
				"abc123  dependency.tar.gz\n",
				'dependency.tar.gz',
				'abc123',
			],
			'select requested artifact' => [
				"abc123  other-file\ndef456  dependency.tar.gz\n",
				'dependency.tar.gz',
				'def456',
			],
			'regex characters in artifact name' => [
				"abcdef  dependency+linux.tar.gz\n",
				'dependency+linux.tar.gz',
				'abcdef',
			],
		];
	}

	#[DataProvider('invalidChecksumListProvider')]
	public function testFetchChecksumRejectsInvalidMetadata(string $content, string $expectedMessage): void {
		$response = $this->createMock(IResponse::class);
		$response->method('getBody')->willReturn($content);
		$client = $this->createMock(IClient::class);
		$client->method('get')->willReturn($response);
		$this->clientService->method('newClient')->willReturn($client);

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage($expectedMessage);

		$this->downloader->fetchChecksum('dependency.tar.gz', 'https://example.invalid/checksums.txt');
	}

	public static function invalidChecksumListProvider(): array {
		return [
			'empty metadata' => [
				'',
				'checksum information for dependency.tar.gz was empty',
			],
			'artifact missing' => [
				"abc123  another-file\n",
				'checksum list does not contain an entry for dependency.tar.gz',
			],
		];
	}

	public function testFetchChecksumReportsNetworkConfigurationFailure(): void {
		$client = $this->createMock(IClient::class);
		$client->method('get')
			->willThrowException(new \RuntimeException('network unavailable'));
		$this->clientService->method('newClient')->willReturn($client);

		try {
			$this->downloader->fetchChecksum('dependency.tar.gz', 'https://example.invalid/checksums.txt');
			$this->fail('Expected download failure');
		} catch (LibresignException $e) {
			$this->assertStringContainsString('network, DNS and proxy configuration', $e->getMessage());
			$this->assertSame('network unavailable', $e->getPrevious()?->getMessage());
		}
	}

	public function testDownloadReportsTransportFailureWithoutExposingClientError(): void {
		$client = $this->createMock(IClient::class);
		$client->method('get')
			->willThrowException(new \RuntimeException('cURL error 28: secret details'));
		$this->clientService->method('newClient')->willReturn($client);

		try {
			$this->downloader->download(
				'https://example.invalid/dependency.bin',
				'dependency',
				'vfs://download/dependency.bin',
			);
			$this->fail('Expected download failure');
		} catch (LibresignException $e) {
			$this->assertStringContainsString('Could not download dependency.', $e->getMessage());
			$this->assertStringNotContainsString('cURL error', $e->getMessage());
			$this->assertSame('cURL error 28: secret details', $e->getPrevious()?->getMessage());
		}
	}

	public function testDownloadFailsWhenExpectedFileWasNotCreated(): void {
		$client = $this->createMock(IClient::class);
		$client->method('get')->willReturn($this->createMock(IResponse::class));
		$this->clientService->method('newClient')->willReturn($client);

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('did not produce the expected file');

		$this->downloader->download(
			'https://example.invalid/dependency.bin',
			'dependency',
			'vfs://download/dependency.bin',
		);
	}

	public function testDownloadRejectsHashMismatch(): void {
		$path = 'vfs://download/dependency.bin';
		file_put_contents($path, 'content');

		$client = $this->createMock(IClient::class);
		$client->method('get')->willReturn($this->createMock(IResponse::class));
		$this->clientService->method('newClient')->willReturn($client);

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Checksum verification failed for dependency.');

		$this->downloader->download(
			'https://example.invalid/dependency.bin',
			'dependency',
			$path,
			'invalid',
			'sha256',
		);
	}

	public function testDownloadReusesValidExistingArtifact(): void {
		$path = 'vfs://download/dependency.bin';
		file_put_contents($path, 'content');

		$this->clientService->expects($this->never())->method('newClient');
		$progress = [];

		$this->downloader->download(
			'https://example.invalid/dependency.bin',
			'dependency',
			$path,
			hash('sha256', 'content'),
			'sha256',
			static function (int $total, int $downloaded) use (&$progress): void {
				$progress = [$total, $downloaded];
			},
		);

		$this->assertSame([7, 7], $progress);
	}
}
