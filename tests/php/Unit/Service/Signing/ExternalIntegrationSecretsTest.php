<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing;

use OCA\Libresign\Service\Signing\ExternalIntegrationSecrets;
use OCP\Security\ICrypto;
use PHPUnit\Framework\TestCase;

final class ExternalIntegrationSecretsTest extends TestCase {
	private ICrypto $crypto;
	private ExternalIntegrationSecrets $codec;

	protected function setUp(): void {
		$this->crypto = $this->createMock(ICrypto::class);
		$this->codec = new ExternalIntegrationSecrets($this->crypto);
	}

	public function testEmptySecretSetStoresNoPlaintextAndNeedsNoCrypto(): void {
		$this->crypto->expects($this->never())->method('encrypt');
		$this->assertSame('', $this->codec->encrypt([]));
	}

	public function testSecretIsEncryptedBeforePersistence(): void {
		$this->crypto->expects($this->once())->method('encrypt')
			->with('{"accessToken":"synthetic-secret"}')
			->willReturn('ciphertext');
		$this->assertSame('ciphertext', $this->codec->encrypt(['accessToken' => 'synthetic-secret']));
	}

	public function testOmittedSecretFieldsPreserveCiphertextByteForByte(): void {
		$this->crypto->expects($this->never())->method('encrypt');
		$this->crypto->expects($this->never())->method('decrypt');
		$this->assertSame('stored-ciphertext', $this->codec->applyChanges('stored-ciphertext', []));
	}

	public function testSecretReplacementAndExplicitRemoval(): void {
		$this->crypto->method('decrypt')->with('stored-ciphertext')
			->willReturn('{"first":"old","second":"keep","third":"remove"}');
		$this->crypto->expects($this->once())->method('encrypt')
			->with('{"first":"replacement","second":"keep"}')
			->willReturn('new-ciphertext');
		$this->assertSame('new-ciphertext', $this->codec->applyChanges('stored-ciphertext', [
			'first' => 'replacement',
			'third' => null,
		]));
	}

	public function testDecryptFailureDoesNotLeakOriginalExceptionOrStoredValue(): void {
		$this->crypto->method('decrypt')->willThrowException(new \RuntimeException('internal message with secret'));
		try {
			$this->codec->decrypt('ciphertext');
			$this->fail('Expected decryption failure');
		} catch (\RuntimeException $e) {
			$this->assertSame('Integration secrets could not be decrypted', $e->getMessage());
			$this->assertNull($e->getPrevious());
		}
	}

	public function testInvalidEmptyReplacementIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->codec->applyChanges('', ['first' => '']);
	}
}
