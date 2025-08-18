<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Certificate\RulesService;
use OCA\Libresign\Service\Certificate\ValidateService;
use OCP\IL10N;
use OCP\L10N\IFactory as IL10NFactory;
use PHPUnit\Framework\Attributes\DataProvider;

final class ValidateServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {

	private IL10N $l10n;

	public function setUp(): void {
		$this->l10n = \OCP\Server::get(IL10NFactory::class)->get(Application::APP_ID);
	}

	private function getService(): ValidateService {
		$rulesService = new RulesService($this->l10n);
		return new ValidateService(
			$rulesService,
			$this->l10n
		);
	}

	#[DataProvider('providerValidInputs')]
	public function testValidateWithValidInput(string $fieldName, string $value): void {
		$service = $this->getService();
		$service->validate($fieldName, $value);
		$this->assertTrue(true); // se não lançar exceção, passou
	}

	public static function providerValidInputs(): array {
		return [
			['CN', 'John Doe'],       // requerido, dentro do limite
			['C', 'BR'],              // exatamente 2 letras
			['ST', 'Amazonas'],       // válido
			['L', 'Manaus'],          // válido
			['O', 'LibreCode'],       // válido
			['OU', 'Development'],    // válido
		];
	}

	#[DataProvider('providerInvalidInputs')]
	public function testValidateWithInvalidInput(string $fieldName, string $value, string $expectedMessage): void {
		$service = $this->getService();
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($expectedMessage);
		$service->validate($fieldName, $value);
	}

	public static function providerInvalidInputs(): array {
		return [
			// CN é obrigatório → vazio deve falhar
			['CN', '', "Parameter 'CN' is required!"],
			// CN muito longo
			['CN', str_repeat('a', 65), "Parameter 'CN' should be betweeen 1 and 64."],
			// C muito curto
			['C', 'B', "Parameter 'C' should be betweeen 2 and 2."],
			// C muito longo
			['C', 'BRA', "Parameter 'C' should be betweeen 2 and 2."],
			// ST acima do limite
			['ST', str_repeat('x', 129), "Parameter 'ST' should be betweeen 1 and 128."],
		];
	}

	public function testValidateNamesWithValidArray(): void {
		$service = $this->getService();

		$names = [
			['id' => 'CN', 'value' => 'Maria da Silva'],
			['id' => 'C', 'value' => 'BR'],
		];

		$service->validateNames($names);

		$this->assertTrue(true);
	}

	public function testValidateNamesWithoutIdShouldFail(): void {
		$service = $this->getService();

		$names = [
			['id' => '', 'value' => 'Invalid Name'],
		];

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Parameter id is required!');

		$service->validateNames($names);
	}

	public function testValidateNamesWithInvalidValueShouldFail(): void {
		$service = $this->getService();

		$names = [
			['id' => 'C', 'value' => 'BRA'], // inválido, deve ter 2 chars
		];

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("Parameter 'C' should be betweeen 2 and 2.");

		$service->validateNames($names);
	}
}
