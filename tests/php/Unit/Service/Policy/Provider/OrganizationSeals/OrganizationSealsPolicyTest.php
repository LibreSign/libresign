<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\OrganizationSeals;

use OCA\Libresign\Service\OrganizationSeal\OrganizationSealStore;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicySpec;
use OCA\Libresign\Service\Policy\Provider\OrganizationSeals\OrganizationSealsPolicy;
use OCA\Libresign\Service\Policy\Provider\OrganizationSeals\OrganizationSealsPolicyValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OrganizationSealsPolicyTest extends TestCase {
	private function getDefinition(array $knownSeals = []): \OCA\Libresign\Service\Policy\Contract\IPolicyDefinition {
		$sealStore = $this->createMock(OrganizationSealStore::class);
		$sealStore->method('exists')->willReturnCallback(static fn (string $id): bool => in_array($id, $knownSeals, true));
		return (new OrganizationSealsPolicy($sealStore))->get(OrganizationSealsPolicy::KEY);
	}

	public function testDefinitionMetadata(): void {
		$definition = $this->getDefinition();

		$this->assertSame(OrganizationSealsPolicy::KEY, $definition->key());
		$this->assertSame('[]', $definition->defaultSystemValue());
		$this->assertTrue($definition->supportsRequestOverride());
		$this->assertSame(PolicySpec::LIFECYCLE_REQUEST_SNAPSHOT, $definition->requestLifecycle());
		$this->assertFalse($definition->supportsUserPreference());
		$this->assertTrue($definition->isBackendOnly());
		$this->assertSame(OrganizationSealsPolicy::SYSTEM_APP_CONFIG_KEY, $definition->getAppConfigKey());
		$this->assertNotSame(OrganizationSealStore::INDEX_APP_CONFIG_KEY, $definition->getAppConfigKey());
	}

	#[DataProvider('providerNormalize')]
	public function testNormalizeValue(mixed $rawValue, string $expected): void {
		$this->assertSame($expected, $this->getDefinition()->normalizeValue($rawValue));
	}

	public static function providerNormalize(): array {
		return [
			'list keeps the order' => [['second', 'first'], '["second","first"]'],
			'json string' => ['["first"]', '["first"]'],
			'duplicates and blanks are dropped' => [['first', ' ', 'first', 7, ' second '], '["first","second"]'],
			'empty string means no seal' => ['', '[]'],
			'unexpected type means no seal' => [true, '[]'],
		];
	}

	public function testValidateRejectsTooManySeals(): void {
		$this->expectException(\InvalidArgumentException::class);
		$ids = array_map(static fn (int $i): string => 'seal' . $i, range(0, OrganizationSealsPolicyValue::MAX_SEALS));
		$this->getDefinition()->validateValue(OrganizationSealsPolicyValue::encode($ids), new PolicyContext());
	}

	public function testValidateDoesNotRequireTheSealToExist(): void {
		$this->getDefinition()->validateValue('["removed"]', new PolicyContext());
		$this->addToAssertionCount(1);
	}

	public function testSavingRejectsAnUnknownSeal(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->getDefinition(['first'])->validateValueForPersistence('["first","unknown"]', new PolicyContext());
	}

	public function testSavingAcceptsConfiguredSeals(): void {
		$this->getDefinition(['first', 'second'])->validateValueForPersistence('["second","first"]', new PolicyContext());
		$this->addToAssertionCount(1);
	}
}
