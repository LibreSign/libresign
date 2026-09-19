<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\SignatureRejection;

use OCA\Libresign\Enum\SignatureRejectionBehavior;
use OCA\Libresign\Enum\SignatureRejectionCommentMode;
use OCA\Libresign\Enum\SignatureRejectionVisibility;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicyConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SignatureRejectionPolicyConfigTest extends TestCase {
	public function testDefaultsKeepTheBehaviorLibreSignHadBeforeRejectionExisted(): void {
		$config = SignatureRejectionPolicyConfig::defaults();

		$this->assertFalse($config->isEnabled());
		$this->assertSame(SignatureRejectionBehavior::CANCEL, $config->getBehavior());
		$this->assertSame(SignatureRejectionCommentMode::DISABLED, $config->getCommentMode());
		$this->assertSame(SignatureRejectionVisibility::REQUESTER, $config->getVisibility());
		$this->assertSame(SignatureRejectionVisibility::REQUESTER, $config->getCommentVisibility());
		$this->assertFalse($config->cancelsWorkflow());
	}

	public function testEveryOtherSettingIsMeaninglessWhileRejectionIsDisabled(): void {
		$config = SignatureRejectionPolicyConfig::fromValues(
			enabled: false,
			behavior: 'continue',
			commentMode: 'required',
			visibility: 'public',
			commentVisibility: 'public',
		);

		$this->assertSame(
			SignatureRejectionPolicyConfig::defaults()->toKeyedValues(),
			$config->toKeyedValues(),
		);
	}

	public function testTheCommentAudienceIsDroppedWhileCommentsAreDisabled(): void {
		$config = SignatureRejectionPolicyConfig::fromValues(
			enabled: true,
			behavior: 'continue',
			commentMode: 'disabled',
			visibility: 'public',
			commentVisibility: 'public',
		);

		$this->assertSame(SignatureRejectionVisibility::PUBLIC, $config->getVisibility());
		$this->assertSame(SignatureRejectionVisibility::REQUESTER, $config->getCommentVisibility());
		$this->assertFalse($config->allowsComment());
	}

	public function testTheCommentNeverReachesFurtherThanTheRejection(): void {
		$config = SignatureRejectionPolicyConfig::fromValues(
			enabled: true,
			behavior: 'cancel',
			commentMode: 'optional',
			visibility: 'participants',
			commentVisibility: 'public',
		);

		$this->assertSame(SignatureRejectionVisibility::PARTICIPANTS, $config->getCommentVisibility());
	}

	public function testUnknownValuesFallBackToTheDefaultOfTheirSetting(): void {
		$config = SignatureRejectionPolicyConfig::fromValues(
			enabled: 'yes',
			behavior: 'postpone',
			commentMode: 'sometimes',
			visibility: 'everyone',
			commentVisibility: null,
		);

		$this->assertTrue($config->isEnabled());
		$this->assertSame(SignatureRejectionBehavior::CANCEL, $config->getBehavior());
		$this->assertSame(SignatureRejectionCommentMode::DISABLED, $config->getCommentMode());
		$this->assertSame(SignatureRejectionVisibility::REQUESTER, $config->getVisibility());
	}

	public function testItIsBuiltFromAndReadBackAsPolicyKeys(): void {
		$values = [
			SignatureRejectionPolicy::KEY_ENABLED => true,
			SignatureRejectionPolicy::KEY_BEHAVIOR => 'continue',
			SignatureRejectionPolicy::KEY_COMMENT_MODE => 'required',
			SignatureRejectionPolicy::KEY_VISIBILITY => 'public',
			SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY => 'participants',
		];

		$this->assertSame($values, SignatureRejectionPolicyConfig::fromKeyedValues($values)->toKeyedValues());
	}

	public function testAMissingKeyKeepsTheDefaultOfItsSetting(): void {
		$config = SignatureRejectionPolicyConfig::fromKeyedValues([
			SignatureRejectionPolicy::KEY_ENABLED => true,
			SignatureRejectionPolicy::KEY_COMMENT_MODE => 'optional',
		]);

		$this->assertTrue($config->isEnabled());
		$this->assertSame(SignatureRejectionCommentMode::OPTIONAL, $config->getCommentMode());
		$this->assertSame(SignatureRejectionBehavior::CANCEL, $config->getBehavior());
		$this->assertSame(SignatureRejectionVisibility::REQUESTER, $config->getVisibility());
	}

	#[DataProvider('provideAudiences')]
	public function testWhoMayLearnAboutTheRejectionAndReadItsComment(
		string $visibility,
		string $commentVisibility,
		SignatureRejectionVisibility $audience,
		bool $rejectionIsVisible,
		bool $commentIsVisible,
	): void {
		$config = SignatureRejectionPolicyConfig::fromValues(
			enabled: true,
			behavior: 'cancel',
			commentMode: 'optional',
			visibility: $visibility,
			commentVisibility: $commentVisibility,
		);

		$this->assertSame($rejectionIsVisible, $config->isRejectionVisibleTo($audience));
		$this->assertSame($commentIsVisible, $config->isCommentVisibleTo($audience));
	}

	/**
	 * @return iterable<string, array{0: string, 1: string, 2: SignatureRejectionVisibility, 3: bool, 4: bool}>
	 */
	public static function provideAudiences(): iterable {
		yield 'private rejection stays away from the participants' => [
			'requester', 'requester', SignatureRejectionVisibility::PARTICIPANTS, false, false,
		];
		yield 'participants see the rejection but not the public' => [
			'participants', 'participants', SignatureRejectionVisibility::PUBLIC, false, false,
		];
		yield 'participants see both' => [
			'participants', 'participants', SignatureRejectionVisibility::PARTICIPANTS, true, true,
		];
		yield 'public rejection with a comment kept among participants' => [
			'public', 'participants', SignatureRejectionVisibility::PUBLIC, true, false,
		];
		yield 'public rejection and public comment' => [
			'public', 'public', SignatureRejectionVisibility::PUBLIC, true, true,
		];
	}

	public function testADisabledRejectionIsVisibleToNobody(): void {
		$config = SignatureRejectionPolicyConfig::defaults();

		$this->assertFalse($config->isRejectionVisibleTo(SignatureRejectionVisibility::REQUESTER));
		$this->assertFalse($config->isCommentVisibleTo(SignatureRejectionVisibility::REQUESTER));
	}

	#[DataProvider('provideKeyedValues')]
	public function testEachKeyNormalizesItsOwnValue(string $policyKey, mixed $rawValue, bool|string $expected): void {
		$this->assertSame($expected, SignatureRejectionPolicyConfig::normalizeKeyedValue($policyKey, $rawValue));
	}

	/**
	 * @return iterable<string, array{0: string, 1: mixed, 2: bool|string}>
	 */
	public static function provideKeyedValues(): iterable {
		yield 'enabled from a string' => [SignatureRejectionPolicy::KEY_ENABLED, 'true', true];
		yield 'enabled from a number' => [SignatureRejectionPolicy::KEY_ENABLED, 0, false];
		yield 'behavior from an enum' => [SignatureRejectionPolicy::KEY_BEHAVIOR, SignatureRejectionBehavior::CONTINUE, 'continue'];
		yield 'behavior falls back' => [SignatureRejectionPolicy::KEY_BEHAVIOR, 'whatever', 'cancel'];
		yield 'comment mode is trimmed' => [SignatureRejectionPolicy::KEY_COMMENT_MODE, ' required ', 'required'];
		yield 'visibility falls back' => [SignatureRejectionPolicy::KEY_VISIBILITY, null, 'requester'];
		yield 'comment visibility is read' => [SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY, 'public', 'public'];
	}
}
