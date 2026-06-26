<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\RequestSignGroups;

use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicy;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicyValue;
use PHPUnit\Framework\TestCase;

final class RequestSignGroupsPolicyTest extends TestCase {
	public function testProviderBuildsGroupsRequestSignDefinition(): void {
		$provider = new RequestSignGroupsPolicy();
		$this->assertSame([RequestSignGroupsPolicy::KEY], $provider->keys());
		$definition = $provider->get(RequestSignGroupsPolicy::KEY);

		$this->assertSame(RequestSignGroupsPolicy::KEY, $definition->key());
		$this->assertSame(
			RequestSignGroupsPolicyValue::encode([
				'allowGroups' => RequestSignGroupsPolicyValue::DEFAULT_ALLOW_GROUPS,
				'denyGroups' => RequestSignGroupsPolicyValue::DEFAULT_DENY_GROUPS,
			]),
			$definition->defaultSystemValue(),
		);
		$this->assertSame([], $definition->allowedValues(new PolicyContext()));
	}

	public function testProviderNormalizesCanonicalGroupPayload(): void {
		$provider = new RequestSignGroupsPolicy();
		$definition = $provider->get(RequestSignGroupsPolicy::KEY);

		$this->assertSame(
			'{"allowGroups":["admin","finance"],"denyGroups":[]}',
			$definition->normalizeValue([
				'allowGroups' => [' finance ', 'admin', 'finance'],
				'denyGroups' => [],
			]),
		);
		$this->assertSame(
			'{"allowGroups":["admin","legal"],"denyGroups":[]}',
			$definition->normalizeValue('{"allowGroups":["legal", "admin"],"denyGroups":[]}'),
		);
	}

	public function testGroupsRequestSignDoesNotSupportUserPreference(): void {
		$provider = new RequestSignGroupsPolicy();
		$definition = $provider->get(RequestSignGroupsPolicy::KEY);

		$this->assertFalse($definition->supportsUserPreference(), 'groups_request_sign must not appear in user preferences');
		$this->assertSame(['system', 'group'], $definition->supportedScopes());
		$this->assertFalse($definition->supportsScope('user'));
	}

	public function testGroupsRequestSignSupportsGroupAdminDelegation(): void {
		$provider = new RequestSignGroupsPolicy();
		$definition = $provider->get(RequestSignGroupsPolicy::KEY);

		$this->assertTrue($definition->supportsGroupAdminDelegation(), 'groups_request_sign must support group-admin delegation overrides');
	}

	public function testValidateGroupAdminDelegatedValuePassesWhenDenyGroupsIsNonEmpty(): void {
		$provider = new RequestSignGroupsPolicy();
		$definition = $provider->get(RequestSignGroupsPolicy::KEY);

		$proposedValue = RequestSignGroupsPolicyValue::encode([
			'allowGroups' => ['board'],
			'denyGroups' => ['board'],
		]);

		$definition->validateGroupAdminDelegatedValue($proposedValue, null, new PolicyContext());

		$this->addToAssertionCount(1);
	}

	public function testValidateGroupAdminDelegatedValueThrowsWhenDenyGroupsIsEmpty(): void {
		$provider = new RequestSignGroupsPolicy();
		$definition = $provider->get(RequestSignGroupsPolicy::KEY);

		$proposedValue = RequestSignGroupsPolicyValue::encode([
			'allowGroups' => ['board', 'company'],
			'denyGroups' => [],
		]);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Add a deny rule to override it');

		$definition->validateGroupAdminDelegatedValue($proposedValue, null, new PolicyContext());
	}

	public function testValidateGroupAdminDelegatedValueSkipsNonStringPayload(): void {
		$provider = new RequestSignGroupsPolicy();
		$definition = $provider->get(RequestSignGroupsPolicy::KEY);

		// Non-string proposed value must not throw (type normalization happens before this hook)
		$definition->validateGroupAdminDelegatedValue(null, null, new PolicyContext());

		$this->addToAssertionCount(1);
	}
}
