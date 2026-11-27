<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\SignedCopyMail;

use OCA\Libresign\Service\Policy\Model\ActorRole;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Provider\PolicyProviders;
use OCA\Libresign\Service\Policy\Provider\SignedCopyMail\SignedCopyMailPolicy;
use PHPUnit\Framework\TestCase;

final class SignedCopyMailPolicyTest extends TestCase {
	public function testProviderBuildsDisabledDefinition(): void {
		$provider = new SignedCopyMailPolicy();
		$this->assertSame([SignedCopyMailPolicy::KEY], $provider->keys());

		$definition = $provider->get(SignedCopyMailPolicy::KEY);
		$this->assertSame(SignedCopyMailPolicy::KEY, $definition->key());
		$this->assertSame(SignedCopyMailPolicy::SYSTEM_APP_CONFIG_KEY, $definition->getAppConfigKey());
		$this->assertSame('{"enabled":false,"archive_addresses":[]}', $definition->defaultSystemValue());
		$this->assertSame(['system', 'group', 'user'], $definition->supportedScopes());
		$this->assertFalse($definition->supportsRequestOverride());
		$this->assertTrue($definition->supportsGroupAdminDelegation());
	}

	public function testProviderNormalizesPayload(): void {
		$definition = (new SignedCopyMailPolicy())->get(SignedCopyMailPolicy::KEY);

		$this->assertSame(
			'{"enabled":true,"archive_addresses":["archive@example.com"]}',
			$definition->normalizeValue(['enabled' => '1', 'archive_addresses' => ['archive@example.com', 'invalid']]),
		);
	}

	public function testGroupAdminCannotManageWithoutDelegation(): void {
		$definition = (new SignedCopyMailPolicy())->get(SignedCopyMailPolicy::KEY);
		$context = (new PolicyContext())->setActorRole(ActorRole::groupAdmin(1));

		$this->assertFalse($definition->canCurrentActorManageGroupPolicy($context, null, []));
	}

	public function testProviderIsRegistered(): void {
		$this->assertSame(SignedCopyMailPolicy::class, PolicyProviders::BY_KEY[SignedCopyMailPolicy::KEY]);
	}
}
