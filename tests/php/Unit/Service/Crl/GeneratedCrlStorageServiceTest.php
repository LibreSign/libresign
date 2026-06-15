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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GeneratedCrlStorageServiceTest extends TestCase {
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
	}

	public function testReadReturnsNullWhenScopeDoesNotExistWithoutUsingRootFolder(): void {
		$folder = $this->createMock(ISimpleFolder::class);

		$this->appDataFactory->expects($this->once())
			->method('get')
			->with(Application::APP_ID)
			->willReturn($this->appData);

		$this->appData->expects($this->once())
			->method('getFolder')
			->with('/')
			->willReturn($folder);

		$folder->expects($this->once())
			->method('getFolder')
			->with('generated_crl')
			->willThrowException(new NotFoundException());

		$this->rootFolder->expects($this->never())
			->method('get');

		$this->assertNull($this->service->read('instance-a', 1, 'o'));
	}

	public function testWriteFallsBackToSimpleFolderWhenRootFolderLookupTypeErrors(): void {
		$folder = $this->createMock(ISimpleFolder::class);
		$file = $this->createMock(ISimpleFile::class);
		$createdFolders = [];
		$createdFiles = [];

		$this->appDataFactory->expects($this->once())
			->method('get')
			->with(Application::APP_ID)
			->willReturn($this->appData);

		$this->appData->expects($this->once())
			->method('getFolder')
			->with('/')
			->willReturn($folder);

		$folder->expects($this->exactly(4))
			->method('getFolder')
			->willThrowException(new NotFoundException());

		$folder->expects($this->exactly(4))
			->method('newFolder')
			->willReturnCallback(function (string $name) use (&$createdFolders, $folder): ISimpleFolder {
				$createdFolders[] = $name;
				return $folder;
			});

		$this->rootFolder->method('getAppDataDirectoryName')
			->willReturn('appdata_test');

		$this->rootFolder->expects($this->exactly(2))
			->method('get')
			->willThrowException(new \TypeError('Cannot assign null to property OC\\Files\\Cache\\Scanner::$connection of type OCP\\IDBConnection'));

		$folder->expects($this->exactly(2))
			->method('fileExists')
			->with($this->callback(static fn (string $fileName): bool => in_array($fileName, ['crl.der', 'meta.json'], true)))
			->willReturn(false);

		$folder->expects($this->never())
			->method('getFile');

		$folder->expects($this->exactly(2))
			->method('newFile')
			->willReturnCallback(function (string $name, mixed $content) use (&$createdFiles, $file): ISimpleFile {
				$createdFiles[$name] = $content;
				return $file;
			});

		$this->service->write('instance-a', 1, 'o', 'DER-CONTENT', [
			'refreshDate' => '2026-06-14',
		]);

		$this->assertSame(['generated_crl', 'instance-a', '1', 'o'], $createdFolders);
		$this->assertSame('DER-CONTENT', $createdFiles['crl.der'] ?? null);
		$this->assertSame(['refreshDate' => '2026-06-14'], json_decode($createdFiles['meta.json'] ?? '', true));
	}
}
