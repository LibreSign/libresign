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
use OCP\IDateTimeZone;
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
	private IDateTimeZone&MockObject $dateTimeZone;

	public function setUp(): void {
		parent::setUp();
		$this->root = $this->createMock(IRootFolder::class);
		$this->appDataFactory = $this->createMock(IAppDataFactory::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->dateTimeZone = $this->createMock(IDateTimeZone::class);
	}

	private function getInstance(?string $userId = '171'): FolderService {
		$service = new FolderService(
			$this->root,
			$this->appDataFactory,
			$this->groupManager,
			$this->appConfig,
			$this->l10n,
			$this->dateTimeZone,
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
}
