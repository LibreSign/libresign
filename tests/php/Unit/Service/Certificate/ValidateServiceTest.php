<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Certificate;

use InvalidArgumentException;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Certificate\RulesService;
use OCA\Libresign\Service\Certificate\ValidateService;
use OCP\IL10N;
use OCP\L10N\IFactory as IL10NFactory;
use PHPUnit\Framework\Attributes\DataProvider;

final class ValidateServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IL10N $l10n;
	private RulesService $rulesService;

	public function setUp(): void {
		$this->l10n = \OCP\Server::get(IL10NFactory::class)->get(Application::APP_ID);
		$this->rulesService = new RulesService($this->l10n);
	}

	public function getService(): ValidateService {
		return new ValidateService(
			$this->rulesService,
			$this->l10n,
		);
	}

	#[DataProvider('providerValidStringFields')]
	public function testValidateStringFieldsSuccess(string $fieldName, string $value): void {
		$service = $this->getService();

		$service->validate($fieldName, $value);
		$this->assertTrue(true);
	}

	public static function providerValidStringFields(): array {
		return [
			['CN', 'LibreSign'],
			['C', 'BR'],
			['ST', 'Rio de Janeiro'],
			['L', 'Rio de Janeiro'],
			['O', 'LibreCode'],
		];
	}

	#[DataProvider('providerInvalidStringFields')]
	public function testValidateStringFieldsFailure(string $fieldName, string $value, string $expectedError): void {
		$service = $this->getService();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($expectedError);

		$service->validate($fieldName, $value);
	}

	public static function providerInvalidStringFields(): array {
		return [
			['CN', '', "Parameter 'CN' is required!"],
			['C', 'BRA', "Parameter 'C' should be betweeen 2 and 2."],
			['C', 'B', "Parameter 'C' should be betweeen 2 and 2."],
			['ST', str_repeat('a', 129), "Parameter 'ST' should be betweeen 1 and 128."],
			['L', str_repeat('a', 129), "Parameter 'L' should be betweeen 1 and 128."],
			['O', str_repeat('a', 65), "Parameter 'O' should be betweeen 1 and 64."],
		];
	}

	#[DataProvider('providerValidArrayFields')]
	public function testValidateArrayFieldsSuccess(string $fieldName, array $value): void {
		$service = $this->getService();

		$service->validate($fieldName, $value);
		$this->assertTrue(true);
	}

	public static function providerValidArrayFields(): array {
		return [
			['OU', ['LibreCode']],
			['OU', ['LibreCode', 'LibreSign']],
			['OU', ['LibreCode', 'LibreSign', '']],
			['OU', ['']],
			['OU', []],
		];
	}

	#[DataProvider('providerInvalidArrayFields')]
	public function testValidateArrayFieldsFailure(string $fieldName, array $value, string $expectedError): void {
		$service = $this->getService();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($expectedError);

		$service->validate($fieldName, $value);
	}

	public static function providerInvalidArrayFields(): array {
		return [
			['OU', array_fill(0, 11, 'test'), "Parameter 'OU items' should be betweeen 0 and 10."],
			['OU', [str_repeat('a', 65)], "Parameter 'OU' should be betweeen 1 and 64."],
			['OU', ['valid', str_repeat('a', 65)], "Parameter 'OU' should be betweeen 1 and 64."],
		];
	}

	public function testValidateStringFieldWithArrayValueFails(): void {
		$service = $this->getService();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("Parameter 'CN' is required!");

		$service->validate('CN', ['LibreSign']);
	}

	public function testValidateArrayFieldWithStringValueFails(): void {
		$service = $this->getService();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("Parameter 'OU' is required!");

		$service->validate('OU', 'LibreSign');
	}

	#[DataProvider('providerValidateNamesSuccess')]
	public function testValidateNamesSuccess(array $names): void {
		$service = $this->getService();

		$service->validateNames($names);
		$this->assertTrue(true);
	}

	public static function providerValidateNamesSuccess(): array {
		return [
			[[
				['id' => 'CN', 'value' => 'LibreSign'],
				['id' => 'C', 'value' => 'BR'],
				['id' => 'OU', 'value' => ['LibreCode', 'LibreSign']],
			]],
			[[
				['id' => 'CN', 'value' => 'LibreSign'],
				['id' => 'OU', 'value' => []],
			]],
		];
	}

	#[DataProvider('providerValidateNamesFailure')]
	public function testValidateNamesFailure(array $names, string $expectedError): void {
		$service = $this->getService();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($expectedError);

		$service->validateNames($names);
	}

	public static function providerValidateNamesFailure(): array {
		return [
			[
				[['value' => 'LibreSign']],
				'Parameter id is required!'
			],
			[
				[['id' => 'CN']],
				"Parameter 'value' is required for field 'CN'!"
			],
			[
				[['id' => 'CN', 'value' => '']],
				"Parameter 'CN' is required!"
			],
		];
	}

	public function testValidateNamesWithMixedTypes(): void {
		$service = $this->getService();

		$names = [
			['id' => 'CN', 'value' => 'LibreSign'],
			['id' => 'C', 'value' => 'BR'],
			['id' => 'OU', 'value' => ['LibreCode', 'LibreSign', 'TestUnit']],
		];

		$service->validateNames($names);
		$this->assertTrue(true);
	}
}
