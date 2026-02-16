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
	public function testGetSessionIdUsesUuidWhenAvailable(mixed $uuidInSession, string $sessionId, string $expected): void {
		$this->session->method('get')
			->with('libresign-uuid')
			->willReturn($uuidInSession);
		$this->session->method('getId')
			->willReturn($sessionId);

		$this->assertSame($expected, $this->getService()->getSessionId());
	}

	public static function providerGetSessionId(): array {
		return [
			'uuid string present' => ['54afbd0a-a065-4eaf-b611-48ec381b116a', 'session-123', '54afbd0a-a065-4eaf-b611-48ec381b116a'],
			'uuid empty string' => ['', 'session-123', 'session-123'],
			'uuid null' => [null, 'session-123', 'session-123'],
			'uuid non-string' => [123, 'session-123', 'session-123'],
		];
	}
}
