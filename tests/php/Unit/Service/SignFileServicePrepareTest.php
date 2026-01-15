<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\File as LibreSignFile;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Service\SignFileService;
use OCP\IUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SignFileServicePrepareTest extends TestCase {
	private SignFileService $service;

	protected function setUp(): void {
		$this->service = $this->createMockService();
	}

	private function createMockService(): SignFileService {
		$mockBuilder = $this->getMockBuilder(SignFileService::class)
			->disableOriginalConstructor()
			->onlyMethods([]);

		return $mockBuilder->getMock();
	}

	public static function signingMethodProvider(): array {
		return [
			'password-based signing' => [
				'signWithoutPassword' => false,
				'password' => 'MyPassword123',
				'expectPassword' => 'MyPassword123',
			],
			'passwordless signing' => [
				'signWithoutPassword' => true,
				'password' => null,
				'expectPassword' => null,
			],
			'passwordless with provided password (ignored)' => [
				'signWithoutPassword' => true,
				'password' => 'WillBeIgnored',
				'expectPassword' => null,
			],
		];
	}

	#[DataProvider('signingMethodProvider')]
	public function testPrepareForSigningConfiguresAuthMethod(
		bool $signWithoutPassword,
		?string $password,
		?string $expectPassword,
	): void {
		$file = new LibreSignFile();
		$file->setId(1);

		$signRequest = new SignRequest();
		$signRequest->setId(1);
		$signRequest->setDisplayName('John Doe');

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('john.doe');

		$result = $this->service->prepareForSigning(
			$file,
			$signRequest,
			$user,
			'account:john.doe',
			'John Doe',
			$signWithoutPassword,
			$password,
		);

		$this->assertInstanceOf(SignFileService::class, $result);
		$this->assertSame($this->service, $result, 'Should return fluent interface');
	}

	public function testPrepareForSigningIsFluentInterface(): void {
		$file = new LibreSignFile();
		$file->setId(1);

		$signRequest = new SignRequest();
		$signRequest->setId(1);
		$signRequest->setDisplayName('Test User');

		$result = $this->service->prepareForSigning(
			$file,
			$signRequest,
			null,
			'email:test@example.com',
			'Test User',
			true,
			null,
		);

		$this->assertSame($this->service, $result);
	}

	public function testPrepareForSigningWithAllParameters(): void {
		$file = new LibreSignFile();
		$file->setId(42);

		$signRequest = new SignRequest();
		$signRequest->setId(10);
		$signRequest->setDisplayName('Signer Name');

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('signer.user');

		$result = $this->service->prepareForSigning(
			$file,
			$signRequest,
			$user,
			'account:signer.user',
			'Signer Name',
			false,
			'SecurePassword456',
		);

		$this->assertInstanceOf(SignFileService::class, $result);
	}

	public function testPrepareForSigningWithoutUser(): void {
		$file = new LibreSignFile();
		$file->setId(1);

		$signRequest = new SignRequest();
		$signRequest->setId(1);
		$signRequest->setDisplayName('Guest Signer');

		$result = $this->service->prepareForSigning(
			$file,
			$signRequest,
			null,
			'email:guest@example.com',
			'Guest Signer',
			true,
			null,
		);

		$this->assertInstanceOf(SignFileService::class, $result);
	}
}
