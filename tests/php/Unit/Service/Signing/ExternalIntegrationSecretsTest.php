<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing;

use OCA\Libresign\Exception\ExternalIntegrationSecretException;
use OCA\Libresign\Service\Signing\ExternalIntegrationSecrets;
use OCP\Security\ICrypto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExternalIntegrationSecretsTest extends TestCase {
	private ICrypto $crypto;
	private ExternalIntegrationSecrets $codec;

	protected function setUp(): void {
		$this->crypto = $this->createMock(ICrypto::class);
		$this->codec = new ExternalIntegrationSecrets($this->crypto);
	}

	/** @return array<string, array{array<string, string>}> */
	public static function validSecretSets(): array {
		return [
			'one access token' => [['accessToken' => 'synthetic-secret']],
			'multiple independently named secrets' => [['clientSecret' => 'client-value', 'accessToken' => 'token-value']],
			'non ASCII credential' => [['password' => 'senha-segura-ç']],
			'largest individual supported value' => [['accessToken' => str_repeat('x', 8192)]],
		];
	}

	#[DataProvider('validSecretSets')]
	public function testValidSecretsArePassedToNativeCryptoAndStoredAsCiphertext(array $secrets): void {
		$this->crypto->expects($this->once())->method('encrypt')
			->with(json_encode($secrets, JSON_THROW_ON_ERROR))
			->willReturn('encrypted-synthetic-contents');
		$this->assertSame('encrypted-synthetic-contents', $this->codec->encrypt($secrets));
	}

	public function testEmptySecretsNeedNoCryptography(): void {
		$this->crypto->expects($this->never())->method('encrypt');
		$this->crypto->expects($this->never())->method('decrypt');
		$this->assertSame('', $this->codec->encrypt([]));
		$this->assertSame([], $this->codec->decrypt(''));
	}

	/** @return array<string, array{array<mixed, mixed>}> */
	public static function invalidSecretSets(): array {
		return [
			'empty field name' => [['' => 'value']],
			'overlong field name' => [[str_repeat('k', 129) => 'value']],
			'numeric field name' => [[0 => 'value']],
			'blank value' => [['token' => '']],
			'overlong value' => [['token' => str_repeat('x', 8193)]],
			'non-string value' => [['token' => 42]],
		];
	}

	#[DataProvider('invalidSecretSets')]
	public function testInvalidSecretSetsAreRejectedBeforeEncryption(array $secrets): void {
		$this->crypto->expects($this->never())->method('encrypt');
		$this->expectException(\InvalidArgumentException::class);
		$this->codec->encrypt($secrets);
	}

	public function testExcessiveAggregatePayloadIsRejected(): void {
		$this->crypto->expects($this->never())->method('encrypt');
		$this->expectException(\InvalidArgumentException::class);
		$this->codec->encrypt(['first' => str_repeat('f', 8192), 'second' => str_repeat('s', 8192)]);
	}

	public function testNativeEncryptionFailureCannotFallBackToUnprotectedStorage(): void {
		$this->crypto->method('encrypt')->willThrowException(new \Exception('backend secret: synthetic-private-value'));
		try {
			$this->codec->encrypt(['accessToken' => 'synthetic-private-value']);
			$this->fail('Encryption failure must interrupt persistence');
		} catch (ExternalIntegrationSecretException $e) {
			$this->assertSame('Integration secrets could not be protected', $e->getMessage());
			$this->assertNull($e->getPrevious());
			$this->assertStringNotContainsString('synthetic-private-value', $e->getMessage());
		}
	}

	/** @return array<string, array{string, array<string, string>}> */
	public static function validDecryptedPayloads(): array {
		return [
			'one credential' => ['{"token":"value"}', ['token' => 'value']],
			'multiple credentials' => ['{"first":"a","second":"b"}', ['first' => 'a', 'second' => 'b']],
			'empty JSON object' => ['{}', []],
		];
	}

	#[DataProvider('validDecryptedPayloads')]
	public function testDecryptReturnsOnlyValidAuthenticatedSecretMaps(string $plaintext, array $expected): void {
		$this->crypto->expects($this->once())->method('decrypt')->with('ciphertext')->willReturn($plaintext);
		$this->assertSame($expected, $this->codec->decrypt('ciphertext'));
	}

	/** @return array<string, array{string}> */
	public static function invalidDecryptedPayloads(): array {
		return [
			'not JSON' => ['not-json'],
			'JSON scalar' => ['null'],
			'JSON list' => ['["token"]'],
			'blank token' => ['{"token":""}'],
			'non-string token' => ['{"token":17}'],
			'blank key' => ['{"":"bad"}'],
		];
	}

	#[DataProvider('invalidDecryptedPayloads')]
	public function testMalformedAuthenticatedPayloadIsRejectedWithoutRevealingDetails(string $plaintext): void {
		$this->crypto->method('decrypt')->willReturn($plaintext);
		try {
			$this->codec->decrypt('ciphertext');
			$this->fail('Invalid stored secrets must not be accepted');
		} catch (ExternalIntegrationSecretException $e) {
			$this->assertSame('Integration secrets could not be decrypted', $e->getMessage());
			$this->assertNull($e->getPrevious());
			$this->assertStringNotContainsString($plaintext, $e->getMessage());
		}
	}

	public function testNativeDecryptionFailureDoesNotExposeOriginalExceptionOrCiphertext(): void {
		$this->crypto->method('decrypt')->willThrowException(new \Exception('synthetic sensitive internal context'));
		try {
			$this->codec->decrypt('ciphertext');
			$this->fail('Decryption failure must interrupt the operation');
		} catch (ExternalIntegrationSecretException $e) {
			$this->assertSame('Integration secrets could not be decrypted', $e->getMessage());
			$this->assertNull($e->getPrevious());
		}
	}

	public function testOmittedChangesPreserveCiphertextByteForByte(): void {
		$this->crypto->expects($this->never())->method('encrypt');
		$this->crypto->expects($this->never())->method('decrypt');
		$this->assertSame('stored-ciphertext', $this->codec->applyChanges('stored-ciphertext', []));
	}

	/** @return array<string, array{array<string, string|null>, string}> */
	public static function validSecretChanges(): array {
		return [
			'replace one, keep another' => [['first' => 'new'], '{"first":"new","second":"preserve"}'],
			'add an independent key' => [['third' => 'added'], '{"first":"old","second":"preserve","third":"added"}'],
			'remove one, keep another' => [['first' => null], '{"second":"preserve"}'],
			'remove missing key is harmless' => [['missing' => null], '{"first":"old","second":"preserve"}'],
		];
	}

	#[DataProvider('validSecretChanges')]
	public function testPartialSecretChangesPreserveUnmentionedKeys(array $changes, string $expectedJson): void {
		$this->crypto->expects($this->once())->method('decrypt')->with('stored-ciphertext')
			->willReturn('{"first":"old","second":"preserve"}');
		$this->crypto->expects($this->once())->method('encrypt')->with($expectedJson)->willReturn('new-ciphertext');
		$this->assertSame('new-ciphertext', $this->codec->applyChanges('stored-ciphertext', $changes));
	}

	public function testExplicitlyClearingLastSecretRemovesCiphertext(): void {
		$this->crypto->expects($this->once())->method('decrypt')->with('stored-ciphertext')->willReturn('{"first":"old"}');
		$this->crypto->expects($this->never())->method('encrypt');
		$this->assertSame('', $this->codec->applyChanges('stored-ciphertext', ['first' => null]));
	}

	/** @return array<string, array{array<mixed, mixed>}> */
	public static function invalidSecretChanges(): array {
		return [
			'blank name' => [['' => 'replace']],
			'overlong name' => [[str_repeat('k', 129) => 'replace']],
			'numeric name' => [[0 => 'replace']],
			'empty replacement' => [['first' => '']],
			'overlong replacement' => [['first' => str_repeat('x', 8193)]],
			'invalid replacement type' => [['first' => false]],
		];
	}

	#[DataProvider('invalidSecretChanges')]
	public function testInvalidSecretPatchFailsWithoutTouchingStoredCredential(array $changes): void {
		$this->crypto->expects($this->never())->method('decrypt');
		$this->crypto->expects($this->never())->method('encrypt');
		$this->expectException(\InvalidArgumentException::class);
		$this->codec->applyChanges('stored-ciphertext', $changes);
	}
}
