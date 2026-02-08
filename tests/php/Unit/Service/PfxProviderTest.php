<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Service\CertificateValidityPolicy;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\ISignatureMethod;
use OCA\Libresign\Service\PfxProvider;
use OCA\Libresign\Tests\Fixtures\FakeSignEngine;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Security\Events\GenerateSecurePasswordEvent;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PfxProviderTest extends TestCase {
	private IEventDispatcher&MockObject $eventDispatcher;
	private ISecureRandom&MockObject $secureRandom;

	protected function setUp(): void {
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->secureRandom = $this->createMock(ISecureRandom::class);
	}

	private function createProvider(): PfxProvider {
		return new PfxProvider(
			new CertificateValidityPolicy(),
			$this->eventDispatcher,
			$this->secureRandom,
		);
	}

	private function createEngine(): FakeSignEngine {
		return new FakeSignEngine();
	}

	private function configurePasswordEvent(string $password): void {
		$this->eventDispatcher
			->method('dispatchTyped')
			->willReturnCallback(function (GenerateSecurePasswordEvent $event) use ($password) {
				$event->setPassword($password);
			});
	}

	public function testReturnsExistingCertificateWithoutGenerating(): void {
		$engine = $this->createEngine();
		$engine->storedCertificate = 'existing-cert';

		$result = $this->createProvider()->getOrGeneratePfx(
			$engine,
			signWithoutPassword: false,
			signatureMethodName: null,
			userUniqueIdentifier: 'account:john',
			friendlyName: 'John Doe',
			password: 'mypass',
		);

		$this->assertSame('existing-cert', $result['pfx']);
		$this->assertSame('mypass', $result['password']);
		$this->assertEmpty($engine->generateCalls);
		$this->assertEmpty($engine->leafExpiryCalls);
	}

	public function testClickToSignGeneratesShortLivedCertificate(): void {
		$this->configurePasswordEvent('temp-pass-123');
		$engine = $this->createEngine();

		$result = $this->createProvider()->getOrGeneratePfx(
			$engine,
			signWithoutPassword: true,
			signatureMethodName: ISignatureMethod::SIGNATURE_METHOD_CLICK_TO_SIGN,
			userUniqueIdentifier: 'account:alice',
			friendlyName: 'Alice Smith',
		);

		$this->assertCount(1, $engine->generateCalls);
		$this->assertSame([
			'host' => 'account:alice',
			'uid' => 'account:alice',
			'name' => 'Alice Smith',
		], $engine->generateCalls[0]['user']);
		$this->assertSame('temp-pass-123', $engine->generateCalls[0]['signPassword']);
		$this->assertSame('Alice Smith', $engine->generateCalls[0]['friendlyName']);

		$this->assertSame([1, null], $engine->leafExpiryCalls);
	}

	public function testSignWithoutPasswordButNonClickToSignSkipsExpiryOverride(): void {
		$this->configurePasswordEvent('temp-pass');
		$engine = $this->createEngine();

		$this->createProvider()->getOrGeneratePfx(
			$engine,
			signWithoutPassword: true,
			signatureMethodName: 'password',
			userUniqueIdentifier: 'user@example.com',
			friendlyName: 'User Example',
		);

		$this->assertCount(1, $engine->generateCalls);
		$this->assertEmpty($engine->leafExpiryCalls);
	}

	public function testSignWithPasswordDoesNotGenerateCertificate(): void {
		$engine = $this->createEngine();

		$result = $this->createProvider()->getOrGeneratePfx(
			$engine,
			signWithoutPassword: false,
			signatureMethodName: null,
			userUniqueIdentifier: 'account:bob',
			friendlyName: 'Bob Jones',
			password: 'user-password',
		);

		$this->assertEmpty($engine->generateCalls);
		$this->assertSame('user-password', $result['password']);
		$this->assertSame('fake-pfx-content', $result['pfx']);
	}

	public function testStripsAccountPrefixFromUid(): void {
		$engine = $this->createEngine();

		$this->createProvider()->getOrGeneratePfx(
			$engine,
			signWithoutPassword: false,
			signatureMethodName: null,
			userUniqueIdentifier: 'account:john',
			friendlyName: 'John',
			password: 'pass',
		);

		$this->assertSame(['john'], $engine->getPfxCalls);
	}

	public function testPreservesUidWithoutAccountPrefix(): void {
		$engine = $this->createEngine();

		$this->createProvider()->getOrGeneratePfx(
			$engine,
			signWithoutPassword: false,
			signatureMethodName: null,
			userUniqueIdentifier: 'user@example.com',
			friendlyName: 'User',
			password: 'pass',
		);

		$this->assertSame(['user@example.com'], $engine->getPfxCalls);
	}

	public function testEmptyPasswordReturnsNullInResult(): void {
		$engine = $this->createEngine();

		$result = $this->createProvider()->getOrGeneratePfx(
			$engine,
			signWithoutPassword: false,
			signatureMethodName: null,
			userUniqueIdentifier: 'account:user1',
			friendlyName: 'User One',
			password: '',
		);

		$this->assertNull($result['password']);
	}

	public function testTemporaryPasswordComesFromEvent(): void {
		$this->configurePasswordEvent('event-generated-pw');
		$engine = $this->createEngine();

		$result = $this->createProvider()->getOrGeneratePfx(
			$engine,
			signWithoutPassword: true,
			signatureMethodName: 'password',
			userUniqueIdentifier: 'account:user1',
			friendlyName: 'User',
		);

		$this->assertSame('event-generated-pw', $result['password']);
		$this->assertSame('event-generated-pw', $engine->generateCalls[0]['signPassword']);
	}

	public function testFallsBackToSecureRandomWhenEventReturnsNoPassword(): void {
		$this->eventDispatcher->method('dispatchTyped');
		$this->secureRandom
			->expects($this->once())
			->method('generate')
			->with(20)
			->willReturn('random-20-char-value!');

		$engine = $this->createEngine();

		$result = $this->createProvider()->getOrGeneratePfx(
			$engine,
			signWithoutPassword: true,
			signatureMethodName: 'password',
			userUniqueIdentifier: 'account:user1',
			friendlyName: 'User',
		);

		$this->assertSame('random-20-char-value!', $result['password']);
	}

	public function testExpiryOverrideIsCleanedUpOnGenerationFailure(): void {
		$this->configurePasswordEvent('temp');
		$engine = $this->createEngine();
		$engine->shouldFailOnGenerate = true;

		try {
			$this->createProvider()->getOrGeneratePfx(
				$engine,
				signWithoutPassword: true,
				signatureMethodName: ISignatureMethod::SIGNATURE_METHOD_CLICK_TO_SIGN,
				userUniqueIdentifier: 'account:user1',
				friendlyName: 'User',
			);
			$this->fail('Expected RuntimeException');
		} catch (\RuntimeException) {
			// Expected
		}

		$this->assertSame([1, null], $engine->leafExpiryCalls);
		$this->assertNull($engine->currentLeafExpiry);
	}

	public function testGeneratedPasswordIsSetOnEngineBeforeGettingPfx(): void {
		$this->configurePasswordEvent('generated-pass');
		$engine = $this->createEngine();

		$this->createProvider()->getOrGeneratePfx(
			$engine,
			signWithoutPassword: true,
			signatureMethodName: 'password',
			userUniqueIdentifier: 'account:signer',
			friendlyName: 'Signer Name',
		);

		$this->assertContains('generated-pass', $engine->setPasswordCalls);
		$this->assertNotEmpty($engine->getPfxCalls);
	}

	#[DataProvider('providerSignatureMethodExpiryBehavior')]
	public function testSignatureMethodExpiryBehavior(
		?string $signatureMethodName,
		array $expectedExpiryCalls,
	): void {
		$this->configurePasswordEvent('temp');
		$engine = $this->createEngine();

		$this->createProvider()->getOrGeneratePfx(
			$engine,
			signWithoutPassword: true,
			signatureMethodName: $signatureMethodName,
			userUniqueIdentifier: 'account:user1',
			friendlyName: 'User',
		);

		$this->assertSame($expectedExpiryCalls, $engine->leafExpiryCalls);
	}

	public static function providerSignatureMethodExpiryBehavior(): array {
		return [
			'click-to-sign sets 1-day expiry then cleans up' => [
				ISignatureMethod::SIGNATURE_METHOD_CLICK_TO_SIGN,
				[1, null],
			],
			'password method does not set expiry' => [
				'password',
				[],
			],
			'null method does not set expiry' => [
				null,
				[],
			],
		];
	}

	public function testUserDataPassedCorrectlyToGenerateCertificate(): void {
		$this->configurePasswordEvent('pass');
		$engine = $this->createEngine();

		$this->createProvider()->getOrGeneratePfx(
			$engine,
			signWithoutPassword: true,
			signatureMethodName: 'password',
			userUniqueIdentifier: 'external@company.com',
			friendlyName: 'External Signer',
		);

		$call = $engine->generateCalls[0];
		$this->assertSame('external@company.com', $call['user']['host']);
		$this->assertSame('external@company.com', $call['user']['uid']);
		$this->assertSame('External Signer', $call['user']['name']);
		$this->assertSame('External Signer', $call['friendlyName']);
	}
}
