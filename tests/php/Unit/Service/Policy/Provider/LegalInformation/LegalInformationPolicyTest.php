<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\LegalInformation;

use OCA\Libresign\Service\Policy\Model\ActorRole;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Provider\LegalInformation\LegalInformationPolicy;
use PHPUnit\Framework\TestCase;

final class LegalInformationPolicyTest extends TestCase {
	public function testProviderBuildsDefinition(): void {
		$provider = new LegalInformationPolicy();
		$this->assertSame([LegalInformationPolicy::KEY], $provider->keys());

		$definition = $provider->get(LegalInformationPolicy::KEY);
		$this->assertSame(LegalInformationPolicy::KEY, $definition->key());
		$this->assertSame('', $definition->defaultSystemValue());
	}

	public function testNormalizesMarkdownAsString(): void {
		$provider = new LegalInformationPolicy();
		$definition = $provider->get(LegalInformationPolicy::KEY);

		$this->assertSame('**Legal** _terms_', $definition->normalizeValue('**Legal** _terms_'));
		$this->assertSame('42', $definition->normalizeValue(42));
	}

	public function testProviderSupportsDelegatedGroupAdminOverlays(): void {
		$provider = new LegalInformationPolicy();
		$definition = $provider->get(LegalInformationPolicy::KEY);

		$this->assertTrue($definition->supportsGroupAdminDelegation());
	}

	public function testGroupAdminCanManageGroupPolicyWhenDelegatedFromSystemCreatedSeed(): void {
		$provider = new LegalInformationPolicy();
		$definition = $provider->get(LegalInformationPolicy::KEY);
		$context = (new PolicyContext())->setActorRole(ActorRole::groupAdmin(1));

		$canManage = $definition->canCurrentActorManageGroupPolicy(
			$context,
			null,
			[
				self::buildPolicyLayer(
					scope: 'group',
					allowChildOverride: false,
					visibleToChild: true,
					value: 'Legal text',
					delegatedFromSystemCreatedSeed: true,
				),
			],
		);

		$this->assertTrue($canManage);
	}

	public function testGroupAdminCanEditSystemCreatedSeedWhenVisibleAndOverridable(): void {
		$provider = new LegalInformationPolicy();
		$definition = $provider->get(LegalInformationPolicy::KEY);
		$context = (new PolicyContext())->setActorRole(ActorRole::groupAdmin(1));

		$canEdit = $definition->canCurrentActorEditSystemCreatedGroupPolicy(
			$context,
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: true,
				visibleToChild: true,
				value: 'Legal text',
				createdBySystemAdmin: true,
			),
		);

		$this->assertTrue($canEdit);
	}

	private static function buildPolicyLayer(
		string $scope,
		bool $allowChildOverride,
		bool $visibleToChild,
		mixed $value,
		bool $createdBySystemAdmin = false,
		bool $delegatedFromSystemCreatedSeed = false,
	): PolicyLayer {
		return (new PolicyLayer())
			->setScope($scope)
			->setAllowChildOverride($allowChildOverride)
			->setVisibleToChild($visibleToChild)
			->setValue($value)
			->setCreatedBySystemAdmin($createdBySystemAdmin)
			->setDelegatedFromSystemCreatedSeed($delegatedFromSystemCreatedSeed);
	}
}
