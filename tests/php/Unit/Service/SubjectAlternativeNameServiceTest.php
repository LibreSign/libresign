<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Service\SubjectAlternativeNameService;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

class SubjectAlternativeNameServiceTest extends TestCase {
	private SubjectAlternativeNameService $service;
	private IUserManager&MockObject $userManager;

	public function setUp(): void {
		parent::setUp();

		$this->userManager = $this->createMock(IUserManager::class);
		$this->service = new SubjectAlternativeNameService($this->userManager);
	}

	public static function parseProvider(): array {
		return [
			'email format' => [
				'input' => 'email:user@example.com',
				'expected' => ['method' => 'email', 'value' => 'user@example.com'],
			],
			'account format' => [
				'input' => 'account:john.doe',
				'expected' => ['method' => 'account', 'value' => 'john.doe'],
			],
			'telegram format' => [
				'input' => 'telegram:+123456789',
				'expected' => ['method' => 'telegram', 'value' => '+123456789'],
			],
			'signal format' => [
				'input' => 'signal:+987654321',
				'expected' => ['method' => 'signal', 'value' => '+987654321'],
			],
			'sms format' => [
				'input' => 'sms:+111222333',
				'expected' => ['method' => 'sms', 'value' => '+111222333'],
			],
			'whatsapp format' => [
				'input' => 'whatsapp:+444555666',
				'expected' => ['method' => 'whatsapp', 'value' => '+444555666'],
			],
			'xmpp format' => [
				'input' => 'xmpp:user@xmpp.server.com',
				'expected' => ['method' => 'xmpp', 'value' => 'user@xmpp.server.com'],
			],
			'password format' => [
				'input' => 'password:secretvalue',
				'expected' => ['method' => 'password', 'value' => 'secretvalue'],
			],
			'clickToSign format' => [
				'input' => 'clickToSign:token123',
				'expected' => ['method' => 'clickToSign', 'value' => 'token123'],
			],
			'value with multiple colons' => [
				'input' => 'email:user:with:colons',
				'expected' => ['method' => 'email', 'value' => 'user:with:colons'],
			],
			'plain email (fallback)' => [
				'input' => 'user@example.com',
				'expected' => ['method' => 'email', 'value' => 'user@example.com'],
			],
			'invalid format' => [
				'input' => 'invalid:data',
				'expected' => null,
			],
			'invalid plain text' => [
				'input' => 'not-an-email',
				'expected' => null,
			],
			'empty value after colon' => [
				'input' => 'email:',
				'expected' => null,
			],
		];
	}

	#[DataProvider('parseProvider')]
	public function testParse(string $input, ?array $expected): void {
		$result = self::invokePrivate($this->service, 'parse', [$input]);
		$this->assertSame($expected, $result);
	}

	public function testParseFromCertificate(): void {
		$certData = [
			'extensions' => [
				'subjectAltName' => 'email:test@example.com',
			],
		];

		$result = $this->service->parseFromCertificate($certData);

		$this->assertSame(['method' => 'email', 'value' => 'test@example.com'], $result);
	}

	public function testParseFromCertificateWithArray(): void {
		$certData = [
			'extensions' => [
				'subjectAltName' => ['email:test@example.com', 'account:john'],
			],
		];

		$result = $this->service->parseFromCertificate($certData);

		// Should return first element
		$this->assertSame(['method' => 'email', 'value' => 'test@example.com'], $result);
	}

	public function testParseFromCertificateEmpty(): void {
		$certData = [];

		$result = $this->service->parseFromCertificate($certData);

		$this->assertNull($result);
	}

	#[DataProvider('buildProvider')]
	public function testBuild(string $method, string $value, string $expected): void {
		$result = $this->service->build($method, $value);

		$this->assertSame($expected, $result);
	}

	public static function buildProvider(): array {
		return [
			'email method' => [
				'method' => 'email',
				'value' => 'user@example.com',
				'expected' => 'email:user@example.com',
			],
			'account method' => [
				'method' => 'account',
				'value' => 'john.doe',
				'expected' => 'account:john.doe',
			],
			'telegram method' => [
				'method' => 'telegram',
				'value' => '+123456789',
				'expected' => 'telegram:+123456789',
			],
			'signal method' => [
				'method' => 'signal',
				'value' => '+987654321',
				'expected' => 'signal:+987654321',
			],
			'sms method' => [
				'method' => 'sms',
				'value' => '+111222333',
				'expected' => 'sms:+111222333',
			],
			'whatsapp method' => [
				'method' => 'whatsapp',
				'value' => '+444555666',
				'expected' => 'whatsapp:+444555666',
			],
			'xmpp method' => [
				'method' => 'xmpp',
				'value' => 'user@xmpp.server.com',
				'expected' => 'xmpp:user@xmpp.server.com',
			],
			'password method' => [
				'method' => 'password',
				'value' => 'secretvalue',
				'expected' => 'password:secretvalue',
			],
			'clickToSign method' => [
				'method' => 'clickToSign',
				'value' => 'token123',
				'expected' => 'clickToSign:token123',
			],
		];
	}

	public function testBuildForHosts(): void {
		$hosts = [
			'user1@example.com',
			'user2@example.com',
			'not-an-email',
		];

		$result = $this->service->buildForHosts($hosts);

		$this->assertSame('email:user1@example.com, email:user2@example.com', $result);
	}

	public static function extractEmailFromCertificateProvider(): array {
		return [
			'with email prefix' => [
				'certData' => [
					'extensions' => [
						'subjectAltName' => 'email:test@example.com',
					],
				],
				'expected' => 'test@example.com',
			],
			'without prefix' => [
				'certData' => [
					'extensions' => [
						'subjectAltName' => 'user@example.com',
					],
				],
				'expected' => 'user@example.com',
			],
			'empty extensions' => [
				'certData' => [],
				'expected' => null,
			],
			'no subjectAltName' => [
				'certData' => [
					'extensions' => [],
				],
				'expected' => null,
			],
			'array format' => [
				'certData' => [
					'extensions' => [
						'subjectAltName' => ['email:first@example.com', 'email:second@example.com'],
					],
				],
				'expected' => 'first@example.com',
			],
			'multiple emails comma separated' => [
				'certData' => [
					'extensions' => [
						'subjectAltName' => 'email:first@example.com, email:second@example.com',
					],
				],
				'expected' => 'first@example.com',
			],
			'other method (account)' => [
				'certData' => [
					'extensions' => [
						'subjectAltName' => 'account:john',
					],
				],
				'expected' => null,
			],
			'invalid email with prefix' => [
				'certData' => [
					'extensions' => [
						'subjectAltName' => 'email:not-an-email',
					],
				],
				'expected' => null,
			],
			'empty string' => [
				'certData' => [
					'extensions' => [
						'subjectAltName' => '',
					],
				],
				'expected' => null,
			],
		];
	}

	#[DataProvider('extractEmailFromCertificateProvider')]
	public function testExtractEmailFromCertificate(array $certData, ?string $expected): void {
		$result = $this->service->extractEmailFromCertificate($certData);
		$this->assertSame($expected, $result);
	}

	public function testResolveUidWithEmail(): void {
		$certData = [
			'extensions' => [
				'subjectAltName' => 'email:user@example.com',
			],
		];

		$this->userManager->method('getByEmail')
			->with('user@example.com')
			->willReturn([]);

		$result = $this->service->resolveUid($certData, 'example.com');

		$this->assertSame('email:user@example.com', $result);
	}

	public function testResolveUidWithAccountMatch(): void {
		$certData = [
			'extensions' => [
				'subjectAltName' => 'email:john@example.com',
			],
		];

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('john');

		$this->userManager->method('get')
			->with('john')
			->willReturn($user);

		$result = $this->service->resolveUid($certData, 'example.com');

		$this->assertSame('account:john', $result);
	}

	public function testResolveUidWithEmailToAccount(): void {
		$certData = [
			'extensions' => [
				'subjectAltName' => 'email:user@example.com',
			],
		];

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('john');

		$this->userManager->method('getByEmail')
			->with('user@example.com')
			->willReturn([$user]);

		$result = $this->service->resolveUid($certData, 'example.com');

		$this->assertSame('account:john', $result);
	}

	public function testResolveUidWithCN(): void {
		$certData = [
			'subject' => [
				'CN' => 'email:user@example.com, CN=CommonName',
			],
		];

		$this->userManager->method('getByEmail')
			->with('user@example.com')
			->willReturn([]);

		$result = $this->service->resolveUid($certData, 'example.com');

		$this->assertSame('email:user@example.com', $result);
	}

	public function testResolveUidWithOtherMethod(): void {
		$certData = [
			'extensions' => [
				'subjectAltName' => 'telegram:+123456789',
			],
		];

		$result = $this->service->resolveUid($certData, 'example.com');

		$this->assertSame('telegram:+123456789', $result);
	}
}
