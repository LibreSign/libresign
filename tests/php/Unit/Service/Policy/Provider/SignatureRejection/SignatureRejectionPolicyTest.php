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
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicyValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SignatureRejectionPolicyTest extends TestCase {
	private function getDefinition(): IPolicyDefinition {
		return (new SignatureRejectionPolicy())->get(SignatureRejectionPolicy::KEY);
	}

	public function testProviderBuildsSignatureRejectionDefinition(): void {
		$provider = new SignatureRejectionPolicy();

		$this->assertSame([SignatureRejectionPolicy::KEY], $provider->keys());

		$definition = $provider->get(SignatureRejectionPolicy::KEY);
		$this->assertSame(SignatureRejectionPolicy::KEY, $definition->key());
		$this->assertSame(SignatureRejectionPolicy::SYSTEM_APP_CONFIG_KEY, $definition->getAppConfigKey());
		$this->assertSame(SignatureRejectionPolicyValue::defaults(), $definition->defaultSystemValue());
	}

	public function testUnknownKeyIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		(new SignatureRejectionPolicy())->get('not_a_signature_rejection_key');
	}

	public function testRejectionRulesAreNotAPersonalPreference(): void {
		$definition = $this->getDefinition();

		$this->assertFalse($definition->supportsUserPreference());
		$this->assertFalse($definition->supportsScope(PolicySpec::SCOPE_USER));
		$this->assertTrue($definition->supportsScope(PolicySpec::SCOPE_SYSTEM));
		$this->assertTrue($definition->supportsScope(PolicySpec::SCOPE_GROUP));
		$this->assertTrue($definition->supportsGroupAdminDelegation());
		$this->assertSame([], $definition->allowedValues(new PolicyContext()));
	}

	public function testNormalizeValueDelegatesToTheValueObject(): void {
		$this->assertSame(
			SignatureRejectionPolicyValue::normalize(['enabled' => true, 'comment_mode' => 'required']),
			$this->getDefinition()->normalizeValue(['enabled' => true, 'comment_mode' => 'required']),
		);
	}

	public function testValidateValueAcceptsANormalizedValue(): void {
		$this->expectNotToPerformAssertions();

		$this->getDefinition()->validateValue(
			SignatureRejectionPolicyValue::normalize(['enabled' => true, 'comment_mode' => 'optional']),
			new PolicyContext(),
		);
	}

	#[DataProvider('provideInvalidValues')]
	public function testValidateValueRejectsMalformedValues(mixed $value): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->getDefinition()->validateValue($value, new PolicyContext());
	}

	/**
	 * @return iterable<string, array{0: mixed}>
	 */
	public static function provideInvalidValues(): iterable {
		yield 'not an array' => ['enabled'];
		yield 'missing enabled' => [['comment_mode' => 'optional']];
		yield 'enabled is not a boolean' => [['enabled' => 1, 'comment_mode' => 'optional']];
		yield 'missing comment mode' => [['enabled' => true]];
		yield 'unknown comment mode' => [['enabled' => true, 'comment_mode' => 'sometimes']];
	}

	public function testDelegatedRuleCannotDropAParentRequiredComment(): void {
		$this->expectException(\InvalidArgumentException::class);

		$this->getDefinition()->validateGroupAdminDelegatedValue(
			['enabled' => true, 'comment_mode' => 'optional'],
			['enabled' => true, 'comment_mode' => 'required'],
			new PolicyContext(),
		);
	}

	#[DataProvider('provideAcceptedDelegatedValues')]
	public function testDelegatedRuleAcceptsValuesThatDoNotWeakenTheParent(array $proposed, array $parent): void {
		$this->expectNotToPerformAssertions();

		$this->getDefinition()->validateGroupAdminDelegatedValue($proposed, $parent, new PolicyContext());
	}

	/**
	 * @return iterable<string, array{0: array<string, mixed>, 1: array<string, mixed>}>
	 */
	public static function provideAcceptedDelegatedValues(): iterable {
		yield 'parent does not require a comment' => [
			['enabled' => true, 'comment_mode' => 'disabled'],
			['enabled' => true, 'comment_mode' => 'optional'],
		];
		yield 'required comment is kept' => [
			['enabled' => true, 'comment_mode' => 'required'],
			['enabled' => true, 'comment_mode' => 'required'],
		];
		yield 'parent is disabled entirely' => [
			['enabled' => true, 'comment_mode' => 'optional'],
			['enabled' => false, 'comment_mode' => 'required'],
		];
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
			$this->getDefinition()->canCurrentActorManageGroupPolicy($context, $systemPolicy, $groupLayers),
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
				->setValue(SignatureRejectionPolicyValue::defaults()),
			[],
			true,
		];
	}
}
