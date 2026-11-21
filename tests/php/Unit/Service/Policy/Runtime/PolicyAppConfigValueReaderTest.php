<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Runtime;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Policy\Runtime\PolicyAppConfigValueReader;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\Exceptions\AppConfigUnknownKeyException;
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\DataProvider;

final class PolicyAppConfigValueReaderTest extends TestCase {
	#[DataProvider('provideStoredTypes')]
	public function testReadsPersistedTypeWithoutProbingAnotherGetter(
		int $storedType,
		string $getter,
		mixed $default,
		mixed $getterDefault,
		mixed $storedValue,
	): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->expects(self::once())
			->method('getValueType')
			->with(Application::APP_ID, 'policy.example', false)
			->willReturn($storedType);
		$appConfig->expects(self::once())
			->method($getter)
			->with(Application::APP_ID, 'policy.example', $getterDefault, false)
			->willReturn($storedValue);

		self::assertSame($storedValue, (new PolicyAppConfigValueReader($appConfig))
			->readUsingStoredType('policy.example', $default));
	}

	public static function provideStoredTypes(): array {
		return [
			'integer with mismatched array default' => [IAppConfig::VALUE_INT, 'getValueInt', [], 0, 12],
			'float with mismatched string default' => [IAppConfig::VALUE_FLOAT, 'getValueFloat', 'old', 0.0, 1.5],
			'boolean with mismatched array default' => [IAppConfig::VALUE_BOOL, 'getValueBool', [], false, true],
			'array with mismatched string default' => [IAppConfig::VALUE_ARRAY, 'getValueArray', 'old', [], ['admin']],
			'string with mismatched array default' => [IAppConfig::VALUE_STRING, 'getValueString', [], '', '1'],
			'sensitive string' => [IAppConfig::VALUE_STRING | IAppConfig::VALUE_SENSITIVE, 'getValueString', '', '', 'secret'],
			'current integer type preserves default' => [IAppConfig::VALUE_INT, 'getValueInt', 7, 7, 12],
			'current boolean type preserves default' => [IAppConfig::VALUE_BOOL, 'getValueBool', true, true, false],
		];
	}

	public function testMissingKeyReturnsUnchangedDefault(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->expects(self::once())
			->method('getValueType')
			->with(Application::APP_ID, 'missing', false)
			->willThrowException(new AppConfigUnknownKeyException('missing'));
		$appConfig->expects(self::never())->method('getValueString');

		$default = ['enabled' => true, 'approvers' => ['admin']];
		self::assertSame($default, (new PolicyAppConfigValueReader($appConfig))
			->readUsingStoredType('missing', $default));
	}
}
