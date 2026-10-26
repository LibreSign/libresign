<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Command\SignatureElements;

use OCA\Libresign\Command\SignatureElements\Cleanup;
use OCA\Libresign\Service\FolderService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\File;
use OCP\Files\Folder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CleanupTest extends TestCase {
	private function command(array $folders): CommandTester {
		$root = $this->createMock(Folder::class);
		$root->method('getDirectoryListing')->willReturn($folders);
		$folderService = $this->createMock(FolderService::class);
		$folderService->method('getUnauthenticatedFolder')->willReturn($root);
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getDateTime')->willReturn(new \DateTime('2026-06-01T00:00:00Z'));
		return new CommandTester(new Cleanup($folderService, $time));
	}

	#[DataProvider('provideDryRun')]
	public function testPurgesOldSavedAndSessionImagesWithoutTouchingDocumentsOrRecentImages(bool $dryRun): void {
		$old = strtotime('2026-01-01T00:00:00Z');
		$recent = strtotime('2026-05-31T00:00:00Z');
		$makeFolder = function (string $name, string $fileName, int $mtime, bool $delete) use ($old, $dryRun): Folder {
			$file = $this->createMock(File::class);
			$file->method('getName')->willReturn($fileName);
			$file->method('getMTime')->willReturn($mtime);
			$folder = $this->createMock(Folder::class);
			$folder->method('getName')->willReturn($name);
			$folder->method('getMTime')->willReturn($old);
			$folder->method('getDirectoryListing')->willReturn([$file]);
			$folder->expects($delete && !$dryRun ? $this->once() : $this->never())->method('delete');
			return $folder;
		};
		$storage = $this->createMock(Folder::class);
		$storage->method('getName')->willReturn('email_signer_elements');
		$storage->expects($this->never())->method('delete');
		$storage->method('getDirectoryListing')->willReturn([
			$makeFolder(str_repeat('a', 64), 'signature.png', $old, true),
			$makeFolder(str_repeat('b', 64), 'initial.png', $recent, false),
		]);
		$command = $this->command([
			$storage,
			$makeFolder(str_repeat('c', 64), 'signature_1700000000.png', $old, true),
			$makeFolder('11111111-1111-1111-1111-111111111111', 'initial_1700000000.png', $old, true),
			$makeFolder(str_repeat('d', 64), 'signature_1700000000.png', $recent, false),
			$makeFolder(str_repeat('e', 64), 'document.pdf', $old, false),
			$makeFolder('unrelated_folder', 'signature_1700000000.png', $old, false),
		]);

		$this->assertSame(Cleanup::SUCCESS, $command->execute(['--days' => '30', '--dry-run' => $dryRun]));
		$this->assertStringContainsString(($dryRun ? 'Would delete ' : 'Deleted ') . '3 signature folders.', $command->getDisplay());
	}

	public static function provideDryRun(): array {
		return ['preview' => [true], 'delete' => [false]];
	}

	#[DataProvider('provideInvalidDays')]
	public function testRequiresAnExplicitPositiveRetentionPeriod(?string $days): void {
		$command = $this->command([]);

		$this->assertSame(Cleanup::INVALID, $command->execute($days === null ? [] : ['--days' => $days]));
	}

	public static function provideInvalidDays(): array {
		return [[null], ['0'], ['-1'], ['1.5'], ['invalid']];
	}
}
