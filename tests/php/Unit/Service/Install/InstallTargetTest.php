<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Install;

use InvalidArgumentException;
use OCA\Libresign\Service\Install\InstallTarget;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InstallTargetTest extends TestCase {
	#[DataProvider('architectureProvider')]
	public function testNormalizeArchitecture(string $input, string $expected): void {
		$this->assertSame($expected, InstallTarget::normalizeArchitecture($input));
	}

	public static function architectureProvider(): array {
		return [
			'x86_64' => ['x86_64', 'x86_64'],
			'amd64' => ['amd64', 'x86_64'],
			'aarch64' => ['aarch64', 'aarch64'],
			'arm64' => ['arm64', 'aarch64'],
		];
	}

	public function testRejectsUnsupportedArchitecture(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Unsupported architecture');
		InstallTarget::normalizeArchitecture('armv7l');
	}

	public function testTargetChangesAreImmutable(): void {
		$target = InstallTarget::from('x86_64', 'linux');
		$other = $target->withArchitecture('arm64')->withDistro('alpine-linux');

		$this->assertSame('x86_64', $target->architecture());
		$this->assertSame('linux', $target->distro());
		$this->assertSame('aarch64', $other->architecture());
		$this->assertSame('alpine-linux', $other->distro());
	}
	#[DataProvider('cacheKeyProvider')]
	public function testCacheKeyIncludesRelevantTargetDimensions(
		string $resource,
		string $expected,
	): void {
		$target = InstallTarget::from('arm64', 'alpine-linux');

		$this->assertSame($expected, $target->cacheKey($resource));
	}

	public static function cacheKeyProvider(): array {
		return [
			'java includes distro' => ['java', 'java:aarch64:alpine-linux'],
			'jsignpdf ignores distro' => ['jsignpdf', 'jsignpdf:aarch64'],
			'pdftk ignores distro' => ['pdftk', 'pdftk:aarch64'],
			'cfssl ignores distro' => ['cfssl', 'cfssl:aarch64'],
		];
	}

}
