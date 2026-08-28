<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\MailSenderStrategy;

use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Provider\MailSenderStrategy\MailSenderStrategyPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MailSenderStrategyPolicyTest extends TestCase {
	public function testProviderBuildsDefinition(): void {
		$provider = new MailSenderStrategyPolicy();
		$this->assertSame([MailSenderStrategyPolicy::KEY], $provider->keys());

		$definition = $provider->get(MailSenderStrategyPolicy::KEY);
		$this->assertSame('mail_sender_strategy', $definition->key());
		$this->assertSame('system', $definition->defaultSystemValue());
		$this->assertSame(['system', 'requester'], $definition->allowedValues(new PolicyContext()));
		$this->assertSame('mail_sender_strategy', $definition->getAppConfigKey());
	}

	public function testProviderIsRestrictedToSystemScope(): void {
		$provider = new MailSenderStrategyPolicy();
		$definition = $provider->get(MailSenderStrategyPolicy::KEY);

		$this->assertSame(['system'], $definition->supportedScopes());
		$this->assertTrue($definition->supportsScope('system'));
		$this->assertFalse($definition->supportsScope('group'));
		$this->assertFalse($definition->supportsScope('user'));
		$this->assertFalse($definition->supportsUserPreference());
		$this->assertFalse($definition->supportsGroupAdminDelegation());
	}

	#[DataProvider('provideRawValues')]
	public function testNormalizeValueTrimsAndLowercases(mixed $rawValue, string $expected): void {
		$provider = new MailSenderStrategyPolicy();
		$definition = $provider->get(MailSenderStrategyPolicy::KEY);

		$this->assertSame($expected, $definition->normalizeValue($rawValue));
	}

	/** @return array<string, array{0: mixed, 1: string}> */
	public static function provideRawValues(): array {
		return [
			'plain system' => ['system', 'system'],
			'plain requester' => ['requester', 'requester'],
			'uppercase with spaces' => ['  REQUESTER ', 'requester'],
			'unknown value is kept for validation' => ['Someone-Else', 'someone-else'],
			'empty value' => ['', ''],
		];
	}

	#[DataProvider('provideValidStrategies')]
	public function testValidateValueAcceptsKnownStrategies(string $strategy): void {
		$provider = new MailSenderStrategyPolicy();
		$definition = $provider->get(MailSenderStrategyPolicy::KEY);

		$definition->validateValue($definition->normalizeValue($strategy), new PolicyContext());
		$this->addToAssertionCount(1);
	}

	/** @return array<string, array{0: string}> */
	public static function provideValidStrategies(): array {
		return [
			'system' => ['system'],
			'requester' => ['requester'],
			'requester with different case' => ['Requester'],
		];
	}

	#[DataProvider('provideInvalidValues')]
	public function testValidateValueRejectsUnknownStrategies(mixed $value): void {
		$provider = new MailSenderStrategyPolicy();
		$definition = $provider->get(MailSenderStrategyPolicy::KEY);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid value for mail_sender_strategy');

		$definition->validateValue($value, new PolicyContext());
	}

	/** @return array<string, array{0: mixed}> */
	public static function provideInvalidValues(): array {
		return [
			'unknown strategy' => ['user_choice'],
			'empty string' => [''],
			'boolean' => [true],
			'null' => [null],
		];
	}

	public function testThrowsOnUnknownPolicyKey(): void {
		$provider = new MailSenderStrategyPolicy();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Unknown policy key: unknown_policy_key');

		$provider->get('unknown_policy_key');
	}
}
