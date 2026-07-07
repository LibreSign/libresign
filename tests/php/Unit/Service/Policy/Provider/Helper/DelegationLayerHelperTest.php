<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\Helper;

use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Provider\Helper\DelegationLayerHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DelegationLayerHelperTest extends TestCase {
	#[DataProvider('explicitGlobalDelegationCases')]
	public function testHasExplicitGlobalDelegation(?PolicyLayer $systemPolicy, bool $expected): void {
		$this->assertSame($expected, DelegationLayerHelper::hasExplicitGlobalDelegation($systemPolicy));
	}

	#[DataProvider('systemCreatedGroupDelegationCases')]
	public function testHasSystemCreatedGroupDelegation(array $groupLayers, bool $expected): void {
		$this->assertSame($expected, DelegationLayerHelper::hasSystemCreatedGroupDelegation($groupLayers));
	}

	/**
	 * @return iterable<string, array{0: ?PolicyLayer, 1: bool}>
	 */
	public static function explicitGlobalDelegationCases(): iterable {
		yield 'missing system policy does not delegate' => [
			null,
			false,
		];

		yield 'non global scope does not delegate' => [
			self::buildPolicyLayer(scope: 'group', allowChildOverride: true, visibleToChild: true, value: 'seed'),
			false,
		];

		yield 'hidden global scope does not delegate' => [
			self::buildPolicyLayer(scope: 'global', allowChildOverride: true, visibleToChild: false, value: 'seed'),
			false,
		];

		yield 'global scope with null value does not delegate' => [
			self::buildPolicyLayer(scope: 'global', allowChildOverride: true, visibleToChild: true, value: null),
			false,
		];

		yield 'visible global scope with child override delegates' => [
			self::buildPolicyLayer(scope: 'global', allowChildOverride: true, visibleToChild: true, value: 'seed'),
			true,
		];
	}

	/**
	 * @return iterable<string, array{0: array<int, mixed>, 1: bool}>
	 */
	public static function systemCreatedGroupDelegationCases(): iterable {
		yield 'empty group layers do not delegate' => [
			[],
			false,
		];

		yield 'invalid layer entries are ignored' => [
			['not-a-layer'],
			false,
		];

		yield 'hidden group layer does not delegate' => [
			[
				self::buildPolicyLayer(scope: 'group', allowChildOverride: true, visibleToChild: false, value: 'seed', createdBySystemAdmin: true),
			],
			false,
		];

		yield 'delegated lineage grants delegation' => [
			[
				self::buildPolicyLayer(scope: 'group', allowChildOverride: false, visibleToChild: true, value: 'seed', delegatedFromSystemCreatedSeed: true),
			],
			true,
		];

		yield 'system admin created layer with child override delegates' => [
			[
				self::buildPolicyLayer(scope: 'group', allowChildOverride: true, visibleToChild: true, value: 'seed', createdBySystemAdmin: true),
			],
			true,
		];

		yield 'system admin created layer without child override does not delegate' => [
			[
				self::buildPolicyLayer(scope: 'group', allowChildOverride: false, visibleToChild: true, value: 'seed', createdBySystemAdmin: true),
			],
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
