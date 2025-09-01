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
					'required' => true,
					'min' => 1,
					'max' => 64,
				],
			],
			[
				'C', [
					'min' => 2,
					'max' => 2,
				],
			],
			[
				'ST', [
					'min' => 1,
					'max' => 128,
				],
			],
			[
				'L', [
					'min' => 1,
					'max' => 128,
				],
			],
			[
				'O', [
					'min' => 1,
					'max' => 64,
				],
			],
			[
				'OU', [
					'min' => 1,
					'max' => 64,
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
}
