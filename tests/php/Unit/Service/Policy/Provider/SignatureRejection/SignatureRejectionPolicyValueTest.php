<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\SignatureRejection;

use OCA\Libresign\Enum\SignatureRejectionCommentMode;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicyValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SignatureRejectionPolicyValueTest extends TestCase {
	public function testDefaultsKeepTheFeatureDisabled(): void {
		$this->assertSame([
			'enabled' => false,
			'comment_mode' => SignatureRejectionCommentMode::DISABLED->value,
			'allow_private_comment' => false,
			'cancel_workflow' => false,
			'public_status' => false,
			'show_comment_on_validation' => false,
		], SignatureRejectionPolicyValue::defaults());
	}

	#[DataProvider('provideNormalizeCases')]
	public function testNormalize(mixed $input, array $expected): void {
		$this->assertSame($expected, SignatureRejectionPolicyValue::normalize($input));
	}

	/**
	 * @return iterable<string, array{0: mixed, 1: array<string, mixed>}>
	 */
	public static function provideNormalizeCases(): iterable {
		yield 'null falls back to defaults' => [null, SignatureRejectionPolicyValue::defaults()];
		yield 'scalar falls back to defaults' => ['invalid', SignatureRejectionPolicyValue::defaults()];
		yield 'empty array falls back to defaults' => [[], SignatureRejectionPolicyValue::defaults()];

		yield 'disabled collapses every sub option' => [
			[
				'enabled' => false,
				'comment_mode' => 'required',
				'allow_private_comment' => true,
				'cancel_workflow' => true,
				'public_status' => true,
				'show_comment_on_validation' => true,
			],
			SignatureRejectionPolicyValue::defaults(),
		];

		yield 'enabled without sub options' => [
			['enabled' => true],
			[
				'enabled' => true,
				'comment_mode' => 'disabled',
				'allow_private_comment' => false,
				'cancel_workflow' => false,
				'public_status' => false,
				'show_comment_on_validation' => false,
			],
		];

		yield 'unknown comment mode falls back to disabled' => [
			['enabled' => true, 'comment_mode' => 'whatever'],
			[
				'enabled' => true,
				'comment_mode' => 'disabled',
				'allow_private_comment' => false,
				'cancel_workflow' => false,
				'public_status' => false,
				'show_comment_on_validation' => false,
			],
		];

		yield 'comment options require comments to be accepted' => [
			[
				'enabled' => true,
				'comment_mode' => 'disabled',
				'allow_private_comment' => true,
				'show_comment_on_validation' => true,
			],
			[
				'enabled' => true,
				'comment_mode' => 'disabled',
				'allow_private_comment' => false,
				'cancel_workflow' => false,
				'public_status' => false,
				'show_comment_on_validation' => false,
			],
		];

		yield 'full configuration is preserved' => [
			[
				'enabled' => true,
				'comment_mode' => 'required',
				'allow_private_comment' => true,
				'cancel_workflow' => true,
				'public_status' => true,
				'show_comment_on_validation' => true,
			],
			[
				'enabled' => true,
				'comment_mode' => 'required',
				'allow_private_comment' => true,
				'cancel_workflow' => true,
				'public_status' => true,
				'show_comment_on_validation' => true,
			],
		];

		yield 'json string is decoded' => [
			'{"enabled":true,"comment_mode":"optional","cancel_workflow":true}',
			[
				'enabled' => true,
				'comment_mode' => 'optional',
				'allow_private_comment' => false,
				'cancel_workflow' => true,
				'public_status' => false,
				'show_comment_on_validation' => false,
			],
		];

		yield 'truthy scalars are accepted as booleans' => [
			[
				'enabled' => 'true',
				'comment_mode' => 'optional',
				'allow_private_comment' => 1,
				'cancel_workflow' => 'yes',
				'public_status' => 'on',
				'show_comment_on_validation' => '1',
			],
			[
				'enabled' => true,
				'comment_mode' => 'optional',
				'allow_private_comment' => true,
				'cancel_workflow' => true,
				'public_status' => true,
				'show_comment_on_validation' => true,
			],
		];

		yield 'falsy scalars are rejected as booleans' => [
			[
				'enabled' => true,
				'comment_mode' => 'optional',
				'allow_private_comment' => '0',
				'cancel_workflow' => 0,
				'public_status' => 'off',
				'show_comment_on_validation' => [],
			],
			[
				'enabled' => true,
				'comment_mode' => 'optional',
				'allow_private_comment' => false,
				'cancel_workflow' => false,
				'public_status' => false,
				'show_comment_on_validation' => false,
			],
		];
	}

	#[DataProvider('provideIsEnabledCases')]
	public function testIsEnabled(mixed $input, bool $expected): void {
		$this->assertSame($expected, SignatureRejectionPolicyValue::isEnabled($input));
	}

	/**
	 * @return iterable<string, array{0: mixed, 1: bool}>
	 */
	public static function provideIsEnabledCases(): iterable {
		yield 'defaults are disabled' => [null, false];
		yield 'explicitly disabled' => [['enabled' => false], false];
		yield 'explicitly enabled' => [['enabled' => true], true];
	}

	#[DataProvider('provideCommentModeCases')]
	public function testGetCommentMode(mixed $input, SignatureRejectionCommentMode $expected): void {
		$this->assertSame($expected, SignatureRejectionPolicyValue::getCommentMode($input));
	}

	/**
	 * @return iterable<string, array{0: mixed, 1: SignatureRejectionCommentMode}>
	 */
	public static function provideCommentModeCases(): iterable {
		yield 'defaults' => [null, SignatureRejectionCommentMode::DISABLED];
		yield 'optional' => [['enabled' => true, 'comment_mode' => 'optional'], SignatureRejectionCommentMode::OPTIONAL];
		yield 'required' => [['enabled' => true, 'comment_mode' => 'required'], SignatureRejectionCommentMode::REQUIRED];
		yield 'ignored while disabled' => [['enabled' => false, 'comment_mode' => 'required'], SignatureRejectionCommentMode::DISABLED];
	}
}
