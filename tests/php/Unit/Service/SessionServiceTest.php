<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Service\SessionService;
use OCP\ISession;

final class SessionServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private function createService(string $browserId, array &$values): SessionService {
		$session = $this->createMock(ISession::class);
		$session->method('getId')->willReturn($browserId);
		$session->method('get')->willReturnCallback(static function (string $key) use (&$values): mixed {
			return $values[$key] ?? null;
		});
		$session->method('set')->willReturnCallback(static function (string $key, mixed $value) use (&$values): void {
			$values[$key] = $value;
		});
		return new SessionService($session);
	}

	public function testGuestElementsAreIsolatedBetweenBrowsersHoldingTheSameLink(): void {
		$values = ['libresign-uuid' => 'sign-request-a'];
		$first = $this->createService('browser-a', $values);
		$second = $this->createService('browser-b', $values);

		$this->assertNotSame($first->getSessionId(), $second->getSessionId());
		$this->assertSame($first->getSessionId(), $first->getSessionId());
	}

	public function testGuestElementsAreIsolatedBetweenSigningRequests(): void {
		$values = ['libresign-uuid' => 'sign-request-a'];
		$service = $this->createService('browser-a', $values);
		$firstId = $service->getSessionId();
		$values['libresign-uuid'] = 'sign-request-b';

		$this->assertNotSame($firstId, $service->getSessionId());
	}

	public function testVerifiedEmailKeyCannotBeUsedOnAnotherSigningRequest(): void {
		$values = ['libresign-uuid' => 'sign-request-a'];
		$service = $this->createService('browser-a', $values);
		$service->setEmailSignatureKey('verified-email-key');
		$this->assertSame('verified-email-key', $service->getEmailSignatureKey());
		$values['libresign-uuid'] = 'sign-request-b';

		$this->assertSame('', $service->getEmailSignatureKey());
	}

	public function testAuthenticatedSessionsKeepTheirExistingStorageId(): void {
		$values = ['user_id' => 'user-a', 'libresign-uuid' => 'sign-request-a'];
		$this->assertSame('browser-a', $this->createService('browser-a', $values)->getSessionId());
	}

	public function testNoSavedKeyIsAvailableWithoutVerification(): void {
		$values = [];
		$this->assertSame('', $this->createService('browser-a', $values)->getEmailSignatureKey());
	}
}
