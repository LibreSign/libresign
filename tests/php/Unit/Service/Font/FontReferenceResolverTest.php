<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Unit\Service\Font;

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\Font\BundledFontLocator;
use OCA\Libresign\Service\Font\FontReferenceResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;

final class FontReferenceResolverTest extends \OCA\Libresign\Tests\Unit\TestCase {
	#[\Override]
	public function setUp(): void {
	}

	public function testResolveFontReferencePrefersFirstAvailableSystemFont(): void {
		$resolver = new FontReferenceResolver(new BundledFontLocator(), new NullLogger());

		$resolvedFont = $resolver->resolveFontReference([
			'LinuxOnlySystemFont',
			'DejaVu-Serif',
		]);

		$this->assertSame('LinuxOnlySystemFont', $resolvedFont);
	}

	#[DataProvider('provideBundledFallbackScenarios')]
	public function testResolveFontReferenceFallsBackToBundledFontWhenSystemFontsAreUnavailable(
		array $systemFonts,
		string $fallbackFontFile,
	): void {
		$locator = new BundledFontLocator();
		$resolver = new FontReferenceResolver($locator, new NullLogger());

		$resolvedFont = $resolver->resolveFontReference($systemFonts, $fallbackFontFile);

		$this->assertSame($locator->requireFontFile($fallbackFontFile), $resolvedFont);
	}

	public static function provideBundledFallbackScenarios(): array {
		return [
			'empty list uses default bundled fallback' => [[], 'DejaVuSerifCondensed.ttf'],
			'empty strings only use default bundled fallback' => [['', ''], 'DejaVuSerifCondensed.ttf'],
			'custom fallback file is supported' => [[], 'DejaVuSansCondensed.ttf'],
		];
	}

	public function testResolveFontReferenceThrowsWhenNeitherSystemNorBundledFallbackFontsExist(): void {
		$logger = new InMemoryLogger();

		$resolver = new FontReferenceResolver(new BundledFontLocator(), $logger);

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('No fonts available at system, and bundled fallback font not found: Missing-Bundled-Font.ttf');

		try {
			$resolver->resolveFontReference([], 'Missing-Bundled-Font.ttf');
		} finally {
			$this->assertSame([
				[
					'level' => 'error',
					'message' => 'No fonts available at system, and bundled fallback font not found: Missing-Bundled-Font.ttf',
					'context' => ['fontFile' => 'Missing-Bundled-Font.ttf'],
				],
			], $logger->all());
		}
	}

	#[DataProvider('provideResolveAliasScenarios')]
	public function testResolveAliasKeepsBackwardCompatibility(array $systemFonts, string $fallbackFontFile): void {
		$resolver = new FontReferenceResolver(new BundledFontLocator(), new NullLogger());

		$this->assertSame(
			$resolver->resolveFontReference($systemFonts, $fallbackFontFile),
			$resolver->resolve($systemFonts, $fallbackFontFile)
		);
	}

	public static function provideResolveAliasScenarios(): array {
		return [
			'system font available' => [['LinuxOnlySystemFont'], 'DejaVuSerifCondensed.ttf'],
			'bundled fallback' => [[], 'DejaVuSansCondensed.ttf'],
		];
	}
}
