<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing\Provider\Drivers\Csc;

use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscIntegration;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SignerPHP\PdfSigner\Application\DTO\ExternalSigningPayload;
use SignerPHP\PdfSigner\Application\DTO\HashAlgorithm;
use SignerPHP\PdfSigner\Application\DTO\SignatureAlgorithm;
use SignerPHP\PdfSigner\Application\DTO\SignatureEncoding;
use SignerPHP\PdfSigner\Application\DTO\SigningCertificateDto;
use SignerPHP\PdfSigner\Application\DTO\SigningInputType;
use SignerPHP\PdfSigner\Domain\Exception\SignerException;
use SignerPHP\PdfSigner\Infrastructure\Native\Contract\HttpClientInterface;
use SignerPHP\PdfSigner\Infrastructure\Native\ValueObject\HttpResponse;
use SignerPHP\PdfSigner\Infrastructure\Remote\Csc\CscCertificateDiscovery;
use SignerPHP\PdfSigner\RemoteSigning\DTO\RemoteCertificate;

final class CscIntegrationTest extends TestCase {
	#[DataProvider('unsafeUrls')]
	public function testRejectsUnsafeEndpoint(string $url): void {
		$this->expectException(\InvalidArgumentException::class);
		CscIntegration::fromConfiguredInstance($this->createMock(IClientService::class), $this->config(), ['baseUrl' => $url]);
	}

	public static function unsafeUrls(): array {
		return [
			'HTTP' => ['http://sign.example.org'],
			'loopback' => ['https://127.0.0.1'],
			'private IP' => ['https://10.0.0.1'],
			'IPv6' => ['https://[::1]'],
			'local hostname' => ['https://sign.local'],
			'internal hostname' => ['https://sign.internal'],
			'userinfo' => ['https://user:secret@sign.example.org'],
			'query' => ['https://sign.example.org/?token=secret'],
			'fragment' => ['https://sign.example.org/#token'],
			'encoded path' => ['https://sign.example.org/%2e%2e'],
			'traversal' => ['https://sign.example.org/a/../b'],
			'backslash' => ['https://sign.example.org\@127.0.0.1'],
			'CRLF' => ["https://sign.example.org\r\nHost: localhost"],
		];
	}

	public function testRejectsSecretInsidePublicConfiguration(): void {
		$this->expectException(\InvalidArgumentException::class);
		CscIntegration::fromConfiguredInstance($this->createMock(IClientService::class), $this->config(), [
			'baseUrl' => 'https://sign.example.org', 'accessToken' => 'must-be-in-secret-store',
		]);
	}

	public function testConfiguredInstanceCannotSignWithoutIndividualConsent(): void {
		$adapter = CscIntegration::fromConfiguredInstance($this->createMock(IClientService::class), $this->config(), [
			'baseUrl' => 'https://sign.example.org',
		]);
		$this->expectException(\LogicException::class);
		$this->expectExceptionMessage('signer-bound authorization');
		$adapter->sign(self::payload(), new RemoteCertificate('synthetic', new SigningCertificateDto('unused')));
	}

	public function testRejectsDisabledDnsPinning(): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValueBool')->willReturnCallback(
			static fn (string $key, bool $default = false): bool => $key === 'dns_pinning' ? false : $default,
		);
		$this->expectException(\LogicException::class);
		CscIntegration::fromConfiguredInstance($this->createMock(IClientService::class), $config, ['baseUrl' => 'https://sign.example.org']);
	}

	public function testSignsHashBoundSadWithSyntheticRsaCertificate(): void {
		[$pem, $key] = self::syntheticCert();
		$requests = [];
		$transport = self::transport(static function (string $operation, array $data) use ($pem, $key, &$requests): array {
			$requests[] = [$operation, $data];
			return match ($operation) {
				'info' => ['specs' => '1.0.4.0'],
				'credentials/list' => ['credentialIDs' => ['synthetic']],
				'credentials/info' => self::credentialInfo($pem),
				'credentials/authorize' => ['SAD' => 'one-hash-only'],
				'signatures/signHash' => self::signWithPrivateKey($data, $key),
				default => throw new \LogicException('Unexpected operation'),
			};
		});
		$adapter = CscIntegration::forSyntheticLab($transport, 'http://127.0.0.1:15432');
		$certificates = $adapter->certificates();
		$this->assertCount(1, $certificates);
		$this->assertSame('synthetic', $certificates[0]->identifier);
		$this->assertSame(openssl_x509_fingerprint($pem, 'sha256'), openssl_x509_fingerprint($certificates[0]->certificate->certificatePem, 'sha256'));
		$payload = self::payload();
		$this->assertNotSame('', $adapter->sign($payload, $certificates[0])->bytes);
		$authorized = array_values(array_filter($requests, static fn (array $request) => $request[0] === 'credentials/authorize'));
		$submitted = array_values(array_filter($requests, static fn (array $request) => $request[0] === 'signatures/signHash'));
		$this->assertCount(1, $authorized);
		$this->assertCount(1, $submitted);
		$this->assertSame([$payload->inputBase64], $authorized[0][1]['hash']);
		$this->assertSame([$payload->inputBase64], $submitted[0][1]['hash']);
		$this->assertSame('one-hash-only', $submitted[0][1]['SAD']);
		$this->assertSame(CscCertificateDiscovery::SHA256_RSA_OID, $submitted[0][1]['signAlgo']);
		$this->expectException(\LogicException::class);
		$adapter->sign($payload, $certificates[0]);
	}

	public function testRejectsCertificateSubstitutionBeforeAuthorization(): void {
		[$expected] = self::syntheticCert();
		[$changed] = self::syntheticCert();
		$operations = [];
		$transport = self::transport(static function (string $operation) use ($changed, &$operations): array {
			$operations[] = $operation;
			return match ($operation) {
				'info' => ['specs' => '1.0.4.0'],
				'credentials/info' => self::credentialInfo($changed),
				default => throw new \LogicException('Must not authorize a changed certificate'),
			};
		});
		try {
			CscIntegration::forSyntheticLab($transport, 'http://127.0.0.1')->sign(
				self::payload(), new RemoteCertificate('synthetic', new SigningCertificateDto($expected)),
			);
			$this->fail('Expected certificate substitution rejection');
		} catch (SignerException $e) {
			$this->assertStringContainsString('certificate changed', $e->getMessage());
			$this->assertNotContains('credentials/authorize', $operations);
		}
	}

	public function testUnsupportedDigestNeverSubmitsSignature(): void {
		[$pem] = self::syntheticCert();
		$transport = self::transport(static fn (): array => throw new \LogicException('Unexpected HTTP request'));
		$this->expectException(SignerException::class);
		CscIntegration::forSyntheticLab($transport, 'http://127.0.0.1')->sign(
			self::payload(HashAlgorithm::Sha512), new RemoteCertificate('synthetic', new SigningCertificateDto($pem)),
		);
	}

	public function testAmbiguousSignHashTimeoutIsNotRetried(): void {
		[$pem] = self::syntheticCert();
		$attempts = 0;
		$transport = self::transport(static function (string $operation) use ($pem, &$attempts): array|HttpResponse {
			if ($operation === 'signatures/signHash') {
				$attempts++;
				return new HttpResponse(0, '', 'timeout after posting request');
			}
			return match ($operation) {
				'info' => ['specs' => '1.0.4.0'],
				'credentials/info' => self::credentialInfo($pem),
				'credentials/authorize' => ['SAD' => 'synthetic'],
				default => throw new \LogicException('Unexpected operation'),
			};
		});
		$adapter = CscIntegration::forSyntheticLab($transport, 'http://127.0.0.1');
		$cert = new RemoteCertificate('synthetic', new SigningCertificateDto($pem));
		try {
			$adapter->sign(self::payload(), $cert);
			$this->fail('Expected unknown outcome');
		} catch (SignerException $e) {
			$this->assertSame(1, $attempts);
		}
		$this->expectException(\LogicException::class);
		$adapter->sign(self::payload(), $cert);
	}

	private function config(): IConfig {
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValueBool')->willReturnCallback(static fn (string $name, bool $default = false): bool => $default);
		return $config;
	}

	private static function payload(HashAlgorithm $hash = HashAlgorithm::Sha256): ExternalSigningPayload {
		return new ExternalSigningPayload(base64_encode(str_repeat("\xA7", 32)), SigningInputType::Digest, $hash, SignatureAlgorithm::RsaPkcs1V15, SignatureEncoding::RsaPkcs1);
	}

	private static function syntheticCert(): array {
		$key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
		self::assertInstanceOf(\OpenSSLAsymmetricKey::class, $key);
		$csr = openssl_csr_new(['commonName' => 'CSC synthetic test'], $key);
		self::assertNotFalse($csr);
		$cert = openssl_csr_sign($csr, null, $key, 1);
		self::assertNotFalse($cert);
		self::assertTrue(openssl_x509_export($cert, $pem));
		return [$pem, $key];
	}

	private static function credentialInfo(string $pem): array {
		$der = base64_decode((string)preg_replace('/-----[^-]+-----|\s+/', '', $pem), true);
		self::assertIsString($der);
		return [
			'key' => ['status' => 'enabled', 'algo' => [CscCertificateDiscovery::SHA256_RSA_OID]],
			'cert' => ['certificates' => [base64_encode($der)]],
		];
	}

	private static function signWithPrivateKey(array $data, \OpenSSLAsymmetricKey $key): array {
		$digest = base64_decode($data['hash'][0], true);
		self::assertIsString($digest);
		self::assertSame(32, strlen($digest));
		self::assertTrue(openssl_private_encrypt(hex2bin('3031300d060960864801650304020105000420') . $digest, $raw, $key, OPENSSL_PKCS1_PADDING));
		return ['signatures' => [base64_encode($raw)]];
	}

	private static function transport(\Closure $handler): HttpClientInterface {
		return new class($handler) implements HttpClientInterface {
			public function __construct(
				private \Closure $handler,
			) {
			}
			public function request(string $method, string $url, array $headers = [], string $body = '', int $timeoutSeconds = 10, bool $followRedirects = false): HttpResponse {
				TestCase::assertSame('POST', $method);
				TestCase::assertFalse($followRedirects);
				$operation = substr((string)parse_url($url, PHP_URL_PATH), strlen('/csc/v1/'));
				$result = ($this->handler)($operation, json_decode($body, true, flags: JSON_THROW_ON_ERROR), $headers);
				return $result instanceof HttpResponse ? $result : new HttpResponse(200, json_encode($result, JSON_THROW_ON_ERROR));
			}
		};
	}
}
