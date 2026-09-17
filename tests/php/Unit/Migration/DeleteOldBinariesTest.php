<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Migration;

use OCA\Libresign\Migration\DeleteOldBinaries;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IAppData;
use PHPUnit\Framework\TestCase;

final class DeleteOldBinariesTest extends TestCase {
	public function testKeepsDataStoredByLibresignInAppData(): void {
		$root = $this->folder('/', false, [
			// Custom signature background uploaded by the admin, see #8415
			$this->folder('signature', false),
			$this->file('certificate-policy.pdf', false),
			$this->folder('pki', false),
			$this->folder('openssl_config', false),
			$this->folder('cfssl_config', false),
			$this->folder('generated_crl', false),
			$this->folder('jsignpdf_home', false),
			$this->folder('unauthenticated', false),
			$this->folder('guest_app', false),
		]);

		$this->deleteOldBinaries()->deleteInvalidNodes($root);
	}

	public function testDeletesLeftoversOfPreviousVersions(): void {
		$root = $this->folder('/', false, [
			$this->folder('libresign-cli', true),
			$this->folder('java', true),
			$this->file('backup-table-libresign_identify_method.csv', true),
		]);

		$this->deleteOldBinaries()->deleteInvalidNodes($root);
	}

	public function testKeepsOnlyKnownBinariesOfArchitectureFolder(): void {
		$root = $this->folder('/', false, [
			$this->folder('x86_64', false, [
				$this->folder('cfssl', false),
				$this->folder('jsignpdf', false),
				$this->folder('pdftk', false),
				$this->folder('linux', false, [
					$this->folder('java', false),
					$this->folder('java-11', true),
				]),
				$this->file('setup-cache.json', true),
			]),
		]);

		$this->deleteOldBinaries()->deleteInvalidNodes($root);
	}

	private function deleteOldBinaries(): DeleteOldBinaries {
		$appDataFactory = $this->createMock(IAppDataFactory::class);
		$appDataFactory->method('get')->willReturn($this->createMock(IAppData::class));

		return new class($appDataFactory) extends DeleteOldBinaries {
			public function deleteInvalidNodes(Folder $folder): void {
				$this->deleteRecursive($folder, $this->allowedFiles);
			}
		};
	}

	/**
	 * @param list<File|Folder> $content
	 */
	private function folder(string $name, bool $deleted, array $content = []): Folder {
		$folder = $this->createMock(Folder::class);
		$folder->method('getName')->willReturn($name);
		$folder->method('getDirectoryListing')->willReturn($content);
		$folder->expects($deleted ? $this->once() : $this->never())
			->method('delete');
		return $folder;
	}

	private function file(string $name, bool $deleted): File {
		$file = $this->createMock(File::class);
		$file->method('getName')->willReturn($name);
		$file->expects($deleted ? $this->once() : $this->never())
			->method('delete');
		return $file;
	}
}
