<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Install;

use OCA\Libresign\Service\Install\JSignPdfRelease;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JSignPdfReleaseTest extends TestCase {
	private const RELEASE_URL = 'https://github.com/intoolswetrust/jsignpdf/releases/download/JSignPdf_3_1_0/';

	public function testArchiveIsTheMinimalPackageOfTheVersion(): void {
		$this->assertSame('3.1.0', JSignPdfRelease::VERSION);
		$this->assertSame('jsignpdf-3.1.0-minimal.zip', JSignPdfRelease::archiveName());
	}

	public function testDownloadUrlPointsToTheArchiveOfTheReleaseTag(): void {
		$this->assertSame(
			self::RELEASE_URL . 'jsignpdf-3.1.0-minimal.zip',
			JSignPdfRelease::downloadUrl(),
		);
	}

	public function testChecksumUrlPointsToTheSha256SumsOfTheSameRelease(): void {
		$this->assertSame(
			self::RELEASE_URL . 'jsignpdf-3.1.0-SHA256SUMS.txt',
			JSignPdfRelease::checksumUrl(),
		);
	}

	#[DataProvider('providerInstallPath')]
	public function testInstallPathIsTheVersionedDirectoryInsideTheExtractDir(string $extractDir, string $expected): void {
		$this->assertSame($expected, JSignPdfRelease::installPath($extractDir));
	}

	public static function providerInstallPath(): array {
		return [
			'appdata folder' => [
				'/var/www/html/data/appdata_abc/libresign/x86_64/jsignpdf',
				'/var/www/html/data/appdata_abc/libresign/x86_64/jsignpdf/jsignpdf-3.1.0',
			],
			'path with spaces' => [
				'/opt/libre sign/jsignpdf',
				'/opt/libre sign/jsignpdf/jsignpdf-3.1.0',
			],
			'relative path' => [
				'jsignpdf',
				'jsignpdf/jsignpdf-3.1.0',
			],
		];
	}
}
