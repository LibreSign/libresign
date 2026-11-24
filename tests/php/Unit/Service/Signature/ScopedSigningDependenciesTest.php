<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signature;

use OCA\Libresign\Vendor\LibreSign\PdfSignatureValidator\Parser\PdfSignatureValidator;
use OCA\Libresign\Vendor\SignerPHP\PdfCore\AppearanceXObject;
use OCA\Libresign\Vendor\SignerPHP\PdfCore\PdfDocument;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SignatureAppearanceXObjectDto;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Presentation\Signer;
use OCA\Libresign\Vendor\Symfony\Component\Process\Process;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ScopedSigningDependenciesTest extends TestCase {
	#[DataProvider('scopedPackageProvider')]
	public function testSigningDependencyLoadsFromThirdparty(string $class, string $package): void {
		$root = dirname(__DIR__, 5);
		$directory = realpath($root . '/3rdparty/composer/' . $package);
		$this->assertNotFalse($directory);
		$source = (new \ReflectionClass($class))->getFileName();
		$this->assertNotFalse($source);
		$this->assertStringStartsWith($directory . DIRECTORY_SEPARATOR, realpath($source));
	}

	public static function scopedPackageProvider(): iterable {
		yield 'PDF core' => [PdfDocument::class, 'signerphp/pdf-core'];
		yield 'PDF signer' => [Signer::class, 'signerphp/pdf-signer'];
		yield 'PDF validator' => [PdfSignatureValidator::class, 'libresign/pdf-signature-validator'];
		yield 'Process' => [Process::class, 'symfony/process'];
	}

	public function testSignerAppearanceExtendsSharedScopedCore(): void {
		$this->assertTrue(is_subclass_of(SignatureAppearanceXObjectDto::class, AppearanceXObject::class));
	}

	public function testRootComposerHasNoProductionPackages(): void {
		$root = dirname(__DIR__, 5);
		$lock = json_decode(file_get_contents($root . '/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
		$this->assertSame([], $lock['packages']);
	}
}
