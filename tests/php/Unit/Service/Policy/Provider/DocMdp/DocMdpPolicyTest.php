<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\DocMdp;

use OCA\Libresign\Enum\DocMdpLevel;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Provider\DocMdp\DocMdpPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DocMdpPolicyTest extends TestCase {
	public function testProviderBuildsDocMdpDefinition(): void {
		$provider = new DocMdpPolicy();
		$this->assertSame([DocMdpPolicy::KEY], $provider->keys());
		$definition = $provider->get(DocMdpPolicy::KEY);

		$this->assertSame(DocMdpPolicy::KEY, $definition->key());
		$this->assertSame(DocMdpLevel::NOT_CERTIFIED->value, $definition->defaultSystemValue());
		$this->assertSame([0, 1, 2, 3], $definition->allowedValues(new PolicyContext()));
	}

	#[DataProvider('normalizationCases')]
	public function testProviderNormalizesDocMdpLevelValues(mixed $input, mixed $expected): void {
		$provider = new DocMdpPolicy();
		$definition = $provider->get(DocMdpPolicy::KEY);

		$this->assertSame($expected, $definition->normalizeValue($input));
	}

	#[DataProvider('validationCases')]
	public function testProviderValidatesNormalizedDocMdpValues(mixed $input, bool $isValid): void {
		$provider = new DocMdpPolicy();
		$definition = $provider->get(DocMdpPolicy::KEY);
		$normalized = $definition->normalizeValue($input);

		if (!$isValid) {
			$this->expectException(\InvalidArgumentException::class);
		}

		$definition->validateValue($normalized, new PolicyContext());
		$this->addToAssertionCount(1);
	}

	/**
	 * @return array<string, array{0: mixed, 1: mixed}>
	 */
	public static function normalizationCases(): array {
		return [
			'integer level remains integer' => [2, 2],
			'enum level is converted to backing integer' => [
				DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS,
				DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS->value,
			],
			'numeric string is converted to integer' => ['2', 2],
			'numeric string with decimal suffix is converted to integer' => ['2.0', 2],
			'non-numeric string remains unchanged' => ['invalid', 'invalid'],
		];
	}

	/**
	 * @return array<string, array{0: mixed, 1: bool}>
	 */
	public static function validationCases(): array {
		return [
			'valid numeric string passes after normalization' => ['2', true],
			'valid enum value passes after normalization' => [DocMdpLevel::CERTIFIED_FORM_FILLING, true],
			'out of range numeric value is rejected' => ['7', false],
			'non-numeric string is rejected' => ['invalid', false],
		];
	}
}
