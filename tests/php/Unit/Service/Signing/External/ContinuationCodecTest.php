<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing\External;

use OCA\Libresign\Service\Signing\External\ContinuationCodec;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContinuationCodecTest extends TestCase {
	public function testRoundTripPreservesPrivateAttributesWithoutInfrastructure(): void {
		self::assertSame(['sad' => 'opaque', 'operationId' => '123'],
			ContinuationCodec::decode(ContinuationCodec::encode(['sad' => 'opaque', 'operationId' => '123'])));
		self::assertSame([], ContinuationCodec::decode(ContinuationCodec::encode([])));
	}

	/** @return array<string, array{array<mixed>}> */
	public static function invalidAttributes(): array {
		return [
			'empty value' => [['sad' => '']],
			'invalid key' => [['bad-key' => 'data']],
			'oversized value' => [['sad' => str_repeat('a', 16385)]],
		];
	}

	#[DataProvider('invalidAttributes')]
	public function testRejectsInvalidAttributes(array $attributes): void {
		$this->expectException(\InvalidArgumentException::class);
		ContinuationCodec::encode($attributes);
	}

	public function testRejectsMalformedAndOversizedPersistedState(): void {
		$this->expectException(\UnexpectedValueException::class);
		ContinuationCodec::decode('["unexpected-list"]');
	}
}
