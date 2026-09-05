<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Install;

/**
 * Where LibreSign downloads JSignPdf from and where the archive ends up once
 * extracted.
 *
 * JSignPdf 3.1 dropped the single JSignPdf.jar. The "minimal" package, meant
 * for headless and command line use, ships a lib/ directory that the PHP
 * wrapper starts through the classpath, so the configured path is the
 * extracted directory instead of a jar file.
 */
final class JSignPdfRelease {
	public const VERSION = '3.1.0';
	private const RELEASES_URL = 'https://github.com/intoolswetrust/jsignpdf/releases/download/';

	public static function archiveName(): string {
		return 'jsignpdf-' . self::VERSION . '-minimal.zip';
	}

	public static function downloadUrl(): string {
		return self::releaseUrl() . self::archiveName();
	}

	/**
	 * One line per asset of the release, in the "hash  file name" format.
	 */
	public static function checksumUrl(): string {
		return self::releaseUrl() . 'jsignpdf-' . self::VERSION . '-SHA256SUMS.txt';
	}

	/**
	 * The archive extracts to a directory named after the version.
	 */
	public static function installPath(string $extractDir): string {
		return $extractDir . '/jsignpdf-' . self::VERSION;
	}

	private static function releaseUrl(): string {
		return self::RELEASES_URL . 'JSignPdf_' . str_replace('.', '_', self::VERSION) . '/';
	}
}
