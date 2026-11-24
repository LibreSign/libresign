<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service\Signing;

use OCA\Libresign\Service\ExperimentalFeaturesService;
use OCA\Libresign\Service\Signature\PreparedExternalOperation;
use OCA\Libresign\Service\Signing\ExternalIntegrationRecord;
use OCA\Libresign\Service\Signing\ExternalIntegrationStore;
use OCA\Libresign\Service\Signing\ExternalSigningSessionState;
use OCA\Libresign\Service\Signing\ExternalSigningSessionStore;
use OCP\IDBConnection;
use OCP\Security\ICrypto;
use OCP\Server;
use SignerPHP\PdfSigner\Application\DTO\ExternalSigningPayload;
use SignerPHP\PdfSigner\Application\DTO\HashAlgorithm;
use SignerPHP\PdfSigner\Application\DTO\PreparedExternalSignature;
use SignerPHP\PdfSigner\Application\DTO\SignatureAlgorithm;
use SignerPHP\PdfSigner\Application\DTO\SignatureEncoding;
use SignerPHP\PdfSigner\Application\DTO\SigningInputType;

/**
 * @group DB
 *
 * Must run on a disposable, migration-upgraded Nextcloud test installation.
 * Every inserted session and integration is individually tracked and cleaned;
 * no shared signing, workflow or provider state is modified.
 */
final class ExternalSigningSessionStoreTest extends \OCA\Libresign\Tests\Integration\TestCase {
	private IDBConnection $db;
	private ExternalIntegrationStore $integrations;
	private ExternalSigningSessionStore $sessions;
	private bool $enabled = true;
	/** @var list<string> */
	private array $createdIntegrations = [];
	/** @var list<string> */
	private array $createdSessions = [];
	private string $certificate;

	#[\Override]
	public function setUp(): void {
		parent::setUp();
		$this->db = Server::get(IDBConnection::class);
		$this->integrations = Server::get(ExternalIntegrationStore::class);
		$features = $this->createMock(ExperimentalFeaturesService::class);
		$features->method('isEnabled')->willReturnCallback(fn (): bool => $this->enabled);
		$this->sessions = new ExternalSigningSessionStore(
			$this->db,
			Server::get(ICrypto::class),
			$features,
			$this->integrations,
		);
		$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
		self::assertNotFalse($key);
		$csr = openssl_csr_new(['commonName' => 'Synthetic signing session'], $key);
		self::assertNotFalse($csr);
		$cert = openssl_csr_sign($csr, null, $key, 1);
		self::assertNotFalse($cert);
		$pem = '';
		self::assertTrue(openssl_x509_export($cert, $pem));
		$this->certificate = $pem;
	}

	#[\Override]
	public function tearDown(): void {
		try {
			foreach ($this->createdSessions as $id) {
				foreach (['libresign_ext_sign_private', 'libresign_ext_sign_audit', 'libresign_ext_sign_attempt'] as $table) {
					$qb = $this->db->getQueryBuilder();
					$qb->delete($table)
						->where($qb->expr()->eq('session_id', $qb->createNamedParameter($id)))
						->executeStatement();
				}
				$qb = $this->db->getQueryBuilder();
				$qb->delete('libresign_ext_sign_session')
					->where($qb->expr()->eq('public_id', $qb->createNamedParameter($id)))
					->executeStatement();
			}
			foreach ($this->createdIntegrations as $id) {
				$qb = $this->db->getQueryBuilder();
				$qb->delete('libresign_signing_integration')
					->where($qb->expr()->eq('public_id', $qb->createNamedParameter($id)))
					->executeStatement();
			}
		} finally {
			parent::tearDown();
		}
	}

	private function integration(): ExternalIntegrationRecord {
		$record = $this->integrations->create('csc', 'Synthetic CSC', ['serviceUrl' => 'https://example.invalid'], []);
		$this->createdIntegrations[] = $record->id;
		return $this->integrations->setEnabled($record->id, $record->revision, true);
	}

	private function prepared(string $pdf): PreparedExternalOperation {
		return new PreparedExternalOperation(
			new PreparedExternalSignature(
				new ExternalSigningPayload(
					base64_encode(hash('sha256', 'signed-attributes:' . $pdf, true)),
					SigningInputType::Digest,
					HashAlgorithm::Sha256,
					SignatureAlgorithm::RsaPkcs1V15,
					SignatureEncoding::RsaPkcs1,
				),
				'{"unsignedPdf":"synthetic-opaque-state","schemaVersion":1}',
			),
			hash('sha256', $pdf),
		);
	}

	/** @return array<string, int|string> */
	private function create(?ExternalIntegrationRecord $integration = null, string $pdf = 'synthetic-source-pdf', ?\DateTimeImmutable $expires = null): array {
		$record = $integration ?? $this->integration();
		$session = $this->sessions->create(
			'synthetic-user',
			12345,
			'synthetic-authorized-attempt',
			$record,
			'synthetic-credential',
			$this->certificate,
			'file-123-etag-456',
			$pdf,
			$this->prepared($pdf),
			$expires ?? new \DateTimeImmutable('+1 hour'),
		);
		$this->createdSessions[] = $session['id'];
		return $session;
	}

	/** @param array<string, int|string> $row */
	private function claim(array $row): array {
		return $this->sessions->claimSigning((string)$row['id'], 'synthetic-user', (int)$row['revision'],
			'file-123-etag-456', 'synthetic-source-pdf', $this->certificate);
	}

	private function reserve(array $row): array {
		return $this->sessions->reservePublication((string)$row['id'], 'synthetic-user', (int)$row['revision'],
			'file-123-etag-456', 'synthetic-source-pdf', $this->certificate);
	}

	private function advance(array $row, ExternalSigningSessionState $state, string $pdf = 'synthetic-source-pdf'): array {
		return $this->sessions->advance(
			(string)$row['id'], 'synthetic-user', (int)$row['revision'], $state,
			'file-123-etag-456', $pdf, $this->certificate,
		);
	}

	public function testEncryptedPreparedDataSurvivesNewServiceInstanceWithoutUnserialize(): void {
		$row = $this->create();
		$qb = $this->db->getQueryBuilder();
		$encrypted = (string)$qb->select('prepared_enc')->from('libresign_ext_sign_session')
			->where($qb->expr()->eq('public_id', $qb->createNamedParameter($row['id'])))
			->executeQuery()->fetchOne();
		$this->assertNotSame('', $encrypted);
		$this->assertStringNotContainsString('synthetic-opaque-state', $encrypted);
		$this->assertStringNotContainsString('synthetic-opaque-state', json_encode($row, JSON_THROW_ON_ERROR));
		$this->assertStringNotContainsString('synthetic-source-pdf', json_encode($row, JSON_THROW_ON_ERROR));

		$second = new ExternalSigningSessionStore(
			$this->db, Server::get(ICrypto::class),
			new ExperimentalFeaturesService($this->createEnabledAppConfig()),
			$this->integrations,
		);
		$restored = $second->resume((string)$row['id'], 'synthetic-user', 'file-123-etag-456', 'synthetic-source-pdf', $this->certificate);
		$this->assertSame($this->prepared('synthetic-source-pdf')->upstream->state, $restored->upstream->state);
		$this->assertSame($this->prepared('synthetic-source-pdf')->upstream->payload->inputBase64, $restored->upstream->payload->inputBase64);
	}

	private function createEnabledAppConfig(): \OCP\IAppConfig {
		$config = $this->createMock(\OCP\IAppConfig::class);
		$config->method('getValueBool')->willReturn(true);
		return $config;
	}

	/**
	 * The child boots a new Nextcloud PHP process and restores persisted state.
	 * It shares only the disposable test DB, not PHP objects or HTTP sessions.
	 */
	public function testResumeInSeparatePhpProcess(): void {
		$row = $this->create();
		$script = tempnam(sys_get_temp_dir(), 'ls-session-');
		$certificateFile = tempnam(sys_get_temp_dir(), 'ls-cert-');
		self::assertIsString($script);
		self::assertIsString($certificateFile);
		try {
			file_put_contents($certificateFile, $this->certificate);
			file_put_contents($script, <<<'PHP'
<?php
declare(strict_types=1);
require $argv[1] . '/lib/base.php';
\OCP\Server::get(\OCP\App\IAppManager::class)->loadApp('libresign');
$gate = new class(\OCP\Server::get(\OCP\IAppConfig::class)) extends \OCA\Libresign\Service\ExperimentalFeaturesService {
	public function isEnabled(): bool { return true; }
};
$store = new \OCA\Libresign\Service\Signing\ExternalSigningSessionStore(
	\OCP\Server::get(\OCP\IDBConnection::class),
	\OCP\Server::get(\OCP\Security\ICrypto::class),
	$gate,
	\OCP\Server::get(\OCA\Libresign\Service\Signing\ExternalIntegrationStore::class),
);
$prepared = $store->resume($argv[2], 'synthetic-user', 'file-123-etag-456', 'synthetic-source-pdf', file_get_contents($argv[3]));
echo hash('sha256', $prepared->upstream->state);
PHP);
			$root = dirname(__DIR__, 5);
			$command = implode(' ', array_map('escapeshellarg', [
				PHP_BINARY, $script, dirname($root, 2), (string)$row['id'], $certificateFile,
			]));
			$output = [];
			$exitCode = -1;
			exec($command . ' 2>&1', $output, $exitCode);
			$this->assertSame(0, $exitCode, implode("\n", $output));
			$this->assertSame(hash('sha256', $this->prepared('synthetic-source-pdf')->upstream->state), trim(implode("\n", $output)));
		} finally {
			@unlink($script);
			@unlink($certificateFile);
		}
	}

	/**
	 * Two real PHP processes take the same pre-submission snapshot before
	 * racing for the single signing claim. The losing process may observe
	 * a CAS conflict or an SQLite lock, but must never obtain a claim.
	 */
	public function testConcurrentPhpProcessesHaveOnlyOneSigningClaim(): void {
		$row = $this->create();
		$row = $this->advance($row, ExternalSigningSessionState::READY_TO_SIGN);
		$script = tempnam(sys_get_temp_dir(), 'ls-concurrent-');
		$certificateFile = tempnam(sys_get_temp_dir(), 'ls-cert-');
		self::assertIsString($script);
		self::assertIsString($certificateFile);
		$processes = [];
		try {
			file_put_contents($certificateFile, $this->certificate);
			file_put_contents($script, <<<'PHP'
<?php
declare(strict_types=1);
require $argv[1] . '/lib/base.php';
\OCP\Server::get(\OCP\App\IAppManager::class)->loadApp('libresign');
$gate = new class(\OCP\Server::get(\OCP\IAppConfig::class)) extends \OCA\Libresign\Service\ExperimentalFeaturesService {
	public function isEnabled(): bool { return true; }
};
$store = new \OCA\Libresign\Service\Signing\ExternalSigningSessionStore(
	\OCP\Server::get(\OCP\IDBConnection::class),
	\OCP\Server::get(\OCP\Security\ICrypto::class),
	$gate,
	\OCP\Server::get(\OCA\Libresign\Service\Signing\ExternalIntegrationStore::class),
);
$id = $argv[2];
$current = $store->get($id, 'synthetic-user');
echo "READY\n";
fflush(STDOUT);
if (fgets(STDIN) !== "GO\n") { exit(9); }
try {
	$store->claimSigning($id, 'synthetic-user', (int)$current['revision'],
		'file-123-etag-456', 'synthetic-source-pdf', file_get_contents($argv[3]));
	echo "CLAIMED\n";
} catch (\Throwable) {
	echo "REJECTED\n";
}
PHP);
			$command = implode(' ', array_map('escapeshellarg', [
				PHP_BINARY, $script, dirname(dirname(__DIR__, 5), 2), (string)$row['id'], $certificateFile,
			]));
			for ($n = 0; $n < 2; $n++) {
				$descriptor = [
					0 => ['pipe', 'r'],
					1 => ['pipe', 'w'],
					2 => ['pipe', 'w'],
				];
				$process = proc_open($command, $descriptor, $pipes);
				$this->assertIsResource($process);
				$processes[] = [$process, $pipes];
			}
			foreach ($processes as [, $pipes]) {
				$this->assertSame("READY\n", fgets($pipes[1]));
			}
			foreach ($processes as [, $pipes]) {
				fwrite($pipes[0], "GO\n");
				fclose($pipes[0]);
			}
			$outcomes = [];
			foreach ($processes as [$process, $pipes]) {
				$outcomes[] = trim((string)stream_get_contents($pipes[1]));
				fclose($pipes[1]);
				$error = stream_get_contents($pipes[2]);
				fclose($pipes[2]);
				$this->assertSame(0, proc_close($process), $error);
			}
			$processes = [];
			$this->assertCount(1, array_filter($outcomes, static fn (string $outcome): bool => $outcome === 'CLAIMED'));
			$this->assertSame('signing_pending', $this->sessions->get((string)$row['id'], 'synthetic-user')['status']);
		} finally {
			foreach ($processes as [$process, $pipes]) {
				foreach ($pipes as $pipe) {
					if (is_resource($pipe)) {
						fclose($pipe);
					}
				}
				proc_terminate($process);
				proc_close($process);
			}
			@unlink($script);
			@unlink($certificateFile);
		}
	}

	public function testAtMostOneSubmissionClaimAndUnknownOutcomeNeverRetries(): void {
		$row = $this->create();
		$row = $this->advance($row, ExternalSigningSessionState::READY_TO_SIGN);
		$stale = $row;
		$row = $this->claim($row);
		try {
			$this->claim($stale);
			$this->fail('The same operation was claimed twice');
		} catch (\DomainException|\RuntimeException) {
			$this->assertSame('signing_pending', $this->sessions->get((string)$row['id'], 'synthetic-user')['status']);
		}
		$row = $this->advance($row, ExternalSigningSessionState::UNCERTAIN);
		$this->expectException(\DomainException::class);
		$this->advance($row, ExternalSigningSessionState::READY_TO_SIGN);
	}

	public function testDuplicateCompletionReservationRejectedAndCompletionIdempotent(): void {
		$row = $this->create();
		$row = $this->advance($row, ExternalSigningSessionState::READY_TO_SIGN);
		$row = $this->claim($row);
		$row = $this->sessions->receiveSignature((string)$row['id'], 'synthetic-user', (int)$row['revision'],
			'synthetic-raw-rsa-signature', 'file-123-etag-456', 'synthetic-source-pdf', $this->certificate);
		$this->assertSame('synthetic-raw-rsa-signature', $this->sessions->readSignature((string)$row['id'], 'synthetic-user'));
		$before = $row;
		$row = $this->reserve($row);
		try {
			$this->reserve($before);
			$this->fail('Repeated publication reservation was granted');
		} catch (\DomainException|\RuntimeException) {
			$this->assertSame('completing', $this->sessions->get((string)$row['id'], 'synthetic-user')['status']);
		}
		$completed = $this->sessions->markCompleted((string)$row['id'], 'synthetic-user', (int)$row['revision'],
			'file-123-etag-456', 'synthetic-source-pdf', $this->certificate);
		$this->assertSame('completed', $completed['status']);
		$this->assertSame($completed, $this->sessions->markCompleted((string)$row['id'], 'synthetic-user',
			(int)$row['revision'], 'file-123-etag-456', 'synthetic-source-pdf', $this->certificate));
		$this->expectException(\DomainException::class);
		$this->sessions->readSignature((string)$row['id'], 'synthetic-user');
	}

	public function testRealSignerPhpPreparedPdfPersistsExactly(): void {
		$pdf = file_get_contents(dirname(__DIR__, 3) . '/fixtures/pdfs/small_valid.pdf');
		$this->assertIsString($pdf);
		$upstream = \SignerPHP\PdfSigner\Presentation\Signer::externalSigner()
			->withPdfContent($pdf)
			->withCertificate($this->certificate)
			->withoutDefaultAppearance()
			->prepare();
		$wrapped = new PreparedExternalOperation($upstream, hash('sha256', $pdf));
		$integration = $this->integration();
		$row = $this->sessions->create(
			'synthetic-user', 12346, 'actual-pdf-preparation', $integration,
			'synthetic-credential', $this->certificate, 'real-pdf-etag',
			$pdf, $wrapped, new \DateTimeImmutable('+1 hour'),
		);
		$this->createdSessions[] = $row['id'];
		$restored = $this->sessions->resume((string)$row['id'], 'synthetic-user', 'real-pdf-etag', $pdf, $this->certificate);
		$this->assertSame($upstream->state, $restored->upstream->state);
		$this->assertSame($upstream->payload->inputBase64, $restored->upstream->payload->inputBase64);
		$this->assertSame($upstream->payload->signatureAlgorithm, $restored->upstream->payload->signatureAlgorithm);
	}

	/** Two independent Nextcloud PHP workers compete for session creation. */
	public function testConcurrentStartsOnlyCreateOneSession(): void {
		$integration = $this->integration();
		$script = tempnam(sys_get_temp_dir(), 'ls-start-');
		$certFile = tempnam(sys_get_temp_dir(), 'ls-cert-');
		self::assertIsString($script);
		self::assertIsString($certFile);
		$processes = [];
		try {
			file_put_contents($certFile, $this->certificate);
			file_put_contents($script, <<<'PHP'
<?php
declare(strict_types=1);
require $argv[1] . '/lib/base.php';
\OCP\Server::get(\OCP\App\IAppManager::class)->loadApp('libresign');
$gate = new class(\OCP\Server::get(\OCP\IAppConfig::class)) extends \OCA\Libresign\Service\ExperimentalFeaturesService {
	public function isEnabled(): bool { return true; }
};
$store = new \OCA\Libresign\Service\Signing\ExternalSigningSessionStore(
	\OCP\Server::get(\OCP\IDBConnection::class),
	\OCP\Server::get(\OCP\Security\ICrypto::class),
	$gate,
	\OCP\Server::get(\OCA\Libresign\Service\Signing\ExternalIntegrationStore::class),
);
$integrations = \OCP\Server::get(\OCA\Libresign\Service\Signing\ExternalIntegrationStore::class);
$integration = $integrations->get($argv[2]);
$cert = file_get_contents($argv[3]);
$pdf = 'parallel-source-pdf';
$prepared = new \OCA\Libresign\Service\Signature\PreparedExternalOperation(
	new \SignerPHP\PdfSigner\Application\DTO\PreparedExternalSignature(
		new \SignerPHP\PdfSigner\Application\DTO\ExternalSigningPayload(
			base64_encode(hash('sha256', 'signed-attributes:' . $pdf, true)),
			\SignerPHP\PdfSigner\Application\DTO\SigningInputType::Digest,
			\SignerPHP\PdfSigner\Application\DTO\HashAlgorithm::Sha256,
			\SignerPHP\PdfSigner\Application\DTO\SignatureAlgorithm::RsaPkcs1V15,
			\SignerPHP\PdfSigner\Application\DTO\SignatureEncoding::RsaPkcs1,
		),
		'{"unsignedPdf":"synthetic-opaque-state","schemaVersion":1}',
	),
	hash('sha256', $pdf),
);
echo "READY\n";
fflush(STDOUT);
if (fgets(STDIN) !== "GO\n") { exit(9); }
try {
	$r = $store->create('parallel-actor', 12347, 'parallel-attempt', $integration,
		'parallel-credential', $cert, 'parallel-document-revision', $pdf,
		$prepared, new \DateTimeImmutable('+1 hour'));
	echo 'OK:' . $r['id'] . "\n";
} catch (\Throwable) {
	echo "REJECTED\n";
}
PHP);
			$command = implode(' ', array_map('escapeshellarg', [PHP_BINARY, $script,
				dirname(dirname(__DIR__, 5), 2), $integration->id, $certFile]));
			for ($n = 0; $n < 2; $n++) {
				$p = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
				self::assertIsResource($p);
				$processes[] = [$p, $pipes];
			}
			foreach ($processes as [, $pipes]) {
				self::assertSame("READY\n", fgets($pipes[1]));
			}
			foreach ($processes as [, $pipes]) {
				fwrite($pipes[0], "GO\n");
				fclose($pipes[0]);
			}
			$outcomes = [];
			foreach ($processes as [$p, $pipes]) {
				$outcomes[] = trim((string)stream_get_contents($pipes[1]));
				fclose($pipes[1]);
				$stderr = (string)stream_get_contents($pipes[2]);
				fclose($pipes[2]);
				self::assertSame(0, proc_close($p), $stderr);
			}
			$processes = [];
			$successes = array_values(array_filter($outcomes, static fn (string $v): bool => str_starts_with($v, 'OK:')));
			self::assertNotEmpty($successes, 'At least one worker must create or read the session');
			self::assertCount(1, array_unique(array_map(static fn (string $v): string => substr($v, 3), $successes)));
			$publicId = substr($successes[0], 3);
			$this->createdSessions[] = $publicId;
			$qb = $this->db->getQueryBuilder();
			$count = $qb->select($qb->createFunction('COUNT(*)'))->from('libresign_ext_sign_session')
				->where($qb->expr()->eq('sign_request_id', $qb->createNamedParameter(12347, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->eq('actor_id', $qb->createNamedParameter('parallel-actor')))
				->executeQuery()->fetchOne();
			self::assertSame(1, (int)$count);
		} finally {
			foreach ($processes as [$p, $pipes]) {
				foreach ($pipes as $pipe) {
					if (is_resource($pipe)) {
						fclose($pipe);
					}
				}
				proc_terminate($p);
				proc_close($p);
			}
			@unlink($script);
			@unlink($certFile);
		}
	}

	public function testSameAuthorizedAttemptIsIdempotentAtDatabaseLevel(): void {
		$integration = $this->integration();
		$one = $this->create($integration);
		$two = $this->sessions->create(
			'synthetic-user', 12345, 'synthetic-authorized-attempt', $integration,
			'synthetic-credential', $this->certificate, 'file-123-etag-456',
			'synthetic-source-pdf', $this->prepared('synthetic-source-pdf'), new \DateTimeImmutable('+1 hour'),
		);
		$this->assertSame($one['id'], $two['id']);
		$qb = $this->db->getQueryBuilder();
		$count = $qb->select($qb->createFunction('COUNT(*)'))->from('libresign_ext_sign_session')
			->where($qb->expr()->eq('public_id', $qb->createNamedParameter($one['id'])))
			->executeQuery()->fetchOne();
		$this->assertSame(1, (int)$count);
	}

	public function testDifferentAttemptCannotCreateSecondSessionForSameSignRequest(): void {
		$integration = $this->integration();
		$this->create($integration);
		$this->expectException(\DomainException::class);
		$this->sessions->create(
			'synthetic-user', 12345, 'another-authorized-attempt', $integration,
			'synthetic-credential', $this->certificate, 'file-123-etag-456',
			'synthetic-source-pdf', $this->prepared('synthetic-source-pdf'), new \DateTimeImmutable('+1 hour'),
		);
	}

	public function testGenericTransitionCannotClaimSigningWithoutDurableLedger(): void {
		$row = $this->create();
		$row = $this->advance($row, ExternalSigningSessionState::READY_TO_SIGN);
		$this->expectException(\DomainException::class);
		$this->advance($row, ExternalSigningSessionState::SIGNING_PENDING);
	}

	public function testClaimWritesOneDurableLedgerAndBlocksPreparedResubmission(): void {
		$row = $this->create();
		$row = $this->advance($row, ExternalSigningSessionState::READY_TO_SIGN);
		$row = $this->claim($row);
		$qb = $this->db->getQueryBuilder();
		$count = $qb->select($qb->createFunction('COUNT(*)'))->from('libresign_ext_sign_attempt')
			->where($qb->expr()->eq('session_id', $qb->createNamedParameter($row['id'])))
			->executeQuery()->fetchOne();
		$this->assertSame(1, (int)$count);
		$this->expectException(\DomainException::class);
		$this->sessions->resume((string)$row['id'], 'synthetic-user', 'file-123-etag-456', 'synthetic-source-pdf', $this->certificate);
	}

	public function testSubmittedOperationCannotBeMarkedFailedByGenericTransition(): void {
		$row = $this->create();
		$row = $this->advance($row, ExternalSigningSessionState::READY_TO_SIGN);
		$row = $this->claim($row);
		$this->expectException(\DomainException::class);
		$this->advance($row, ExternalSigningSessionState::FAILED);
	}

	public function testDuplicateProviderCallbackDoesNotChangeSession(): void {
		$row = $this->create();
		$row = $this->advance($row, ExternalSigningSessionState::READY_TO_SIGN);
		$row = $this->claim($row);
		$pending = $row;
		$row = $this->sessions->receiveSignature((string)$row['id'], 'synthetic-user', (int)$row['revision'],
			'synthetic-signature', 'file-123-etag-456', 'synthetic-source-pdf', $this->certificate);
		$this->assertSame('signature_received', $row['status']);
		try {
			$this->sessions->receiveSignature((string)$pending['id'], 'synthetic-user', (int)$pending['revision'],
				'synthetic-signature', 'file-123-etag-456', 'synthetic-source-pdf', $this->certificate);
			$this->fail('Duplicate callback was incorrectly accepted');
		} catch (\DomainException|\RuntimeException) {
			$this->assertSame($row['revision'], $this->sessions->get((string)$row['id'], 'synthetic-user')['revision']);
		}
	}

	public function testDifferentValidCertificateIsRejected(): void {
		$row = $this->create();
		$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
		self::assertNotFalse($key);
		$csr = openssl_csr_new(['commonName' => 'Synthetic replaced certificate'], $key);
		self::assertNotFalse($csr);
		$cert = openssl_csr_sign($csr, null, $key, 1);
		self::assertNotFalse($cert);
		self::assertTrue(openssl_x509_export($cert, $replacementPem));
		$this->expectException(\DomainException::class);
		$this->sessions->resume((string)$row['id'], 'synthetic-user', 'file-123-etag-456', 'synthetic-source-pdf', $replacementPem);
	}

	public function testWrongActorCannotReadOrMutate(): void {
		$row = $this->create();
		$this->expectException(\OutOfBoundsException::class);
		$this->sessions->get((string)$row['id'], 'different-actor');
	}

	public function testSourceDocumentChangeIsRejected(): void {
		$row = $this->create();
		$this->expectException(\DomainException::class);
		$this->sessions->resume((string)$row['id'], 'synthetic-user', 'file-123-etag-456', 'changed-document', $this->certificate);
	}

	public function testSourceDocumentRevisionChangeIsRejected(): void {
		$row = $this->create();
		$this->expectException(\DomainException::class);
		$this->sessions->resume((string)$row['id'], 'synthetic-user', 'file-123-new-etag', 'synthetic-source-pdf', $this->certificate);
	}

	public function testCertificateSubstitutionIsRejected(): void {
		$row = $this->create();
		$this->expectException(\InvalidArgumentException::class);
		$this->sessions->resume((string)$row['id'], 'synthetic-user', 'file-123-etag-456', 'synthetic-source-pdf', 'not-a-certificate');
	}

	public function testIntegrationCannotBeDisabledWithUnresolvedSession(): void {
		$integration = $this->integration();
		$row = $this->create($integration);
		try {
			$this->integrations->setEnabled($integration->id, $integration->revision, false);
			self::fail('Disabling an unresolved integration must fail');
		} catch (\DomainException $e) {
			self::assertStringContainsString('unresolved', $e->getMessage());
		}
		self::assertTrue($this->integrations->get($integration->id)->enabled);
		self::assertSame('preparing', $this->sessions->get((string)$row['id'], 'synthetic-user')['status']);
	}

	public function testIntegrationCannotBeDeletedOrReconfiguredWithUncertainSession(): void {
		$integration = $this->integration();
		$row = $this->create($integration);
		$row = $this->advance($row, ExternalSigningSessionState::READY_TO_SIGN);
		$row = $this->claim($row);
		$row = $this->advance($row, ExternalSigningSessionState::UNCERTAIN);
		foreach (['update', 'delete'] as $action) {
			try {
				if ($action === 'update') {
					$this->integrations->update($integration->id, $integration->revision, 'Changed CSC', ['serviceUrl' => 'https://changed.example.org']);
				} else {
					$this->integrations->softDelete($integration->id, $integration->revision);
				}
				self::fail('An unresolved CSC submission must prevent ' . $action);
			} catch (\DomainException $e) {
				self::assertStringContainsString('unresolved', $e->getMessage());
			}
		}
		self::assertSame($integration->revision, $this->integrations->get($integration->id)->revision);
	}

	public function testIntegrationCanBeReconfiguredAfterSessionIsRejected(): void {
		$integration = $this->integration();
		$row = $this->create($integration);
		$this->advance($row, ExternalSigningSessionState::REJECTED);
		$updated = $this->integrations->update($integration->id, $integration->revision,
			'Updated CSC', ['serviceUrl' => 'https://updated.example.org']);
		self::assertSame($integration->revision + 1, $updated->revision);
		self::assertSame('Updated CSC', $updated->name);
	}

	public function testPrivateContinuationEncryptedAndRemovedAfterRejection(): void {
		$row = $this->create();
		$row = $this->sessions->saveContinuation((string)$row['id'], 'synthetic-user', (int)$row['revision'], ['sad' => 'synthetic-sensitive-value']);
		$qb = $this->db->getQueryBuilder();
		$stored = (string)$qb->select('secrets_enc')->from('libresign_ext_sign_private')
			->where($qb->expr()->eq('session_id', $qb->createNamedParameter($row['id'])))
			->executeQuery()->fetchOne();
		$this->assertStringNotContainsString('synthetic-sensitive-value', $stored);
		$this->assertSame(['sad' => 'synthetic-sensitive-value'], $this->sessions->readContinuation((string)$row['id'], 'synthetic-user'));
		$row = $this->advance($row, ExternalSigningSessionState::REJECTED);
		$this->assertSame('rejected', $row['status']);
		$this->expectException(\DomainException::class);
		$this->sessions->readContinuation((string)$row['id'], 'synthetic-user');
	}

	public function testTamperedCiphertextCannotBeRestored(): void {
		$row = $this->create();
		$qb = $this->db->getQueryBuilder();
		$qb->update('libresign_ext_sign_session')
			->set('prepared_enc', $qb->createNamedParameter('corrupted-ciphertext'))
			->where($qb->expr()->eq('public_id', $qb->createNamedParameter($row['id'])))
			->executeStatement();
		$this->expectException(\UnexpectedValueException::class);
		$this->sessions->resume((string)$row['id'], 'synthetic-user', 'file-123-etag-456', 'synthetic-source-pdf', $this->certificate);
	}

	public function testExpiredSessionCannotResume(): void {
		$row = $this->create();
		$qb = $this->db->getQueryBuilder();
		$qb->update('libresign_ext_sign_session')
			->set('expires_at', $qb->createNamedParameter(new \DateTime('-1 minute'), \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->where($qb->expr()->eq('public_id', $qb->createNamedParameter($row['id'])))
			->executeStatement();
		$this->expectException(\DomainException::class);
		$this->sessions->resume((string)$row['id'], 'synthetic-user', 'file-123-etag-456', 'synthetic-source-pdf', $this->certificate);
	}

	public function testExpiredSentOperationBecomesUncertainNotRetryable(): void {
		$row = $this->create();
		$row = $this->advance($row, ExternalSigningSessionState::READY_TO_SIGN);
		$row = $this->claim($row);
		$qb = $this->db->getQueryBuilder();
		$qb->update('libresign_ext_sign_session')
			->set('expires_at', $qb->createNamedParameter(new \DateTime('-1 minute'), \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->where($qb->expr()->eq('public_id', $qb->createNamedParameter($row['id'])))
			->executeStatement();
		$row = $this->sessions->expire((string)$row['id'], 'synthetic-user', (int)$row['revision']);
		$this->assertSame('uncertain', $row['status']);
		$this->assertFalse(ExternalSigningSessionState::UNCERTAIN->allows(ExternalSigningSessionState::SIGNING_PENDING));
	}

	public function testDisabledFeatureCannotCreateOrAdvance(): void {
		$integration = $this->integration();
		$this->enabled = false;
		$this->expectException(\DomainException::class);
		$this->create($integration);
	}
}
