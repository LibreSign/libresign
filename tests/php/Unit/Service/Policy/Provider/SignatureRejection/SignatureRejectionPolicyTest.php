<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\SignatureRejection;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Model\ActorRole;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Model\PolicySpec;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicyValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SignatureRejectionPolicyTest extends TestCase {
	private function getProvider(): SignatureRejectionPolicy {
		return new SignatureRejectionPolicy(
			new SignatureRejectionPolicyValidator(),
		);
	}

	private function getDefinition(string $policyKey): IPolicyDefinition {
		return $this->getProvider()->get($policyKey);
	}

	public function testEverySettingIsItsOwnScalarPolicyKey(): void {
		$provider = $this->getProvider();

		$this->assertSame([
			SignatureRejectionPolicy::KEY_ENABLED,
			SignatureRejectionPolicy::KEY_BEHAVIOR,
			SignatureRejectionPolicy::KEY_COMMENT_MODE,
			SignatureRejectionPolicy::KEY_VISIBILITY,
			SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY,
		], $provider->keys());

		foreach ($provider->keys() as $policyKey) {
			$definition = $provider->get($policyKey);
			$this->assertSame($policyKey, $definition->key());
			$this->assertSame($policyKey, $definition->getAppConfigKey());
			$this->assertSame(PolicySpec::RESOLUTION_MODE_VALUE_CHOICE, $definition->resolutionMode());
		}
	}

	#[DataProvider('provideAllowedValues')]
	public function testTheAdministratorChoosesAmongTheValuesOfEachSetting(string $policyKey, array $allowedValues, mixed $defaultValue): void {
		$definition = $this->getDefinition($policyKey);

		$this->assertSame($allowedValues, $definition->allowedValues(new PolicyContext()));
		$this->assertSame($defaultValue, $definition->defaultSystemValue());
	}

	/**
	 * @return iterable<string, array{0: string, 1: list<mixed>, 2: mixed}>
	 */
	public static function provideAllowedValues(): iterable {
		yield 'enabled' => [SignatureRejectionPolicy::KEY_ENABLED, [false, true], false];
		yield 'behavior' => [SignatureRejectionPolicy::KEY_BEHAVIOR, ['cancel', 'continue'], 'cancel'];
		yield 'comment mode' => [SignatureRejectionPolicy::KEY_COMMENT_MODE, ['disabled', 'optional', 'required'], 'disabled'];
		yield 'visibility' => [SignatureRejectionPolicy::KEY_VISIBILITY, ['requester', 'participants', 'public'], 'requester'];
		yield 'comment visibility' => [SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY, ['requester', 'participants', 'public'], 'requester'];
	}

	public function testTheSettingsAreGroupedUnderTheOneTheyDependOn(): void {
		$enabled = $this->getDefinition(SignatureRejectionPolicy::KEY_ENABLED);

		$this->assertNull($enabled->parentPolicyKey());
		$this->assertSame(SignatureRejectionPolicy::DEPENDENT_KEYS, $enabled->compositeChildren());
		$this->assertFalse($enabled->isHelper());

		foreach (SignatureRejectionPolicy::DEPENDENT_KEYS as $policyKey) {
			$definition = $this->getDefinition($policyKey);
			$this->assertSame(SignatureRejectionPolicy::KEY_ENABLED, $definition->parentPolicyKey());
			$this->assertSame([], $definition->compositeChildren());
			$this->assertTrue($definition->isHelper());
		}
	}

	public function testTheRequesterCanDecidePerSignatureRequest(): void {
		foreach (SignatureRejectionPolicy::ALL_KEYS as $policyKey) {
			$definition = $this->getDefinition($policyKey);

			// The user scope is what makes a per-request override possible at all.
			$this->assertTrue($definition->supportsUserPreference());
			$this->assertTrue($definition->supportsScope(PolicySpec::SCOPE_USER));
			$this->assertTrue($definition->supportsScope(PolicySpec::SCOPE_SYSTEM));
			$this->assertTrue($definition->supportsScope(PolicySpec::SCOPE_GROUP));
			$this->assertTrue($definition->supportsGroupAdminDelegation());
		}
	}

	#[DataProvider('provideNormalizedValues')]
	public function testEachSettingNormalizesItsOwnValue(string $policyKey, mixed $rawValue, mixed $expected): void {
		$this->assertSame($expected, $this->getDefinition($policyKey)->normalizeValue($rawValue));
	}

	/**
	 * @return iterable<string, array{0: string, 1: mixed, 2: mixed}>
	 */
	public static function provideNormalizedValues(): iterable {
		yield 'enabled from a string' => [SignatureRejectionPolicy::KEY_ENABLED, 'true', true];
		yield 'unknown behavior' => [SignatureRejectionPolicy::KEY_BEHAVIOR, 'postpone', 'cancel'];
		yield 'comment mode' => [SignatureRejectionPolicy::KEY_COMMENT_MODE, 'required', 'required'];
		yield 'visibility' => [SignatureRejectionPolicy::KEY_VISIBILITY, 'public', 'public'];
		yield 'unknown comment visibility' => [SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY, 'everyone', 'requester'];
	}

	#[DataProvider('provideInvalidValues')]
	public function testASettingOnlyAcceptsItsOwnValues(string $policyKey, mixed $value): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->getDefinition($policyKey)->validateValue($value, new PolicyContext());
	}

	/**
	 * @return iterable<string, array{0: string, 1: mixed}>
	 */
	public static function provideInvalidValues(): iterable {
		yield 'behavior' => [SignatureRejectionPolicy::KEY_BEHAVIOR, 'postpone'];
		yield 'comment mode' => [SignatureRejectionPolicy::KEY_COMMENT_MODE, 'sometimes'];
		yield 'visibility' => [SignatureRejectionPolicy::KEY_VISIBILITY, 'everyone'];
		yield 'enabled' => [SignatureRejectionPolicy::KEY_ENABLED, 'true'];
	}

	public function testUnknownKeyIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->getProvider()->get('not_a_signature_rejection_key');
	}

	public function testTheCombinedConfigurationIsValidatedBeforeAnyKeyIsSaved(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('The rejection comment cannot be visible to a wider audience than the rejection itself.');

		$this->getDefinition(SignatureRejectionPolicy::KEY_ENABLED)->validateCompositeValuesForPersistence(
			[
				SignatureRejectionPolicy::KEY_ENABLED => true,
				SignatureRejectionPolicy::KEY_BEHAVIOR => 'cancel',
				SignatureRejectionPolicy::KEY_COMMENT_MODE => 'optional',
				SignatureRejectionPolicy::KEY_VISIBILITY => 'participants',
				SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY => 'public',
			],
			[SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY],
			new PolicyContext(),
		);
	}

	#[DataProvider('provideAcceptedCombinations')]
	public function testACombinationThatFitsTogetherIsAccepted(array $combinedValues, array $submittedKeys): void {
		$this->expectNotToPerformAssertions();

		$this->getDefinition(SignatureRejectionPolicy::KEY_ENABLED)
			->validateCompositeValuesForPersistence($combinedValues, $submittedKeys, new PolicyContext());
	}

	/**
	 * @return iterable<string, array{0: array<string, mixed>, 1: list<string>}>
	 */
	public static function provideAcceptedCombinations(): iterable {
		$widened = [
			SignatureRejectionPolicy::KEY_ENABLED => true,
			SignatureRejectionPolicy::KEY_BEHAVIOR => 'cancel',
			SignatureRejectionPolicy::KEY_COMMENT_MODE => 'optional',
			SignatureRejectionPolicy::KEY_VISIBILITY => 'public',
			SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY => 'public',
		];

		// The same change described in either order is the same configuration.
		yield 'both audiences widened at once' => [
			$widened,
			[SignatureRejectionPolicy::KEY_VISIBILITY, SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY],
		];
		yield 'both audiences widened, the other way around' => [
			$widened,
			[SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY, SignatureRejectionPolicy::KEY_VISIBILITY],
		];
		yield 'the comment audience is narrower' => [
			[
				SignatureRejectionPolicy::KEY_ENABLED => true,
				SignatureRejectionPolicy::KEY_BEHAVIOR => 'continue',
				SignatureRejectionPolicy::KEY_COMMENT_MODE => 'required',
				SignatureRejectionPolicy::KEY_VISIBILITY => 'public',
				SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY => 'participants',
			],
			[SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY],
		];
	}

	/**
	 * A key written on its own says nothing about the other four, so refusing it
	 * would only make the outcome depend on which key was saved first.
	 */
	public function testASingleKeyCarriesNoRuleAboutTheOtherSettings(): void {
		$this->expectNotToPerformAssertions();

		$this->getDefinition(SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY)
			->validateValueForPersistence('public', new PolicyContext());
	}

	public function testOnlyTheSettingTheOthersAreGroupedUnderOwnsTheCombinedRules(): void {
		$this->expectNotToPerformAssertions();

		foreach (SignatureRejectionPolicy::DEPENDENT_KEYS as $policyKey) {
			$this->getDefinition($policyKey)->validateCompositeValuesForPersistence(
				[
					SignatureRejectionPolicy::KEY_ENABLED => true,
					SignatureRejectionPolicy::KEY_COMMENT_MODE => 'optional',
					SignatureRejectionPolicy::KEY_VISIBILITY => 'requester',
					SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY => 'public',
				],
				[SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY],
				new PolicyContext(),
			);
		}
	}

	public function testDelegatedRuleCannotDropAParentRequiredComment(): void {
		$this->expectException(\InvalidArgumentException::class);

		$this->getDefinition(SignatureRejectionPolicy::KEY_COMMENT_MODE)
			->validateGroupAdminDelegatedValue('optional', 'required', new PolicyContext());
	}

	public function testDelegatedRuleCannotWidenTheAudienceOfTheParent(): void {
		$this->expectException(\InvalidArgumentException::class);

		$this->getDefinition(SignatureRejectionPolicy::KEY_VISIBILITY)
			->validateGroupAdminDelegatedValue('public', 'participants', new PolicyContext());
	}

	#[DataProvider('provideAcceptedDelegatedValues')]
	public function testDelegatedRuleAcceptsValuesThatDoNotWeakenTheParent(string $policyKey, string $proposed, string $parent): void {
		$this->expectNotToPerformAssertions();

		$this->getDefinition($policyKey)->validateGroupAdminDelegatedValue($proposed, $parent, new PolicyContext());
	}

	/**
	 * @return iterable<string, array{0: string, 1: string, 2: string}>
	 */
	public static function provideAcceptedDelegatedValues(): iterable {
		yield 'parent does not require a comment' => [SignatureRejectionPolicy::KEY_COMMENT_MODE, 'disabled', 'optional'];
		yield 'required comment is kept' => [SignatureRejectionPolicy::KEY_COMMENT_MODE, 'required', 'required'];
		yield 'the audience is narrowed' => [SignatureRejectionPolicy::KEY_VISIBILITY, 'requester', 'participants'];
		yield 'the audience is kept' => [SignatureRejectionPolicy::KEY_VISIBILITY, 'public', 'public'];
		yield 'the comment audience is narrowed' => [SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY, 'participants', 'public'];
	}

	#[DataProvider('provideGroupPolicyManagerCases')]
	public function testCanCurrentActorManageGroupPolicy(
		ActorRole $actorRole,
		?PolicyLayer $systemPolicy,
		array $groupLayers,
		bool $expected,
	): void {
		$context = new PolicyContext();
		$context->setActorRole($actorRole);

		$this->assertSame(
			$expected,
			$this->getDefinition(SignatureRejectionPolicy::KEY_ENABLED)
				->canCurrentActorManageGroupPolicy($context, $systemPolicy, $groupLayers),
		);
	}

	/**
	 * @return iterable<string, array{0: ActorRole, 1: ?PolicyLayer, 2: list<PolicyLayer>, 3: bool}>
	 */
	public static function provideGroupPolicyManagerCases(): iterable {
		yield 'system admin always manages' => [ActorRole::systemAdmin(), null, [], true];
		yield 'regular user never manages' => [ActorRole::regularUser(), null, [], false];
		yield 'group admin without groups' => [ActorRole::groupAdmin(0), null, [], false];
		yield 'group admin without delegation' => [ActorRole::groupAdmin(1), null, [], false];
		yield 'group admin with global delegation' => [
			ActorRole::groupAdmin(1),
			(new PolicyLayer())
				->setScope('global')
				->setAllowChildOverride(true)
				->setValue(true),
			[],
			true,
		];
	}
}
