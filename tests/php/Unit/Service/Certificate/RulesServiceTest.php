<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Certificate\RulesService;
use OCP\IL10N;
use OCP\L10N\IFactory as IL10NFactory;
use PHPUnit\Framework\Attributes\DataProvider;

final class RulesServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IL10N $l10n;

	public function setUp(): void {
		$this->l10n = \OCP\Server::get(IL10NFactory::class)->get(Application::APP_ID);
	}

	public function getService(): RulesService {
		return new RulesService(
			$this->l10n,
		);
	}

	#[DataProvider('providerGetHelperTextWithValidFieldName')]
	public function testGetHelperTextWithValidFieldName(string $fieldName, string $expected): void {
		$service = $this->getService();
		$helperText = $service->getHelperText($fieldName);
		$this->assertNotEmpty($helperText);
		$this->assertStringContainsString($expected, $helperText);
	}

	public static function providerGetHelperTextWithValidFieldName(): array {
		return [
			['CN', 'Common Name'],
			['C', 'country code'],
			['ST', 'states'],
			['L', 'locality'],
			['O', 'organization'],
			['OU', 'organizational unit'],
		];
	}

	#[DataProvider('providerGetHelperTextWithInvalidFieldName')]
	public function testGetHelperTextWithInvalidFieldName(string $fieldName): void {
		$service = $this->getService();
		$helperText = $service->getHelperText($fieldName);
		$this->assertNull($helperText);
	}

	public static function providerGetHelperTextWithInvalidFieldName(): array {
		return [
			['INVALID'],
			[''],
			['123'],
			['!@#'],
			['CN1'],
			['C2'],
			['ST3'],
			['L4'],
			['O5'],
			['OU6'],
		];
	}

	#[DataProvider('providerGetRuleToValidField')]
	public function testGetRuleToValidField(string $fieldName, array $expected): void {
		$service = $this->getService();

		$actual = $service->getRule($fieldName);

		$this->assertArrayHasKey('helperText', $actual);
		unset($actual['helperText']);

		$this->assertCount(count($expected), $actual, "Expected count does not match actual count for field: $fieldName");

		$this->assertSame($expected, $actual, "Mismatch for field: $fieldName");
	}

	public static function providerGetRuleToValidField(): array {
		return [
			[
				'CN', [
					'type' => 'string',
					'required' => true,
					'min' => 1,
					'max' => 64,
				],
			],
			[
				'C', [
					'type' => 'string',
					'min' => 2,
					'max' => 2,
				],
			],
			[
				'ST', [
					'type' => 'string',
					'min' => 1,
					'max' => 128,
				],
			],
			[
				'L', [
					'type' => 'string',
					'min' => 1,
					'max' => 128,
				],
			],
			[
				'O', [
					'type' => 'string',
					'min' => 1,
					'max' => 64,
				],
			],
			[
				'OU', [
					'type' => 'array',
					'required' => false,
					'min' => 1,
					'max' => 64,
					'minItems' => 0,
					'maxItems' => 10,
				],
			],
		];
	}

	#[DataProvider('providerGetRuleToInvalidField')]
	public function testGetRuleToInvalidField(string $fieldName): void {
		$service = $this->getService();
		$actual = $service->getRule($fieldName);
		$this->assertEmpty($actual);
	}

	public static function providerGetRuleToInvalidField(): array {
		return [
			['INVALID'],
			[''],
			['123'],
			['!@#'],
			['CN1'],
			['C2'],
			['ST3'],
			['L4'],
			['O5'],
			['OU6'],
		];
	}

	#[DataProvider('providerGetRuleTypeValidation')]
	public function testGetRuleTypeValidation(string $fieldName, string $expectedType): void {
		$service = $this->getService();
		$rule = $service->getRule($fieldName);

		$this->assertArrayHasKey('type', $rule, "Field $fieldName should have a type defined");
		$this->assertSame($expectedType, $rule['type'], "Field $fieldName should be of type $expectedType");
	}

	public static function providerGetRuleTypeValidation(): array {
		return [
			['CN', 'string'],
			['C', 'string'],
			['ST', 'string'],
			['L', 'string'],
			['O', 'string'],
			['OU', 'array'],
		];
	}

	#[DataProvider('providerGetRuleArraySpecificValidation')]
	public function testGetRuleArraySpecificValidation(string $fieldName, array $expectedProperties): void {
		$service = $this->getService();
		$rule = $service->getRule($fieldName);

		foreach ($expectedProperties as $property => $expectedValue) {
			$this->assertArrayHasKey($property, $rule, "Field $fieldName should have property $property");
			$this->assertSame($expectedValue, $rule[$property], "Field $fieldName property $property should be $expectedValue");
		}
	}

	public static function providerGetRuleArraySpecificValidation(): array {
		return [
			[
				'OU', [
					'type' => 'array',
					'minItems' => 0,
					'maxItems' => 10,
					'required' => false,
				]
			],
		];
	}

	public function testStringFieldsDoNotHaveArrayProperties(): void {
		$service = $this->getService();
		$stringFields = ['CN', 'C', 'ST', 'L', 'O'];

		foreach ($stringFields as $fieldName) {
			$rule = $service->getRule($fieldName);
			$this->assertArrayNotHasKey('minItems', $rule, "String field $fieldName should not have minItems property");
			$this->assertArrayNotHasKey('maxItems', $rule, "String field $fieldName should not have maxItems property");
			$this->assertSame('string', $rule['type'], "Field $fieldName should be of type string");
		}
	}

	public function testArrayFieldsHaveArrayProperties(): void {
		$service = $this->getService();
		$rule = $service->getRule('OU');

		$this->assertSame('array', $rule['type'], 'OU field should be of type array');
		$this->assertArrayHasKey('minItems', $rule, 'Array field OU should have minItems property');
		$this->assertArrayHasKey('maxItems', $rule, 'Array field OU should have maxItems property');
		$this->assertIsInt($rule['minItems'], 'minItems should be an integer');
		$this->assertIsInt($rule['maxItems'], 'maxItems should be an integer');
		$this->assertGreaterThanOrEqual(0, $rule['minItems'], 'minItems should be >= 0');
		$this->assertGreaterThan($rule['minItems'], $rule['maxItems'], 'maxItems should be > minItems');
	}
}
