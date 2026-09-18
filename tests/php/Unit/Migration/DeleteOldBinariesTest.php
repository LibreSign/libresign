<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Migration;

use OCA\Libresign\Migration\DeleteOldBinaries;
use OCA\Libresign\Service\Install\InstallService;
use OCA\Libresign\Service\Install\JSignPdfRelease;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IAppData;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use PHPUnit\Framework\TestCase;

final class DeleteOldBinariesTest extends TestCase {
	private const CURRENT_JAVA = 'jdk-' . InstallService::JAVA_URL_PATH_NAME . '-jre';
	private const CURRENT_JSIGNPDF = 'jsignpdf-' . JSignPdfRelease::VERSION;

	public function testDeletesBinaryFoldersOfThePreArchitectureLayout(): void {
		$root = $this->root([
			'libresign-cli' => $this->folder('libresign-cli', true),
			'java' => $this->folder('java', true),
			'jsignpdf' => $this->folder('jsignpdf', true),
			'pdftk' => $this->folder('pdftk', true),
			'cfssl' => $this->folder('cfssl', true),
		]);

		$this->deleteOldBinaries()->deleteObsoleteBinaries($root);
	}

	public function testDeletesOnlyOutdatedJSignPdfReleases(): void {
		$root = $this->root([
			'x86_64/jsignpdf' => $this->folder('jsignpdf', false, [
				$this->folder(self::CURRENT_JSIGNPDF, false),
				$this->folder('jsignpdf-2.3.0', true),
			]),
			'aarch64/jsignpdf' => $this->folder('jsignpdf', false, [
				$this->folder(self::CURRENT_JSIGNPDF, false),
				$this->folder('jsignpdf-2.3.0', true),
			]),
		]);

		$this->deleteOldBinaries()->deleteObsoleteBinaries($root);
	}

	public function testDeletesOnlyOutdatedJavaReleasesOfEveryArchitectureAndDistribution(): void {
		$nodes = [];
		foreach (['x86_64', 'aarch64'] as $architecture) {
			foreach (['linux', 'alpine-linux'] as $distribution) {
				$nodes[$architecture . '/' . $distribution . '/java'] = $this->folder('java', false, [
					$this->folder(self::CURRENT_JAVA, false),
					$this->folder('jdk-21.0.2+13-jre', true),
				]);
			}
		}

		$this->deleteOldBinaries()->deleteObsoleteBinaries($this->root($nodes));
	}

	public function testNeverDeletesEntriesItDoesNotKnowAbout(): void {
		$root = $this->root([
			// Admin customizations and signer files, see #8415
			'signature' => $this->folder('signature', false),
			'certificate-policy.pdf' => $this->file('certificate-policy.pdf', false),
			'guest_app' => $this->folder('guest_app', false),
			'generated_crl' => $this->folder('generated_crl', false),
			'jsignpdf_home' => $this->folder('jsignpdf_home', false),
			// Whatever a future feature decides to store
			'future-feature' => $this->folder('future-feature', false),
		]);

		$this->deleteOldBinaries()->deleteObsoleteBinaries($root);
	}

	public function testKeepsFilesThatAreNotReleasesOfTheDependency(): void {
		$root = $this->root([
			'x86_64/jsignpdf' => $this->folder('jsignpdf', false, [
				$this->folder(self::CURRENT_JSIGNPDF, false),
				$this->file('setup-cache.json', false),
			]),
		]);

		$this->deleteOldBinaries()->deleteObsoleteBinaries($root);
	}

	private function deleteOldBinaries(): DeleteOldBinaries {
		$appDataFactory = $this->createMock(IAppDataFactory::class);
		$appDataFactory->method('get')->willReturn($this->createMock(IAppData::class));

		return new class($appDataFactory) extends DeleteOldBinaries {
			public function deleteObsoleteBinaries(Folder $root): void {
				parent::deleteObsoleteBinaries($root);
			}
		};
	}

	/**
	 * Root of the AppData folder, resolving the given paths and listing the
	 * top level nodes. An unmapped path is reported as missing, the same way
	 * the real folder does.
	 *
	 * @param array<string, Node> $nodes
	 */
	private function root(array $nodes): Folder {
		$root = $this->createMock(Folder::class);
		$root->method('get')->willReturnCallback(
			static function (string $path) use ($nodes): Node {
				if (!isset($nodes[$path])) {
					throw new NotFoundException($path);
				}
				return $nodes[$path];
			}
		);
		// A regression to filtering the directory listing has to fail the tests
		// instead of silently deleting what isn't listed as allowed.
		$root->method('getDirectoryListing')->willReturn(array_values(array_filter(
			$nodes,
			static fn (string $path): bool => !str_contains($path, '/'),
			ARRAY_FILTER_USE_KEY,
		)));
		$root->expects($this->never())->method('delete');
		return $root;
	}

	/**
	 * @param list<Node> $content
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
