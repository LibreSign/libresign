<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service\Signing\External;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Service\ExperimentalFeaturesService;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Signing\ExternalIntegrationAvailability;
use OCA\Libresign\Service\Signing\ExternalIntegrationRecord;
use OCA\Libresign\Service\Signing\ExternalIntegrationStore;
use OCA\Libresign\Service\Signing\External\Authorization;
use OCA\Libresign\Service\Signing\External\CredentialSelection;
use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscDriver;
use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscIntegration;
use OCA\Libresign\Service\Validation\SignerValidator;
use OCA\Libresign\Service\Validation\SigningRequestValidator;
use OCA\Libresign\Tests\Integration\TestCase;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Infrastructure\Native\Contract\HttpClientInterface;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Infrastructure\Native\ValueObject\HttpResponse;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Infrastructure\Remote\Csc\CscCertificateDiscovery;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUserSession;
use OCP\Server;

final class CredentialSelectionTest extends TestCase {
	private const REQUEST_UUID = 'dc37db93-4fc7-40a1-8f7c-d94fe3be790e';
	private const LAB_URL = 'http://127.0.0.1:15432';
	private const EMAIL = 'synthetic@example.org';

	private CredentialSelection $selection;
	private ExternalIntegrationRecord $record;
	private ExternalIntegrationStore $store;
	private SignerValidator $validator;
	private bool $enabled = true;

	#[\Override]
	public function setUp(): void {
		parent::setUp();
		$this->enabled = true;
		$gate = $this->createMock(ExperimentalFeaturesService::class);
		$gate->method('isEnabled')->willReturnCallback(fn (): bool => $this->enabled);
		$signRequest = new SignRequest();
		$signRequest->setId(9876);
		$signRequest->setUuid(self::REQUEST_UUID);
		$signRequest->setFileId(9877);
		$signRequest->setStatus(SignRequestStatus::ABLE_TO_SIGN->value);
		$file = new File();
		$file->setId(9877);
		$requests = $this->createMock(SignRequestMapper::class);
		$requests->method('getByUuid')->willReturn($signRequest);
		$files = $this->createMock(FileMapper::class);
		$files->method('getById')->willReturn($file);
		$this->validator = $this->createMock(SignerValidator::class);
		$identityEntity = new IdentifyMethod();
		$identityEntity->setIdentifierKey('email');
		$identityEntity->setIdentifierValue(self::EMAIL);
		$method = $this->createMock(IIdentifyMethod::class);
		$method->method('getEntity')->willReturn($identityEntity);
		$methods = $this->createMock(IdentifyMethodService::class);
		$methods->method('getIdentifyMethodsFromSignRequestId')->willReturn(['email' => [$method]]);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn(null);
		$authorization = new Authorization($gate, $requests, $files,
			$this->validator, $this->createMock(SigningRequestValidator::class), $methods, $session);
		$this->store = Server::get(ExternalIntegrationStore::class);
		$created = $this->store->create(CscDriver::ID, 'Synthetic CSC provider',
			['baseUrl' => self::LAB_URL], []);
		$this->record = $this->store->setEnabled($created->id, $created->revision, true);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $value): string => $value);
		$this->selection = new CredentialSelection($authorization, $this->store,
			new ExternalIntegrationAvailability($gate), new CscDriver($l10n));
	}

	#[\Override]
	public function tearDown(): void {
		try {
			$qb = Server::get(IDBConnection::class)->getQueryBuilder();
			$qb->delete('libresign_signing_integration')
				->where($qb->expr()->eq('public_id', $qb->createNamedParameter($this->record->id)))
				->executeStatement();
		} finally {
			parent::tearDown();
		}
	}

	private function certificate(): string {
		$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
		self::assertNotFalse($key);
		$req = tempnam(sys_get_temp_dir(), 'csc-select-');
		self::assertIsString($req);
		try {
			self::assertNotFalse(file_put_contents($req,
				"[req]\ndistinguished_name = dn\n[dn]\n[v3_signer]\nbasicConstraints = critical,CA:FALSE\nkeyUsage = critical,digitalSignature\n"));
			$csr = openssl_csr_new(['commonName' => 'Synthetic CSC signer'], $key);
			self::assertNotFalse($csr);
			$cert = openssl_csr_sign($csr, null, $key, 1, [
				'config' => $req, 'x509_extensions' => 'v3_signer', 'digest_alg' => 'sha256',
			]);
			self::assertNotFalse($cert);
			self::assertTrue(openssl_x509_export($cert, $pem));
			return $pem;
		} finally {
			@unlink($req);
		}
	}

	/** @return array{CscIntegration, HttpClientInterface&object} */
	private function laboratory(): array {
		$pem = $this->certificate();
		$der = base64_decode((string)preg_replace('/-----[^-]+-----|\s+/', '', $pem), true);
		self::assertIsString($der);
		$transport = new class($der) implements HttpClientInterface {
			public int $calls = 0;

			public function __construct(
				private string $certificateDer,
			) {
			}

			public function request(string $method, string $url, array $headers = [], string $body = '',
				int $timeoutSeconds = 10, bool $followRedirects = false): HttpResponse {
				$this->calls++;
				CredentialSelectionTest::assertSame('POST', $method);
				CredentialSelectionTest::assertFalse($followRedirects);
				$path = (string)parse_url($url, PHP_URL_PATH);
				$payload = match ($path) {
					'/csc/v1/info' => ['specs' => '1.0.4.0'],
					'/csc/v1/credentials/list' => ['credentialIDs' => ['synthetic-cert']],
					'/csc/v1/credentials/info' => [
						'key' => ['status' => 'enabled', 'algo' => [CscCertificateDiscovery::SHA256_RSA_OID]],
						'cert' => ['certificates' => [base64_encode($this->certificateDer)]],
					],
					default => throw new \LogicException('Unexpected CSC laboratory request: ' . $path),
				};
				return new HttpResponse(200, json_encode($payload, JSON_THROW_ON_ERROR));
			}
		};
		return [CscIntegration::forSyntheticLab($transport, self::LAB_URL), $transport];
	}

	public function testVerifiedGuestSeesOnlyPublicSyntheticCredentialMetadata(): void {
		[$lab, $transport] = $this->laboratory();
		$this->validator->expects($this->once())->method('validateCredentials')
			->with($this->anything(), 'email', self::EMAIL, 'verified-token');
		$selection = $this->selection->discoverSynthetic(self::REQUEST_UUID, 'email', self::EMAIL,
			'verified-token', $this->record->id, $lab);
		self::assertGreaterThanOrEqual(3, $transport->calls);
		self::assertSame($this->record->id, $selection['integration']['id']);
		self::assertSame('Synthetic CSC provider', $selection['integration']['name']);
		self::assertTrue($selection['syntheticOnly']);
		self::assertFalse($selection['personalSignerAuthorization']);
		self::assertCount(1, $selection['credentials']);
		$credential = $selection['credentials'][0];
		self::assertSame('synthetic-cert', $credential['id']);
		self::assertSame('Synthetic CSC signer', $credential['subject']);
		self::assertSame(['pdf-basic', 'pades-baseline-b'], $credential['profiles']);
		self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $credential['certificateSha256']);
		self::assertStringNotContainsString('BEGIN CERTIFICATE', json_encode($selection, JSON_THROW_ON_ERROR));
	}

	public function testGuestWithoutTokenCannotDiscoverOrCallProvider(): void {
		[$lab, $transport] = $this->laboratory();
		$this->validator->expects($this->never())->method('validateCredentials');
		$this->expectException(\DomainException::class);
		try {
			$this->selection->discoverSynthetic(self::REQUEST_UUID, 'email', self::EMAIL,
				'', $this->record->id, $lab);
		} finally {
			self::assertSame(0, $transport->calls);
		}
	}

	public function testDisabledIntegrationCannotDiscoverCredentials(): void {
		[$lab, $transport] = $this->laboratory();
		$this->record = $this->store->setEnabled($this->record->id, $this->record->revision, false);
		$this->expectException(\DomainException::class);
		try {
			$this->selection->discoverSynthetic(self::REQUEST_UUID, 'email', self::EMAIL,
				'verified-token', $this->record->id, $lab);
		} finally {
			self::assertSame(0, $transport->calls);
		}
	}

	public function testAdministrationTokenCannotBeUsedAsSignerConsent(): void {
		[$lab, $transport] = $this->laboratory();
		$this->record = $this->store->update($this->record->id, $this->record->revision,
			$this->record->name, ['baseUrl' => self::LAB_URL], ['accessToken' => 'admin-token-is-not-consent']);
		$this->expectException(\DomainException::class);
		try {
			$this->selection->discoverSynthetic(self::REQUEST_UUID, 'email', self::EMAIL,
				'verified-token', $this->record->id, $lab);
		} finally {
			self::assertSame(0, $transport->calls);
		}
	}

	public function testOtherLaboratoryEndpointCannotReplaceConfiguredIntegration(): void {
		[$lab, $transport] = $this->laboratory();
		$this->record = $this->store->update($this->record->id, $this->record->revision,
			$this->record->name, ['baseUrl' => 'http://localhost:16400']);
		$this->expectException(\DomainException::class);
		try {
			$this->selection->discoverSynthetic(self::REQUEST_UUID, 'email', self::EMAIL,
				'verified-token', $this->record->id, $lab);
		} finally {
			self::assertSame(0, $transport->calls);
		}
	}

	public function testFeatureDisabledPreventsSignerDiscoveryBeforeRemoteCall(): void {
		[$lab, $transport] = $this->laboratory();
		$this->enabled = false;
		$this->expectException(\DomainException::class);
		try {
			$this->selection->discoverSynthetic(self::REQUEST_UUID, 'email', self::EMAIL,
				'verified-token', $this->record->id, $lab);
		} finally {
			self::assertSame(0, $transport->calls);
		}
	}
}
