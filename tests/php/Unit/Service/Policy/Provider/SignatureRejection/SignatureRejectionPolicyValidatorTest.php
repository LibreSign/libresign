<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\SignatureRejection;

use OCA\Libresign\Enum\SignatureRejectionVisibility;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicyValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SignatureRejectionPolicyValidatorTest extends TestCase {
	private function getValidator(): SignatureRejectionPolicyValidator {
		return new SignatureRejectionPolicyValidator();
	}

	/** @return array<string, mixed> */
	private static function configuration(array $values = []): array {
		return array_merge([
			SignatureRejectionPolicy::KEY_ENABLED => true,
			SignatureRejectionPolicy::KEY_BEHAVIOR => 'cancel',
			SignatureRejectionPolicy::KEY_COMMENT_MODE => 'optional',
			SignatureRejectionPolicy::KEY_VISIBILITY => 'participants',
			SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY => 'participants',
		], $values);
	}

	public function testAValidCombinationIsReturnedAsAConfiguration(): void {
		$config = $this->getValidator()->validateRequest(
			self::configuration([SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY => 'requester']),
			[SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY],
		);

		$this->assertTrue($config->isEnabled());
		$this->assertSame(SignatureRejectionVisibility::PARTICIPANTS, $config->getVisibility());
		$this->assertSame(SignatureRejectionVisibility::REQUESTER, $config->getCommentVisibility());
	}

	#[DataProvider('provideWiderCommentAudiences')]
	public function testTheCommentAudienceCannotBeWiderThanTheRejectionAudience(string $submittedKey): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('The rejection comment cannot be visible to a wider audience than the rejection itself.');

		$this->getValidator()->validateRequest(
			self::configuration([
				SignatureRejectionPolicy::KEY_VISIBILITY => 'participants',
				SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY => 'public',
			]),
			[$submittedKey],
		);
	}

	/**
	 * @return iterable<string, array{0: string}>
	 */
	public static function provideWiderCommentAudiences(): iterable {
		// Whichever of the two settings is being written, the combination is the
		// same, so the outcome cannot depend on the order in which they are saved.
		yield 'the comment audience is being widened' => [SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY];
		yield 'the rejection audience is being narrowed' => [SignatureRejectionPolicy::KEY_VISIBILITY];
	}

	public function testAnInheritedWiderCommentAudienceIsNarrowedInsteadOfRefused(): void {
		// Nobody is writing these values: an administrator narrowing the rejection
		// audience must not break every request that already inherited a wider one.
		$config = $this->getValidator()->validateRequest(
			self::configuration([
				SignatureRejectionPolicy::KEY_VISIBILITY => 'requester',
				SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY => 'public',
			]),
			[],
		);

		$this->assertSame(SignatureRejectionVisibility::REQUESTER, $config->getCommentVisibility());
	}

	public function testADependentSettingCannotBeConfiguredWhileRejectionIsDisabled(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Signature rejection is disabled, so the other rejection settings cannot be configured.');

		$this->getValidator()->validateRequest(
			self::configuration([
				SignatureRejectionPolicy::KEY_ENABLED => false,
				SignatureRejectionPolicy::KEY_COMMENT_MODE => 'required',
			]),
			[SignatureRejectionPolicy::KEY_COMMENT_MODE],
		);
	}

	public function testWritingTheDefaultOfADependentSettingWhileDisabledIsAccepted(): void {
		$config = $this->getValidator()->validateRequest(
			self::configuration([
				SignatureRejectionPolicy::KEY_ENABLED => false,
				SignatureRejectionPolicy::KEY_COMMENT_MODE => 'disabled',
			]),
			[SignatureRejectionPolicy::KEY_COMMENT_MODE],
		);

		$this->assertFalse($config->isEnabled());
	}

	public function testADisabledRejectionDoesNotRefuseWhatWasMerelyInherited(): void {
		$config = $this->getValidator()->validateRequest(
			self::configuration([SignatureRejectionPolicy::KEY_ENABLED => false]),
			[SignatureRejectionPolicy::KEY_ENABLED],
		);

		$this->assertFalse($config->isEnabled());
	}

	public function testTheCommentAudienceCannotBeConfiguredWhileCommentsAreDisabled(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Rejection comments are disabled, so the audience of the comment cannot be configured.');

		$this->getValidator()->validateRequest(
			self::configuration([
				SignatureRejectionPolicy::KEY_COMMENT_MODE => 'disabled',
				SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY => 'participants',
			]),
			[SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY],
		);
	}

	public function testDisablingTheCommentsThemselvesIsNotAConflict(): void {
		$config = $this->getValidator()->validateRequest(
			self::configuration([
				SignatureRejectionPolicy::KEY_COMMENT_MODE => 'disabled',
				SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY => 'participants',
			]),
			[SignatureRejectionPolicy::KEY_COMMENT_MODE],
		);

		$this->assertFalse($config->allowsComment());
		$this->assertSame(SignatureRejectionVisibility::REQUESTER, $config->getCommentVisibility());
	}

	public function testALayerIsOnlyCheckedAgainstWhatItWouldDisclose(): void {
		// An administrator configures one key at a time and in whichever order, so
		// a comment mode saved while rejection is still disabled is a standing rule,
		// not a conflict.
		$this->expectNotToPerformAssertions();

		$this->getValidator()->validateLayer(
			self::configuration([
				SignatureRejectionPolicy::KEY_ENABLED => false,
				SignatureRejectionPolicy::KEY_COMMENT_MODE => 'required',
			]),
			SignatureRejectionPolicy::KEY_COMMENT_MODE,
		);
	}

	public function testALayerStillCannotWidenWhatTheCommentDiscloses(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('The rejection comment cannot be visible to a wider audience than the rejection itself.');

		$this->getValidator()->validateLayer(
			self::configuration([
				SignatureRejectionPolicy::KEY_VISIBILITY => 'participants',
				SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY => 'public',
			]),
			SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY,
		);
	}

	public function testMissingValuesFallBackToTheDefaults(): void {
		$config = $this->getValidator()->validateRequest([], []);

		$this->assertSame(
			SignatureRejectionPolicy::ALL_KEYS,
			array_keys($config->toKeyedValues()),
		);
		$this->assertFalse($config->isEnabled());
	}
}
