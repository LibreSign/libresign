<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider;

use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Provider\NotificationDelivery\NotificationDeliveryModePolicy;
use PHPUnit\Framework\TestCase;

final class NotificationDeliveryModePolicyTest extends TestCase {
	public function testPolicyIsSeparateAndDefaultsToSync(): void {
		$policy = (new NotificationDeliveryModePolicy())->get(NotificationDeliveryModePolicy::KEY);
		$this->assertSame('notification_delivery_mode', $policy->key());
		$this->assertSame('sync', $policy->defaultSystemValue());
		$this->assertSame(['sync', 'async'], $policy->allowedValues(new PolicyContext()));
		$this->assertSame(['system'], $policy->supportedScopes());
		$this->assertFalse($policy->supportsUserPreference());
		$this->assertFalse($policy->supportsRequestOverride());
	}

	public function testAsyncCannotBeEnabledBeforeExecutorExists(): void {
		$policy = (new NotificationDeliveryModePolicy())->get(NotificationDeliveryModePolicy::KEY);
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('not available');
		$policy->validateValueForPersistence('async', new PolicyContext());
	}

	public function testSyncValueCanBePersisted(): void {
		$policy = (new NotificationDeliveryModePolicy())->get(NotificationDeliveryModePolicy::KEY);
		$policy->validateValueForPersistence('sync', new PolicyContext());
		$this->addToAssertionCount(1);
	}
}
