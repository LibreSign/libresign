<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing;

use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Service\Signing\RequestedSigningMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestedSigningMethodTest extends TestCase {
	/** @return array<string, array{string, bool, bool}> */
	public static function allowedPaths(): array {
		return [
			'LibreSign only' => ['libresign', true, false],
			'external certificate required' => ['external_certificate', false, true],
			'exactly one method selected by signer' => ['signer_choice', true, true],
		];
	}

	#[DataProvider('allowedPaths')]
	public function testOneRequestedSigningModeControlsBothPaths(string $input, bool $local, bool $external): void {
		$mode = RequestedSigningMethod::fromInput($input);
		self::assertSame($local, $mode->allowsLibreSign());
		self::assertSame($external, $mode->allowsExternalCertificate());
	}

	public function testLegacyRequestDefaultsToLibreSignOnly(): void {
		self::assertSame(RequestedSigningMethod::LIBRESIGN, RequestedSigningMethod::forRequest(new SignRequest()));
	}

	public function testMetadataWinsOverLegacyFallback(): void {
		$request = new SignRequest();
		$request->setMetadata([RequestedSigningMethod::METADATA_KEY => 'external_certificate']);
		self::assertSame(RequestedSigningMethod::EXTERNAL_CERTIFICATE, RequestedSigningMethod::forRequest($request));
	}

	public function testUnexpectedMethodFailsClosed(): void {
		$this->expectException(\InvalidArgumentException::class);
		RequestedSigningMethod::fromInput(['libresign', 'external_certificate']);
	}
}
