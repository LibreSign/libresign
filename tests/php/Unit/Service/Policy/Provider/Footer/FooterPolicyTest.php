<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\Footer;

use OCA\Libresign\Handler\FooterHandler;
use OCA\Libresign\Service\Policy\Model\ActorRole;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicy;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicyValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FooterPolicyTest extends TestCase {
	public function testProviderBuildsAddFooterDefinition(): void {
		$provider = new FooterPolicy();
		$this->assertSame([FooterPolicy::KEY], $provider->keys());
		$definition = $provider->get(FooterPolicy::KEY);
		$defaultTemplate = (string)file_get_contents(FooterHandler::DEFAULT_TEMPLATE_PATH);

		$this->assertSame(FooterPolicy::KEY, $definition->key());
		$this->assertSame(
			FooterPolicyValue::encode(FooterPolicyValue::defaults()),
			$definition->defaultSystemValue(),
		);
		$this->assertSame(
			['defaultSystemValue' => FooterPolicyValue::encode(FooterPolicyValue::defaults($defaultTemplate), $defaultTemplate)],
			$definition->resolvedStateMeta(new PolicyContext()),
		);
		$this->assertSame([], $definition->allowedValues(new PolicyContext()));
	}

	public function testProviderSupportsDelegatedGroupAdminOverlays(): void {
		$provider = new FooterPolicy();
		$definition = $provider->get(FooterPolicy::KEY);

		$this->assertTrue($definition->supportsGroupAdminDelegation());
	}

	#[DataProvider('normalizationCases')]
	public function testProviderNormalizesValues(mixed $input, array $expected): void {
		$provider = new FooterPolicy();
		$definition = $provider->get(FooterPolicy::KEY);
		$defaultTemplate = (string)file_get_contents(FooterHandler::DEFAULT_TEMPLATE_PATH);

		$this->assertSame(
			FooterPolicyValue::encode($expected, $defaultTemplate),
			$definition->normalizeValue($input),
		);
	}

	/**
	 * @return array<string, array{0: mixed, 1: array{enabled: bool, writeQrcodeOnFooter: bool, validationSite: string, customizeFooterTemplate: bool, footerTemplate?: string, previewWidth?: int, previewHeight?: int, previewZoom?: int}}>
	 */
	public static function normalizationCases(): array {
		return [
			'boolean true enables footer with defaults' => [
				true,
				[
					'enabled' => true,
					'writeQrcodeOnFooter' => true,
					'validationSite' => '',
					'customizeFooterTemplate' => false,
					'footerTemplate' => '',
					'previewWidth' => 595,
					'previewHeight' => 100,
					'previewZoom' => 100,
				],
			],
			'string zero disables footer with defaults' => [
				'0',
				[
					'enabled' => false,
					'writeQrcodeOnFooter' => true,
					'validationSite' => '',
					'customizeFooterTemplate' => false,
					'footerTemplate' => '',
					'previewWidth' => 595,
					'previewHeight' => 100,
					'previewZoom' => 100,
				],
			],
			'structured json keeps full explicit payload' => [
				'{"enabled":true,"writeQrcodeOnFooter":false,"validationSite":"https://validation.example","customizeFooterTemplate":true,"previewWidth":740,"previewHeight":160,"previewZoom":130}',
				[
					'enabled' => true,
					'writeQrcodeOnFooter' => false,
					'validationSite' => 'https://validation.example',
					'customizeFooterTemplate' => true,
					'footerTemplate' => '',
					'previewWidth' => 740,
					'previewHeight' => 160,
					'previewZoom' => 130,
				],
			],
			'legacy snake case json is normalized' => [
				'{"addFooter":"1","write_qrcode_on_footer":"0","validation_site":" https://legacy.example/base/ ","customize_footer_template":"1"}',
				[
					'enabled' => true,
					'writeQrcodeOnFooter' => false,
					'validationSite' => 'https://legacy.example/base/',
					'customizeFooterTemplate' => true,
					'footerTemplate' => '',
					'previewWidth' => 595,
					'previewHeight' => 100,
					'previewZoom' => 100,
				],
			],
			'invalid json falls back to enabled false from scalar parser' => [
				'{invalid-json',
				[
					'enabled' => false,
					'writeQrcodeOnFooter' => true,
					'validationSite' => '',
					'customizeFooterTemplate' => false,
					'footerTemplate' => '',
					'previewWidth' => 595,
					'previewHeight' => 100,
					'previewZoom' => 100,
				],
			],
		];
	}

	#[DataProvider('validationSiteOverrideCases')]
	public function testValidationSiteOverrideRules(
		ActorRole $actorRole,
		bool $expectException,
	): void {
		$provider = new FooterPolicy();
		$definition = $provider->get(FooterPolicy::KEY);
		$value = $definition->normalizeValue([
			'enabled' => true,
			'writeQrcodeOnFooter' => true,
			'validationSite' => 'https://internal.example/validation',
			'customizeFooterTemplate' => false,
		]);

		$context = (new PolicyContext())
			->setActorRole($actorRole);

		if ($expectException) {
			$this->expectException(\InvalidArgumentException::class);
		}

		$definition->validateValue($value, $context);

		if (!$expectException) {
			$this->addToAssertionCount(1);
		}
	}

	#[DataProvider('provideCanCurrentActorManageGroupPolicyCases')]
	public function testCanCurrentActorManageGroupPolicyWithDataProvider(
		ActorRole $actorRole,
		?PolicyLayer $systemPolicy,
		array $groupLayers,
		bool $expected,
	): void {
		$provider = new FooterPolicy();
		$definition = $provider->get(FooterPolicy::KEY);
		$context = (new PolicyContext())->setActorRole($actorRole);

		$this->assertSame(
			$expected,
			$definition->canCurrentActorManageGroupPolicy($context, $systemPolicy, $groupLayers),
		);
	}

	#[DataProvider('provideCanCurrentActorEditSystemCreatedGroupPolicyCases')]
	public function testCanCurrentActorEditSystemCreatedGroupPolicyWithDataProvider(
		ActorRole $actorRole,
		?PolicyLayer $systemPolicy,
		PolicyLayer $existingPolicy,
		bool $expected,
	): void {
		$provider = new FooterPolicy();
		$definition = $provider->get(FooterPolicy::KEY);
		$context = (new PolicyContext())->setActorRole($actorRole);

		$this->assertSame(
			$expected,
			$definition->canCurrentActorEditSystemCreatedGroupPolicy($context, $systemPolicy, $existingPolicy),
		);
	}

	/**
	 * @return array<string, array{0: ActorRole, 1: bool}>
	 */
	public static function validationSiteOverrideCases(): array {
		return [
			'rejects override for regular actors' => [
				ActorRole::regularUser(),
				true,
			],
			'allows override for policy managers' => [
				ActorRole::groupAdmin(1),
				false,
			],
		];
	}

	/**
	 * @return iterable<string, array{0: ActorRole, 1: ?PolicyLayer, 2: list<PolicyLayer>, 3: bool}>
	 */
	public static function provideCanCurrentActorManageGroupPolicyCases(): iterable {
		yield 'system admin can always manage footer group policy' => [
			ActorRole::systemAdmin(),
			null,
			[],
			true,
		];

		yield 'regular user cannot manage footer group policy' => [
			ActorRole::regularUser(),
			null,
			[],
			false,
		];

		yield 'group admin with explicit global delegation can manage footer group policy' => [
			ActorRole::groupAdmin(1),
			self::buildPolicyLayer(scope: 'global', allowChildOverride: true, visibleToChild: true, value: 'seed'),
			[],
			true,
		];

		yield 'group admin with system-created footer seed can manage footer group policy' => [
			ActorRole::groupAdmin(1),
			null,
			[
				self::buildPolicyLayer(
					scope: 'group',
					allowChildOverride: true,
					visibleToChild: true,
					value: 'seed',
					createdBySystemAdmin: true,
				),
			],
			true,
		];

		yield 'group admin with delegated footer overlay lineage can manage footer group policy' => [
			ActorRole::groupAdmin(1),
			null,
			[
				self::buildPolicyLayer(
					scope: 'group',
					allowChildOverride: false,
					visibleToChild: true,
					value: 'seed',
					delegatedFromSystemCreatedSeed: true,
				),
			],
			true,
		];

		yield 'group admin without manageable groups cannot manage footer group policy' => [
			ActorRole::groupAdmin(0),
			self::buildPolicyLayer(scope: 'global', allowChildOverride: true, visibleToChild: true, value: 'seed'),
			[],
			false,
		];

		yield 'group admin without explicit or seed delegation cannot manage footer group policy' => [
			ActorRole::groupAdmin(1),
			null,
			[
				self::buildPolicyLayer(
					scope: 'group',
					allowChildOverride: false,
					visibleToChild: true,
					value: 'seed',
					createdBySystemAdmin: true,
				),
			],
			false,
		];
	}

	/**
	 * @return iterable<string, array{0: ActorRole, 1: ?PolicyLayer, 2: PolicyLayer, 3: bool}>
	 */
	public static function provideCanCurrentActorEditSystemCreatedGroupPolicyCases(): iterable {
		yield 'system admin can edit any footer seed' => [
			ActorRole::systemAdmin(),
			null,
			self::buildPolicyLayer(scope: 'group', allowChildOverride: true, visibleToChild: true, value: 'seed'),
			true,
		];

		yield 'group admin can edit visible footer seed' => [
			ActorRole::groupAdmin(1),
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: true,
				visibleToChild: true,
				value: 'seed',
				createdBySystemAdmin: true,
			),
			true,
		];

		yield 'group admin can edit footer seed when explicit global delegation exists' => [
			ActorRole::groupAdmin(1),
			self::buildPolicyLayer(scope: 'global', allowChildOverride: true, visibleToChild: true, value: 'seed'),
			self::buildPolicyLayer(scope: 'group', allowChildOverride: true, visibleToChild: true, value: 'seed'),
			true,
		];

		yield 'group admin cannot edit non-system-created footer seed without explicit global delegation' => [
			ActorRole::groupAdmin(1),
			null,
			self::buildPolicyLayer(scope: 'group', allowChildOverride: true, visibleToChild: true, value: 'seed'),
			false,
		];

		yield 'group admin cannot edit invisible footer seed' => [
			ActorRole::groupAdmin(1),
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: true,
				visibleToChild: false,
				value: 'seed',
				createdBySystemAdmin: true,
			),
			false,
		];

		yield 'group admin cannot edit footer seed that disallows child override' => [
			ActorRole::groupAdmin(1),
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: false,
				visibleToChild: true,
				value: 'seed',
				createdBySystemAdmin: true,
			),
			false,
		];

		yield 'regular user cannot edit footer seed' => [
			ActorRole::regularUser(),
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: true,
				visibleToChild: true,
				value: 'seed',
				createdBySystemAdmin: true,
			),
			false,
		];
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
