<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Service\FolderService;

/**
 * @internal
 * @group DB
 */
final class FolderServiceDbTest extends \OCA\Libresign\Tests\Unit\TestCase {
	/** @runInSeparateProcess */
	public function testGetFolderRestoresMissingUserFilesystemBeforeWriting(): void {
		$user = $this->createAccount('folderserviceuser', 'password');
		$userFilesDirectory = $user->getHome() . '/files';
		$this->removeDirectory($userFilesDirectory);

		self::assertDirectoryDoesNotExist($userFilesDirectory);

		$service = \OCP\Server::get(FolderService::class);
		$service->setUserId($user->getUID());
		$folder = $service->getFolder();
		$folder->newFile('signature.pfx', 'test pfx content');

		/** @var \OCP\Files\File $file */
		$file = $folder->get('signature.pfx');
		self::assertSame('test pfx content', $file->getContent());
		self::assertDirectoryExists($userFilesDirectory . '/LibreSign');
	}

	private function removeDirectory(string $path): void {
		if (!is_dir($path)) {
			return;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST,
		);

		foreach ($iterator as $item) {
			if ($item->isDir()) {
				@rmdir($item->getPathname());
				continue;
			}

			@unlink($item->getPathname());
		}

		@rmdir($path);
	}
}
