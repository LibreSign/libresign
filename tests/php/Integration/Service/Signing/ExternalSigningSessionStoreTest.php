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
				foreach (['libresign_ext_sign_private', 'libresign_ext_sign_audit'] as $table) {
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
	$store->advance($id, 'synthetic-user', (int)$current['revision'],
		\OCA\Libresign\Service\Signing\ExternalSigningSessionState::SIGNING_PENDING,
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
					if (is_resource($pipe)) { fclose($pipe); }
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
		$row = $this->advance($row, ExternalSigningSessionState::SIGNING_PENDING);
		try {
			$this->advance($stale, ExternalSigningSessionState::SIGNING_PENDING);
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
		$row = $this->advance($row, ExternalSigningSessionState::SIGNING_PENDING);
		$row = $this->sessions->receiveSignature((string)$row['id'], 'synthetic-user', (int)$row['revision'],
			'synthetic-raw-rsa-signature', 'file-123-etag-456', 'synthetic-source-pdf', $this->certificate);
		$this->assertSame('synthetic-raw-rsa-signature', $this->sessions->readSignature((string)$row['id'], 'synthetic-user'));
		$before = $row;
		$row = $this->advance($row, ExternalSigningSessionState::COMPLETING);
		try {
			$this->advance($before, ExternalSigningSessionState::COMPLETING);
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

	public function testIntegrationRevisionChangesInvalidateSession(): void {
		$integration = $this->integration();
		$row = $this->create($integration);
		$this->integrations->setEnabled($integration->id, $integration->revision, false);
		$this->expectException(\DomainException::class);
		$this->sessions->resume((string)$row['id'], 'synthetic-user', 'file-123-etag-456', 'synthetic-source-pdf', $this->certificate);
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
		$row = $this->advance($row, ExternalSigningSessionState::SIGNING_PENDING);
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
