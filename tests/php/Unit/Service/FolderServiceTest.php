<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use Exception;
use OCA\Libresign\Service\FolderService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\Folder;
use OCP\Files\IAppData;
use OCP\Files\IRootFolder;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class FakeFolder implements ISimpleFolder {
	public Folder $folder;

	public function getName(): string {
		return 'fake';
	}

	public function getDirectoryListing(): array {
		return [];
	}

	public function delete(): void {
	}

	public function fileExists(string $name): bool {
		return false;
	}

	public function getFile(string $name): ISimpleFile {
		throw new Exception('fake class');
	}

	public function newFile(string $name, $content = null): ISimpleFile {
		throw new Exception('fake class');
	}

	public function getFolder(string $name): ISimpleFolder {
		throw new Exception('fake class');
	}

	public function newFolder(string $path): ISimpleFolder {
		throw new Exception('fake class');
	}
}

final class FolderServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IRootFolder&MockObject $root;
	private IAppDataFactory&MockObject $appDataFactory;
	private IGroupManager&MockObject $groupManager;
	private IAppConfig&MockObject $appConfig;
	private IL10N&MockObject $l10n;

	public function setUp(): void {
		parent::setUp();
		$this->root = $this->createMock(IRootFolder::class);
		$this->appDataFactory = $this->createMock(IAppDataFactory::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->l10n = $this->createMock(IL10N::class);
	}

	private function getInstance(?string $userId = '171'): FolderService {
		$service = new FolderService(
			$this->root,
			$this->appDataFactory,
			$this->groupManager,
			$this->appConfig,
			$this->l10n,
			$userId
		);
		return $service;
	}

	public function testGetContainerFolderAsUnauthenticatedWhenUserIdIsInvalid():void {
		$folder = $this->createMock(\OCP\Files\Folder::class);
		$fakeFolder = new FakeFolder();
		$fakeFolder->folder = $folder;
		$appData = $this->createMock(IAppData::class);
		$appData->method('getFolder')->willReturn($fakeFolder);
		$this->appDataFactory->method('get')->willReturn($appData);

		$service = $this->getInstance(userId: null);
		$actual = $this->invokePrivate($service, 'getContainerFolder');
		$this->assertEquals($folder, $actual);
	}

	#[DataProvider('providerGetFolderName')]
	public function testGetFolderName(array $data, string $uid, ?string $expectExact, ?string $expectRegex): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);

		$service = $this->getInstance();
		$actual = $service->getFolderName($data, $user);

		if ($expectExact !== null) {
			self::assertSame($expectExact, $actual);
		} else {
			self::assertMatchesRegularExpression($expectRegex, $actual);
		}
	}

	public static function providerGetFolderName(): array {
		$defaultDateRegex = '[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}-[0-9]{2}-[0-9]{2}-[0-9]{6}';

		return [
			'explicit-folderName' => [
				'data' => [
					'name' => 'MyDoc',
					'settings' => [
						'folderName' => 'predefinido/abc',
					],
				],
				'uid' => 'u1',
				'expectExact' => 'predefinido/abc',
				'expectRegex' => null,
			],
			'defaults-with-name' => [
				'data' => [
					'name' => 'MyDoc',
				],
				'uid' => 'u1',
				'expectExact' => null,
				'expectRegex' => '/^' . $defaultDateRegex . '_MyDoc_u1$/',
			],
			'defaults-without-name' => [
				'data' => [
					'settings' => [],
				],
				'uid' => 'u1',
				'expectExact' => null,
				'expectRegex' => '/^' . $defaultDateRegex . '_u1$/',
			],
			'custom-order-no-date' => [
				'data' => [
					'name' => 'Doc',
					'settings' => [
						'separator' => '-',
						'folderPatterns' => [
							['name' => 'name'],
							['name' => 'userId'],
						],
					],
				],
				'uid' => 'u1',
				'expectExact' => null,
				'expectRegex' => '/^Doc-u1$/',
			],
			'custom-date-format' => [
				'data' => [
					'name' => 'Doc',
					'settings' => [
						'separator' => '.',
						'folderPatterns' => [
							['name' => 'date', 'setting' => 'Ymd'],
							['name' => 'name'],
							['name' => 'userId'],
						],
					],
				],
				'uid' => 'u1',
				'expectExact' => null,
				'expectRegex' => '/^[0-9]{8}\.Doc\.u1$/',
			],
			'only-date' => [
				'data' => [
					'settings' => [
						'separator' => '/',
						'folderPatterns' => [
							['name' => 'date', 'setting' => 'Y-m-d\TH-i-s-u'],
						],
					],
				],
				'uid' => 'u1',
				'expectExact' => null,
				'expectRegex' => '/^' . $defaultDateRegex . '$/',
			],
			'unknown-pattern-ignored' => [
				'data' => [
					'name' => 'Doc',
					'settings' => [
						'separator' => '+',
						'folderPatterns' => [
							['name' => 'foo'],
							['name' => 'name'],
						],
					],
				],
				'uid' => 'u1',
				'expectExact' => null,
				'expectRegex' => '/^Doc$/',
			],
			'name-empty-token-ignored' => [
				'data' => [
					'name' => '',
					'settings' => [
						'separator' => '_',
						'folderPatterns' => [
							['name' => 'date', 'setting' => 'Y-m-d\TH-i-s-u'],
							['name' => 'name'],
							['name' => 'userId'],
						],
					],
				],
				'uid' => 'u1',
				'expectExact' => null,
				'expectRegex' => '/^' . $defaultDateRegex . '_u1$/',
			],
			'only-userId' => [
				'data' => [
					'settings' => [
						'separator' => '=',
						'folderPatterns' => [
							['name' => 'userId'],
						],
					],
				],
				'uid' => 'user-XYZ',
				'expectExact' => 'user-XYZ',
				'expectRegex' => null,
			],
			'only-name' => [
				'data' => [
					'name' => 'OnlyName',
					'settings' => [
						'separator' => '--',
						'folderPatterns' => [
							['name' => 'name'],
						],
					],
				],
				'uid' => 'u1',
				'expectExact' => 'OnlyName',
				'expectRegex' => null,
			],
			'multichar-separator-with-all' => [
				'data' => [
					'name' => 'DocX',
					'settings' => [
						'separator' => '~~',
						'folderPatterns' => [
							['name' => 'userId'],
							['name' => 'name'],
							['name' => 'date', 'setting' => 'YmdHis'],
						],
					],
				],
				'uid' => 'U777',
				'expectExact' => null,
				'expectRegex' => '/^U777~~DocX~~[0-9]{14}$/',
			],
			'settings-present-without-folderPatterns' => [
				'data' => [
					'name' => 'WithSettings',
					'settings' => [],
				],
				'uid' => 'u9',
				'expectExact' => null,
				'expectRegex' => '/^' . $defaultDateRegex . '_WithSettings_u9$/',
			],
		];
	}


	#[DataProvider('providerGetFolderNameWithStringIdentifier')]
	public function testGetFolderNameWithStringIdentifier(array $data, string $identifier, ?string $expectExact, ?string $expectRegex): void {
		$service = $this->getInstance();
		$actual = $service->getFolderName($data, $identifier);

		if ($expectExact !== null) {
			self::assertSame($expectExact, $actual);
		} else {
			self::assertMatchesRegularExpression($expectRegex, $actual);
		}
	}

	public static function providerGetFolderNameWithStringIdentifier(): array {
		$defaultDateRegex = '[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}-[0-9]{2}-[0-9]{2}-[0-9]{6}';

		return [
			'string-id-empty-defaults' => [
				'data' => [
					'name' => 'Doc',
					'settings' => [],
				],
				'identifier' => '',
				'expectExact' => null,
				'expectRegex' => '/^' . $defaultDateRegex . '_Doc$/',
			],
			'string-id-empty-defaults-without-name' => [
				'data' => [
					'name' => '',
					'settings' => [],
				],
				'identifier' => '',
				'expectExact' => null,
				'expectRegex' => '/^' . $defaultDateRegex . '$/',
			],
			'string-id-empty-only-userId' => [
				'data' => [
					'settings' => [
						'separator' => '_',
						'folderPatterns' => [
							['name' => 'userId'],
						],
					],
				],
				'identifier' => '',
				'expectExact' => '',
				'expectRegex' => null,
			],
			'string-id-zero-only-userId' => [
				'data' => [
					'settings' => [
						'separator' => '_',
						'folderPatterns' => [
							['name' => 'userId'],
						],
					],
				],
				'identifier' => '0',
				'expectExact' => '',
				'expectRegex' => null,
			],
			'string-id-nonempty' => [
				'data' => [
					'name' => 'Doc',
					'settings' => [
						'separator' => '-',
						'folderPatterns' => [
							['name' => 'name'],
							['name' => 'userId'],
						],
					],
				],
				'identifier' => 'guest-123',
				'expectExact' => 'Doc-guest-123',
				'expectRegex' => null,
			],
		];
	}

	#[DataProvider('providerGetFolderNameWithNonStringIdentifier')]
	public function testGetFolderNameWithNonStringIdentifier(array $data, mixed $identifier, string $expected): void {
		$service = $this->getInstance();
		$actual = $service->getFolderName($data, $identifier);

		self::assertSame($expected, $actual);
	}

	public static function providerGetFolderNameWithNonStringIdentifier(): array {
		return [
			'null-identifier-ignored' => [
				'data' => [
					'name' => 'Doc',
					'settings' => [
						'separator' => '-',
						'folderPatterns' => [
							['name' => 'name'],
							['name' => 'userId'],
						],
					],
				],
				'identifier' => null,
				'expected' => 'Doc',
			],
			'int-identifier-ignored' => [
				'data' => [
					'name' => 'Doc',
					'settings' => [
						'separator' => '-',
						'folderPatterns' => [
							['name' => 'name'],
							['name' => 'userId'],
						],
					],
				],
				'identifier' => 123,
				'expected' => 'Doc',
			],
		];
	}

	public function testGetFolderForFileUsesEnvelopeFolderWhenProvided(): void {
		$envelopeFolderId = 456;
		$data = [
			'settings' => [
				'envelopeFolderId' => $envelopeFolderId,
			],
		];

		$mockUserFolder = $this->createMock(Folder::class);
		$mockEnvelopeFolder = $this->createMock(Folder::class);

		$mockUserFolder->expects($this->once())
			->method('getFirstNodeById')
			->with($envelopeFolderId)
			->willReturn($mockEnvelopeFolder);

		$this->appConfig->method('getUserValue')->willReturn('/LibreSign');
		$this->groupManager->method('isInGroup')->willReturn(false);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('isUpdateable')->willReturn(true);
		$userFolder->method('get')->willReturn($mockUserFolder);
		$this->root->method('getUserFolder')->willReturn($userFolder);

		$service = $this->getInstance('testuser');
		$result = $service->getFolderForFile($data, 'testuser');

		$this->assertInstanceOf(Folder::class, $result);
	}

	public function testGetFolderForFileCreatesNewFolderWhenNoEnvelopeId(): void {
		$data = [
			'name' => 'Document',
			'settings' => [],
		];

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');

		$mockUserFolder = $this->createMock(Folder::class);
		$mockNewFolder = $this->createMock(Folder::class);

		$mockUserFolder->expects($this->once())
			->method('newFolder')
			->willReturn($mockNewFolder);

		$this->appConfig->method('getUserValue')->willReturn('/LibreSign');
		$this->groupManager->method('isInGroup')->willReturn(false);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('isUpdateable')->willReturn(true);
		$userFolder->method('get')->willReturn($mockUserFolder);
		$this->root->method('getUserFolder')->willReturn($userFolder);

		$service = $this->getInstance('user1');
		$result = $service->getFolderForFile($data, $user);

		$this->assertInstanceOf(Folder::class, $result);
	}

	public function testGetUserRootFolderReturnsUserFolder(): void {
		$mockUserFolder = $this->createMock(Folder::class);
		$this->root->expects($this->once())
			->method('getUserFolder')
			->with('171')
			->willReturn($mockUserFolder);

		$service = $this->getInstance('171');
		$result = $service->getUserRootFolder();

		$this->assertSame($mockUserFolder, $result);
	}

	#[DataProvider('providerGetOrCreateFolderByAbsolutePath')]
	public function testGetOrCreateFolderByAbsolutePathCreatesNestedFolders(
		string $path,
		array $existingFolders,
		array $expectedNewFolders,
	): void {
		$mockUserFolder = $this->createMock(Folder::class);
		$this->root->method('getUserFolder')->willReturn($mockUserFolder);

		$currentFolder = $mockUserFolder;
		$segments = array_filter(explode('/', ltrim($path, '/')));

		foreach ($segments as $index => $segment) {
			if (in_array($segment, $existingFolders)) {
				$existingFolder = $this->createMock(Folder::class);
				$existingFolder->method('getDirectoryListing')->willReturn([]);
				$currentFolder->method('get')
					->with($segment)
					->willReturn($existingFolder);
				$currentFolder = $existingFolder;
			} elseif (in_array($segment, $expectedNewFolders)) {
				$currentFolder->method('get')
					->with($segment)
					->willThrowException(new \OCP\Files\NotFoundException());

				$newFolder = $this->createMock(Folder::class);
				$newFolder->method('getDirectoryListing')->willReturn([]);
				$currentFolder->method('newFolder')
					->with($segment)
					->willReturn($newFolder);
				$currentFolder = $newFolder;
			}
		}

		$service = $this->getInstance('171');
		$result = $service->getOrCreateFolderByAbsolutePath($path);

		$this->assertInstanceOf(Folder::class, $result);
	}

	public static function providerGetOrCreateFolderByAbsolutePath(): array {
		return [
			'create single folder at root' => [
				'/Envelopes',
				[],
				['Envelopes'],
			],
			'create nested folders' => [
				'/Documents/Legal/Contracts',
				[],
				['Documents', 'Legal', 'Contracts'],
			],
			'use existing folder' => [
				'/Existing',
				['Existing'],
				[],
			],
			'create inside existing folder' => [
				'/Documents/NewFolder',
				['Documents'],
				['NewFolder'],
			],
		];
	}

	public function testGetOrCreateFolderByAbsolutePathFailsWhenFolderNotEmpty(): void {
		$mockUserFolder = $this->createMock(Folder::class);
		$this->root->method('getUserFolder')->willReturn($mockUserFolder);

		$existingFolder = $this->createMock(Folder::class);
		$existingFile = $this->createMock(\OCP\Files\File::class);
		$existingFolder->method('getDirectoryListing')->willReturn([$existingFile]);

		$mockUserFolder->method('get')
			->with('NotEmpty')
			->willReturn($existingFolder);

		$service = $this->getInstance('171');

		$this->expectException(\OCA\Libresign\Exception\LibresignException::class);
		$service->getOrCreateFolderByAbsolutePath('/NotEmpty');
	}
}
