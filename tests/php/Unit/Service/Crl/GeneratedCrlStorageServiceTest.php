<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Crl;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Crl\GeneratedCrlStorageService;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class GeneratedCrlStorageServiceTest extends TestCase {
	private IAppDataFactory&MockObject $appDataFactory;
	private IAppData&MockObject $appData;
	private IRootFolder&MockObject $rootFolder;
	private GeneratedCrlStorageService $service;

	protected function setUp(): void {
		$this->appDataFactory = $this->createMock(IAppDataFactory::class);
		$this->appData = $this->createMock(IAppData::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);

		$this->service = new GeneratedCrlStorageService(
			$this->appDataFactory,
			$this->rootFolder,
		);

		$this->appDataFactory->method('get')
			->with(Application::APP_ID)
			->willReturn($this->appData);
	}

	public static function scopeKeyProvider(): array {
		return [
			'short openssl alias' => ['o', 'instance-a/1/o'],
			'long openssl name' => ['openssl', 'instance-a/1/o'],
			'short cfssl alias' => ['c', 'instance-a/1/c'],
			'long cfssl name' => ['cfssl', 'instance-a/1/c'],
		];
	}

	#[DataProvider('scopeKeyProvider')]
	public function testGetScopeKeyNormalizesEngineAliases(string $engineType, string $expected): void {
		$this->assertSame($expected, $this->service->getScopeKey('instance-a', 1, $engineType));
	}

	public static function persistedScopeProvider(): array {
		return [
			'missing scope' => [null, null, null, null, false],
			'crl only' => ['DER-CONTENT', null, 'DER-CONTENT', null, true],
			'metadata only' => [null, '{"refreshDate":"2026-06-14","engineType":"o"}', null, ['refreshDate' => '2026-06-14', 'engineType' => 'o'], false],
			'valid metadata' => ['DER-CONTENT', '{"refreshDate":"2026-06-14","engineType":"o"}', 'DER-CONTENT', ['refreshDate' => '2026-06-14', 'engineType' => 'o'], true],
			'invalid metadata json' => ['DER-CONTENT', '{invalid-json', 'DER-CONTENT', null, true],
		];
	}

	#[DataProvider('persistedScopeProvider')]
	public function testReadMetadataAndMTimeFollowPersistedAppDataRules(
		?string $crlContent,
		?string $metadataContent,
		?string $expectedRead,
		?array $expectedMetadata,
		bool $expectMTime,
	): void {
		$this->mockScopeLookup($crlContent, $metadataContent);

		$this->assertSame($expectedRead, $this->service->read('instance-a', 1, 'o'));
		$this->assertSame($expectedMetadata, $this->service->readMetadata('instance-a', 1, 'o'));

		$mtime = $this->service->getMTime('instance-a', 1, 'o');
		if ($expectMTime) {
			$this->assertIsInt($mtime);
			$this->assertGreaterThan(0, $mtime);
		} else {
			$this->assertNull($mtime);
		}
	}

	public function testDeleteRemovesPersistedScope(): void {
		/** @var ISimpleFolder&MockObject $scopeFolder */
		$scopeFolder = $this->mockScopeLookup('DER-CONTENT', '{"refreshDate":"2026-06-14"}');
		$scopeFolder->expects($this->once())
			->method('delete');

		$this->service->delete('instance-a', 1, 'o');
	}

	public function testDeleteIgnoresMissingScopes(): void {
		$this->mockScopeLookup(null, null);

		$this->service->delete('instance-a', 1, 'o');

		$this->addToAssertionCount(1);
	}

	public function testReadMetadataAndMTimeReturnNullWhenAppDataRootResolutionHitsKnownBootstrapTypeError(): void {
		$this->appData->expects($this->exactly(3))
			->method('getFolder')
			->with('/')
			->willThrowException($this->createKnownAppDataBootstrapTypeError());

		$this->assertNull($this->service->read('instance-a', 1, 'o'));
		$this->assertNull($this->service->readMetadata('instance-a', 1, 'o'));
		$this->assertNull($this->service->getMTime('instance-a', 1, 'o'));
	}

	public function testDeleteIgnoresKnownBootstrapTypeErrorWhenAppDataRootIsUnavailable(): void {
		$this->appData->expects($this->once())
			->method('getFolder')
			->with('/')
			->willThrowException($this->createKnownAppDataBootstrapTypeError());

		$this->service->delete('instance-a', 1, 'o');

		$this->addToAssertionCount(1);
	}

	public static function simpleFsWriteFallbackProvider(): array {
		return [
			'create fresh files' => [false],
			'overwrite existing files' => [true],
		];
	}

	#[DataProvider('simpleFsWriteFallbackProvider')]
	public function testWriteFallsBackToSimpleFsWhenRichAppDataNodesCannotBeResolved(bool $filesAlreadyExist): void {
		$folder = $this->createMock(ISimpleFolder::class);
		$createdFiles = [];
		$updatedFiles = [];

		$this->appData->expects($this->once())
			->method('getFolder')
			->with('/')
			->willReturn($folder);

		$folder->expects($this->exactly(4))
			->method('getFolder')
			->willThrowException(new NotFoundException());

		$folder->expects($this->exactly(4))
			->method('newFolder')
			->willReturn($folder);

		$this->rootFolder->method('getAppDataDirectoryName')
			->willReturn('appdata_test');

		$this->rootFolder->expects($this->exactly(2))
			->method('get')
			->willThrowException(new \TypeError('Cannot assign null to property OC\\Files\\Cache\\Scanner::$connection of type OCP\\IDBConnection'));

		$folder->expects($this->exactly(2))
			->method('fileExists')
			->with($this->callback(static fn (string $fileName): bool => in_array($fileName, ['crl.der', 'meta.json'], true)))
			->willReturn($filesAlreadyExist);

		if ($filesAlreadyExist) {
			$fileMocks = [
				'crl.der' => $this->createWritableFileMock('crl.der', $updatedFiles),
				'meta.json' => $this->createWritableFileMock('meta.json', $updatedFiles),
			];

			$folder->expects($this->exactly(2))
				->method('getFile')
				->willReturnCallback(static fn (string $name): ISimpleFile => $fileMocks[$name]);

			$folder->expects($this->never())
				->method('newFile');
		} else {
			$folder->expects($this->never())
				->method('getFile');

			$folder->expects($this->exactly(2))
				->method('newFile')
				->willReturnCallback(function (string $name, mixed $content = null) use (&$createdFiles): ISimpleFile {
					$createdFiles[$name] = $this->normalizeContent($content);
					return $this->createMock(ISimpleFile::class);
				});
		}

		$this->service->write('instance-a', 1, 'o', 'DER-CONTENT', [
			'refreshDate' => '2026-06-14',
			'engineType' => 'o',
		]);

		if ($filesAlreadyExist) {
			$this->assertSame('DER-CONTENT', $updatedFiles['crl.der'] ?? null);
			$this->assertSame([
				'refreshDate' => '2026-06-14',
				'engineType' => 'o',
			], json_decode($updatedFiles['meta.json'] ?? '', true));
			return;
		}

		$this->assertSame('DER-CONTENT', $createdFiles['crl.der'] ?? null);
		$this->assertSame([
			'refreshDate' => '2026-06-14',
			'engineType' => 'o',
		], json_decode($createdFiles['meta.json'] ?? '', true));
	}

	public function testWriteSkipsPersistenceWhenSimpleAppDataRootCannotBeResolved(): void {
		$this->appData->expects($this->once())
			->method('getFolder')
			->with('/')
			->willThrowException($this->createKnownAppDataBootstrapTypeError());

		$this->rootFolder->expects($this->never())
			->method('get');

		$this->service->write('instance-a', 1, 'o', 'DER-CONTENT', [
			'refreshDate' => '2026-06-14',
			'engineType' => 'o',
		]);

		$this->addToAssertionCount(1);
	}

	private function mockScopeLookup(?string $crlContent, ?string $metadataContent): ISimpleFolder&MockObject {
		$rootFolder = $this->createMock(ISimpleFolder::class);
		$generatedFolder = $this->createMock(ISimpleFolder::class);
		$instanceFolder = $this->createMock(ISimpleFolder::class);
		$generationFolder = $this->createMock(ISimpleFolder::class);
		$scopeFolder = $this->createMock(ISimpleFolder::class);

		$this->appData->method('getFolder')
			->with('/')
			->willReturn($rootFolder);

		$rootFolder->method('getFolder')
			->with('generated_crl')
			->willReturn($generatedFolder);
		$generatedFolder->method('getFolder')
			->with('instance-a')
			->willReturn($instanceFolder);
		$instanceFolder->method('getFolder')
			->with('1')
			->willReturn($generationFolder);

		if ($crlContent === null && $metadataContent === null) {
			$generationFolder->method('getFolder')
				->with('o')
				->willThrowException(new NotFoundException());

			return $scopeFolder;
		}

		$generationFolder->method('getFolder')
			->with('o')
			->willReturn($scopeFolder);

		$fileMap = [];
		if ($crlContent !== null) {
			$fileMap['crl.der'] = $this->createFileMock($crlContent, 1700000001);
		}
		if ($metadataContent !== null) {
			$fileMap['meta.json'] = $this->createFileMock($metadataContent, 1700000002);
		}

		$scopeFolder->method('fileExists')
			->willReturnCallback(static fn (string $name): bool => isset($fileMap[$name]));
		$scopeFolder->method('getFile')
			->willReturnCallback(function (string $name) use ($fileMap): ISimpleFile {
				if (!isset($fileMap[$name])) {
					throw new NotFoundException(sprintf('File %s not found', $name));
				}

				return $fileMap[$name];
			});

		return $scopeFolder;
	}

	private function createFileMock(string $content, int $mtime): ISimpleFile {
		$file = $this->createMock(ISimpleFile::class);
		$file->method('getContent')
			->willReturn($content);
		$file->method('getMTime')
			->willReturn($mtime);

		return $file;
	}

	/**
	 * @param array<string, string> $updatedFiles
	 */
	private function createWritableFileMock(string $fileName, array &$updatedFiles): ISimpleFile {
		$file = $this->createMock(ISimpleFile::class);
		$file->method('putContent')
			->willReturnCallback(function (mixed $content) use ($fileName, &$updatedFiles): void {
				$updatedFiles[$fileName] = $this->normalizeContent($content);
			});

		return $file;
	}

	private function normalizeContent(mixed $content): string {
		if (is_resource($content)) {
			$normalized = stream_get_contents($content);
			return is_string($normalized) ? $normalized : '';
		}

		if (is_string($content)) {
			return $content;
		}

		return '';
	}

	private function createKnownAppDataBootstrapTypeError(): \TypeError {
		return new \TypeError('Cannot assign null to property OC\\Files\\Cache\\Scanner::$connection of type OCP\\IDBConnection');
	}
}
