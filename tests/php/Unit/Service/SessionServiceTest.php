<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Unit\Service;

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\Service\SessionService;
use OCP\IAppConfig;
use OCP\ISession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class SessionServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private ISession&MockObject $session;
	private IAppConfig&MockObject $appConfig;

	public function setUp(): void {
		$this->session = $this->createMock(ISession::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
	}

	private function getService(): SessionService {
		return new SessionService(
			$this->session,
			$this->appConfig,
		);
	}

	#[DataProvider('providerGetSessionId')]
	public function testGetSessionIdResolvesByContext(?string $userId, mixed $uuid, string $expected): void {
		$this->session->method('get')
			->willReturnCallback(function (string $key) use ($userId, $uuid) {
				return match ($key) {
					'user_id' => $userId,
					'libresign-uuid' => $uuid,
					default => null,
				};
			});
		$this->session->method('getId')
			->willReturn('session-raw-id');

		$this->assertSame($expected, $this->getService()->getSessionId());
	}

	public static function providerGetSessionId(): array {
		return [
			'authenticated keeps raw session id' => ['admin', 'public-uuid', 'session-raw-id'],
			'anonymous uses public uuid when available' => [null, 'public-uuid', 'public-uuid'],
			'anonymous falls back to raw session id' => [null, null, 'session-raw-id'],
		];
	}
}
