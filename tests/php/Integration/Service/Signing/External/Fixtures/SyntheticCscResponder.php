<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service\Signing\External\Fixtures;

use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Infrastructure\Remote\Csc\CscCertificateDiscovery;
use OCP\Http\Client\IResponse;
use PHPUnit\Framework\TestCase;

/** Deterministic CSC HTTP boundary with a real, throwaway RSA test certificate. */
final class SyntheticCscResponder {
	public const URL = 'https://synthetic-csc.example.org';
	public const CREDENTIAL = 'synthetic-lab-key';
	public readonly string $certificatePem;
	private readonly \OpenSSLAsymmetricKey $privateKey;
	private int $submissions = 0;
	private bool $unknownSigningOutcome = false;
	private bool $discoveryUnavailable = false;

	public function __construct(private readonly \Closure $mock) {
		$key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
		TestCase::assertInstanceOf(\OpenSSLAsymmetricKey::class, $key);
		$config = tempnam(sys_get_temp_dir(), 'csc-ocs-cert-');
		TestCase::assertIsString($config);
		try {
			file_put_contents($config, "[req]\ndistinguished_name = dn\n[dn]\n[v3_signer]\nbasicConstraints = critical,CA:FALSE\nkeyUsage = critical,digitalSignature,nonRepudiation\n");
			$csr = openssl_csr_new(['commonName' => 'Synthetic CSC laboratory signer'], $key);
			TestCase::assertNotFalse($csr);
			$cert = openssl_csr_sign($csr, null, $key, 1, [
				'config' => $config, 'x509_extensions' => 'v3_signer', 'digest_alg' => 'sha256',
			]);
			TestCase::assertNotFalse($cert);
			TestCase::assertTrue(openssl_x509_export($cert, $pem));
			$this->certificatePem = $pem;
			$this->privateKey = $key;
		} finally {
			@unlink($config);
		}
	}

	public function submissions(): int {
		return $this->submissions;
	}

	public function simulateUnknownSigningOutcome(): void {
		$this->unknownSigningOutcome = true;
	}

	public function makeDiscoveryUnavailable(): void {
		$this->discoveryUnavailable = true;
	}

	/** @param array<string, mixed> $options */
	public function respond(string $url, array $options): IResponse {
		TestCase::assertFalse($options['allow_redirects']);
		TestCase::assertArrayNotHasKey('Authorization', $options['headers']);
		$operation = substr((string)parse_url($url, PHP_URL_PATH), strlen('/csc/v1/'));
		if ($this->discoveryUnavailable && $operation === 'credentials/info') {
			throw new \RuntimeException('Synthetic CSC credential discovery unavailable');
		}
		$body = json_decode((string)$options['body'], true, flags: JSON_THROW_ON_ERROR);
		$der = base64_decode((string)preg_replace('/-----[^-]+-----|\s+/', '', $this->certificatePem), true);
		TestCase::assertIsString($der);
		$payload = match ($operation) {
			'info' => ['specs' => '1.0.4.0'],
			'credentials/list' => ['credentialIDs' => [self::CREDENTIAL]],
			'credentials/info' => [
				'key' => ['status' => 'enabled', 'algo' => [CscCertificateDiscovery::SHA256_RSA_OID]],
				'cert' => ['certificates' => [base64_encode($der)]],
			],
			'credentials/authorize' => ['SAD' => 'synthetic-bound-operation'],
			'signatures/signHash' => $this->signHash($body),
			default => throw new \LogicException('Unexpected CSC request ' . $operation),
		};
		$response = ($this->mock)(IResponse::class);
		$response->method('getStatusCode')->willReturn(
			$this->unknownSigningOutcome && $operation === 'signatures/signHash' ? 503 : 200,
		);
		$response->method('getBody')->willReturn(json_encode($payload, JSON_THROW_ON_ERROR));
		return $response;
	}

	/** @param array<string, mixed> $body @return array<string, mixed> */
	private function signHash(array $body): array {
		$this->submissions++;
		TestCase::assertSame('synthetic-bound-operation', $body['SAD']);
		$hash = base64_decode($body['hash'][0], true);
		TestCase::assertIsString($hash);
		TestCase::assertSame(32, strlen($hash));
		TestCase::assertTrue(openssl_private_encrypt(
			hex2bin('3031300d060960864801650304020105000420') . $hash,
			$signature, $this->privateKey, OPENSSL_PKCS1_PADDING,
		));
		return ['signatures' => [base64_encode($signature)]];
	}
}
