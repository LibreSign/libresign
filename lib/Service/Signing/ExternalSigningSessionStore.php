<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing;

use OCA\Libresign\Service\ExperimentalFeaturesService;
use OCA\Libresign\Service\Signature\PreparedExternalOperation;
use OCP\AppFramework\Db\TTransactional;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Security\ICrypto;

/**
 * Durable, server-authoritative signing state, not a public API or a provider.
 *
 * #9603 MUST authenticate the actor, authorize the SignRequest and obtain the
 * latest revision and PDF bytes from trusted storage before every operation.
 * A session ID is a lookup identifier, NEVER a bearer authorization token.
 * #9603 also owns publication; this service only reserves it exactly once.
 *
 * SIGNING_PENDING is persisted BEFORE calling the private-key operation.
 * A crash in that window is indeterminate: it must never be replayed. There is
 * deliberately no method to return SIGNING_PENDING/UNCERTAIN to READY_TO_SIGN.
 */
final class ExternalSigningSessionStore {
	use TTransactional;
	private const TABLE = 'libresign_ext_sign_session';
	private const PRIVATE_TABLE = 'libresign_ext_sign_private';
	private const AUDIT_TABLE = 'libresign_ext_sign_audit';
	private const ATTEMPT_TABLE = 'libresign_ext_sign_attempt';
	// Fail closed instead of accidentally overflowing MySQL TEXT or exhausting memory.
	private const MAX_SECRET_JSON = 64 * 1024;

	public function __construct(
		private IDBConnection $db,
		private ICrypto $crypto,
		private ExperimentalFeaturesService $features,
		private ExternalIntegrationStore $integrations,
	) {
	}

	/**
	 * The caller has already authenticated/authorized $actorId against the
	 * SignRequest; $pdfBytes and $documentRevision come from trusted storage.
	 * An enabled integration and a public certificate known in advance are
	 * mandatory. The payload and opaque state are never taken from a client.
	 *
	 * @return array<string, int|string>
	 */
	public function create(
		string $actorId,
		int $signRequestId,
		string $attemptId,
		ExternalIntegrationRecord $integration,
		string $credentialId,
		string $certificatePem,
		string $documentRevision,
		#[\SensitiveParameter] string $pdfBytes,
		PreparedExternalOperation $prepared,
		\DateTimeImmutable $expiresAt,
	): array {
		$this->assertFeatureEnabled();
		if ($attemptId === '' || strlen($attemptId) > 128 || $signRequestId < 1 || $actorId === '' || strlen($actorId) > 255
			|| $credentialId === '' || strlen($credentialId) > 255
			|| $documentRevision === '' || strlen($documentRevision) > 128
			|| $expiresAt->getTimestamp() <= time()) {
			throw new \InvalidArgumentException('Invalid external signing session context');
		}
		$documentHash = hash('sha256', $pdfBytes);
		if (!hash_equals($documentHash, $prepared->preparationInputSha256)) {
			throw new \DomainException('Prepared payload does not match authoritative document');
		}
		$certificateHash = $this->certificateFingerprint($certificatePem);
		$encrypted = $this->protect(ExternalSigningPreparedStateCodec::encode($prepared));
		$id = bin2hex(random_bytes(16));
		$attemptKey = hash('sha256', $signRequestId . ':' . strlen($actorId) . ':' . $actorId . ':' . $attemptId);
		$slot = self::signerSlot($signRequestId, $actorId);
		$now = $this->now();
		try {
			$this->atomic(function () use ($integration, $id, $attemptKey, $slot, $actorId, $signRequestId, $credentialId, $certificateHash, $documentRevision, $documentHash, $encrypted, $expiresAt, $now): void {
				$this->integrations->lockIntegrationRow($integration->id, $integration->revision);
				$this->assertIntegration($integration->id, $integration->revision);
				$qb = $this->db->getQueryBuilder();
				$qb->insert(self::TABLE)->values([
					'public_id' => $qb->createNamedParameter($id),
					'attempt_key' => $qb->createNamedParameter($attemptKey),
					'signer_slot' => $qb->createNamedParameter($slot),
					'actor_id' => $qb->createNamedParameter($actorId),
					'sign_request_id' => $qb->createNamedParameter($signRequestId, IQueryBuilder::PARAM_INT),
					'integration_id' => $qb->createNamedParameter($integration->id),
					'integration_revision' => $qb->createNamedParameter($integration->revision, IQueryBuilder::PARAM_INT),
					'credential_id' => $qb->createNamedParameter($credentialId),
					'certificate_sha256' => $qb->createNamedParameter($certificateHash),
					'document_revision' => $qb->createNamedParameter($documentRevision),
					'document_sha256' => $qb->createNamedParameter($documentHash),
					'payload_version' => $qb->createNamedParameter(ExternalSigningPreparedStateCodec::FORMAT, IQueryBuilder::PARAM_INT),
					'prepared_enc' => $qb->createNamedParameter($encrypted),
					'status' => $qb->createNamedParameter(ExternalSigningSessionState::PREPARING->value),
					'revision' => $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT),
					'expires_at' => $qb->createNamedParameter(\DateTime::createFromImmutable($expiresAt), IQueryBuilder::PARAM_DATETIME_MUTABLE),
					'created_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_MUTABLE),
					'updated_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_MUTABLE),
				])->executeStatement();
				$qb = $this->db->getQueryBuilder();
				$qb->insert(self::PRIVATE_TABLE)->values([
					'session_id' => $qb->createNamedParameter($id),
					'secrets_enc' => $qb->createNamedParameter(''),
				])->executeStatement();
				$this->audit($id, $actorId, 'created');
			}, $this->db);
		} catch (\OCP\DB\Exception $e) {
			// Only uniqueness conflicts permit idempotent recovery.
			if ($e->getReason() !== \OCP\DB\Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}
			$existing = $this->findBySlot($slot, $actorId);
			if ($existing !== null) {
				if (!hash_equals((string)$existing['attempt_key'], $attemptKey)) {
					throw new \DomainException('Another signing attempt already occupies this SignRequest');
				}
				if ($existing['integration_id'] !== $integration->id || (int)$existing['integration_revision'] !== $integration->revision
					|| $existing['credential_id'] !== $credentialId) {
					throw new \DomainException('Idempotent signing attempt context changed');
				}
				$this->assertContext($existing, $documentRevision, $pdfBytes, $certificatePem);
				return $this->metadata($existing);
			}
			// A unique SignRequest constraint also rejects a second actor
			// (for example after switching from guest to Nextcloud login).
			// Do not reveal the existing actor/session identity to the caller.
			if ($this->findBySignRequestId($signRequestId) !== null) {
				throw new \DomainException('Another signing actor already occupies this SignRequest', 0, $e);
			}
			throw $e;
		}
		return $this->get($id, $actorId);
	}

	/**
	 * Internal conflict lookup only, never exposed as an API: a foreign
	 * actor must not discover another signer's credential or session ID.
	 *
	 * @return array<string, mixed>|null
	 */
	private function findBySignRequestId(int $signRequestId): ?array {
		$qb = $this->db->getQueryBuilder();
		$row = $qb->select('id')->from(self::TABLE)
			->where($qb->expr()->eq('sign_request_id',
				$qb->createNamedParameter($signRequestId, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1)->executeQuery()->fetchAssociative();
		return $row === false ? null : $row;
	}

	/**
	 * A stable, per-actor SignRequest slot permits safe session recovery on a
	 * subsequent request or PHP worker without client-supplied prepared state.
	 */
	private static function signerSlot(int $signRequestId, string $actorId): string {
		return hash('sha256', $signRequestId . ':' . strlen($actorId) . ':' . $actorId);
	}

	/** @return array<string, mixed>|null */
	private function findBySlot(string $slot, string $actorId): ?array {
		$qb = $this->db->getQueryBuilder();
		$row = $qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('signer_slot', $qb->createNamedParameter($slot)))
			->andWhere($qb->expr()->eq('actor_id', $qb->createNamedParameter($actorId)))
			->executeQuery()->fetchAssociative();
		return $row === false ? null : $row;
	}

	/**
	 * Public metadata read. Does not return protected payload/signature/SAD.
	 *
	 * @return array<string, int|string>
	 */
	public function get(string $id, string $actorId): array {
		$row = $this->load($id, $actorId);
		return $this->metadata($row);
	}

	/**
	 * Find the sole durable session for the server-authorized signer and
	 * SignRequest, including terminal or uncertain states. No secret material
	 * or trusted prepared state is returned. An API caller MUST first resolve
	 * the current authenticated or invited signer, authorize the SignRequest,
	 * then supply the server-derived actor ID. This method is NOT an access
	 * policy or permission to submit another private-key operation.
	 *
	 * @return array<string, int|string>|null
	 */
	public function findForSignRequest(int $signRequestId, string $actorId): ?array {
		$this->assertFeatureEnabled();
		$this->validateSignerContext($signRequestId, $actorId);
		$row = $this->findBySlot(self::signerSlot($signRequestId, $actorId), $actorId);
		return $row === null ? null : $this->metadata($row);
	}

	/**
	 * Read one session only if it belongs to the expected server-authorized
	 * actor AND SignRequest. A session ID is never a bearer access token.
	 *
	 * @return array<string, int|string>
	 */
	public function getForSignRequest(string $sessionId, int $signRequestId, string $actorId): array {
		$this->assertFeatureEnabled();
		$this->validateSignerContext($signRequestId, $actorId);
		$row = $this->load($sessionId, $actorId);
		if ((int)$row['sign_request_id'] !== $signRequestId) {
			throw new \OutOfBoundsException('Signing session not found');
		}
		return $this->metadata($row);
	}

	private function validateSignerContext(int $signRequestId, string $actorId): void {
		if ($signRequestId < 1 || $actorId === '' || strlen($actorId) > 255) {
			throw new \InvalidArgumentException('Invalid signing session context');
		}
	}

	/**
	 * Revalidate the *current* trusted document and certificate before
	 * accessing the prepared operation, not only at session creation.
	 */
	public function resume(
		string $id,
		string $actorId,
		string $documentRevision,
		#[\SensitiveParameter] string $currentPdfBytes,
		string $expectedCertificatePem,
	): PreparedExternalOperation {
		$this->assertFeatureEnabled();
		$row = $this->load($id, $actorId);
		$this->assertContext($row, $documentRevision, $currentPdfBytes, $expectedCertificatePem);
		if (in_array($row['status'], [
			ExternalSigningSessionState::SIGNING_PENDING->value,
			ExternalSigningSessionState::UNCERTAIN->value,
			ExternalSigningSessionState::COMPLETING->value,
			ExternalSigningSessionState::COMPLETED->value,
			ExternalSigningSessionState::REJECTED->value,
			ExternalSigningSessionState::EXPIRED->value,
			ExternalSigningSessionState::FAILED->value,
		], true)) {
			throw new \DomainException('Signing session is terminal');
		}
		return $this->restorePrepared((string)$row['prepared_enc']);
	}

	/** Only for #9603 PDF completion, never for a new remote key submission. */
	public function preparedForCompletion(
		string $id, string $actorId, string $documentRevision,
		#[\SensitiveParameter] string $currentPdfBytes, string $expectedCertificatePem,
	): PreparedExternalOperation {
		$this->assertFeatureEnabled();
		$row = $this->load($id, $actorId);
		$this->assertContext($row, $documentRevision, $currentPdfBytes, $expectedCertificatePem);
		if (!in_array($row['status'], [ExternalSigningSessionState::SIGNATURE_RECEIVED->value, ExternalSigningSessionState::COMPLETING->value], true)) {
			throw new \DomainException('Completion preparation unavailable');
		}
		return $this->restorePrepared((string)$row['prepared_enc']);
	}

	/**
	 * Compare-and-swap transition. The expected revision is mandatory.
	 * Every request, including duplicate callbacks, competes at DB level.
	 *
	 * @return array<string, int|string>
	 */
	public function advance(
		string $id,
		string $actorId,
		int $expectedRevision,
		ExternalSigningSessionState $next,
		string $documentRevision,
		#[\SensitiveParameter] string $currentPdfBytes,
		string $expectedCertificatePem,
	): array {
		$this->assertFeatureEnabled();
		$row = $this->load($id, $actorId);
		$this->assertContext($row, $documentRevision, $currentPdfBytes, $expectedCertificatePem);
		$previous = ExternalSigningSessionState::from((string)$row['status']);
		if (!$previous->allows($next)) {
			throw new \DomainException('Invalid external signing session transition');
		}
		// A submitted operation can only be resolved by explicit, authenticated
		// reconciliation; a network timeout is never proof of failure.
		if (in_array($previous, [ExternalSigningSessionState::SIGNING_PENDING, ExternalSigningSessionState::UNCERTAIN], true)
			&& in_array($next, [ExternalSigningSessionState::FAILED, ExternalSigningSessionState::REJECTED], true)) {
			throw new \DomainException('Submitted operation requires explicit reconciliation');
		}
		// Transitions with protected side effects have dedicated atomic APIs.
		if (in_array($next, [ExternalSigningSessionState::SIGNING_PENDING, ExternalSigningSessionState::SIGNATURE_RECEIVED, ExternalSigningSessionState::COMPLETING, ExternalSigningSessionState::COMPLETED], true)) {
			throw new \DomainException('Use the protected signing result or completion API');
		}
		return $this->cas($row, $actorId, $expectedRevision, $next);
	}

	/**
	 * Durable write-ahead reservation BEFORE invoking any external private key.
	 * An ambiguous crash window never creates another reservation or retry.
	 * @return array<string, int|string>
	 */
	public function claimSigning(
		string $id, string $actorId, int $expectedRevision,
		string $documentRevision, #[\SensitiveParameter] string $currentPdfBytes,
		string $expectedCertificatePem,
	): array {
		$this->assertFeatureEnabled();
		$row = $this->load($id, $actorId);
		$this->assertContext($row, $documentRevision, $currentPdfBytes, $expectedCertificatePem);
		if ($row['status'] !== ExternalSigningSessionState::READY_TO_SIGN->value) {
			throw new \DomainException('Cryptographic operation already reserved or not authorized');
		}
		$operationId = bin2hex(random_bytes(16));
		$this->db->beginTransaction();
		try {
			$this->assertRevisionAndBump($row, $expectedRevision, $actorId, ExternalSigningSessionState::SIGNING_PENDING);
			$qb = $this->db->getQueryBuilder();
			$qb->insert(self::ATTEMPT_TABLE)->values([
				'session_id' => $qb->createNamedParameter($id),
				'operation_id' => $qb->createNamedParameter($operationId),
				'status' => $qb->createNamedParameter('reserved'),
				'created_at' => $qb->createNamedParameter($this->now(), IQueryBuilder::PARAM_DATETIME_MUTABLE),
				'updated_at' => $qb->createNamedParameter($this->now(), IQueryBuilder::PARAM_DATETIME_MUTABLE),
			])->executeStatement();
			$this->audit($id, $actorId, 'signing_reserved');
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
		return $this->get($id, $actorId);
	}

	/**
	 * Reserve one publication transition; #9603 owns actual PDF publication.
	 * A crash after this reservation requires reconciliation, not a new write.
	 * @return array<string, int|string>
	 */
	public function reservePublication(
		string $id, string $actorId, int $expectedRevision,
		string $documentRevision, #[\SensitiveParameter] string $currentPdfBytes,
		string $expectedCertificatePem,
	): array {
		$this->assertFeatureEnabled();
		$row = $this->load($id, $actorId);
		$this->assertContext($row, $documentRevision, $currentPdfBytes, $expectedCertificatePem);
		if ($row['status'] !== ExternalSigningSessionState::SIGNATURE_RECEIVED->value) {
			throw new \DomainException('Publication cannot be reserved again');
		}
		return $this->cas($row, $actorId, $expectedRevision, ExternalSigningSessionState::COMPLETING);
	}

	/**
	 * Atomically store an authenticated provider result. The consuming
	 * workflow MUST validate provider authenticity and the raw signature
	 * against the expected certificate before accepting or publishing it.
	 * A late authentic response may resolve an UNCERTAIN outcome.
	 *
	 * @return array<string, int|string>
	 */
	public function receiveSignature(
		string $id,
		string $actorId,
		int $expectedRevision,
		#[\SensitiveParameter] string $signature,
		string $documentRevision,
		#[\SensitiveParameter] string $currentPdfBytes,
		string $expectedCertificatePem,
	): array {
		$this->assertFeatureEnabled();
		if ($signature === '' || strlen($signature) > 65536) {
			throw new \InvalidArgumentException('Invalid external signature length');
		}
		$row = $this->load($id, $actorId);
		$this->assertContext($row, $documentRevision, $currentPdfBytes, $expectedCertificatePem);
		$state = ExternalSigningSessionState::from((string)$row['status']);
		if (!in_array($state, [ExternalSigningSessionState::SIGNING_PENDING, ExternalSigningSessionState::UNCERTAIN], true)) {
			throw new \DomainException('No pending external signature');
		}
		$encrypted = $this->protect(base64_encode($signature));
		$this->db->beginTransaction();
		try {
			$this->assertRevisionAndBump($row, $expectedRevision, $actorId, ExternalSigningSessionState::SIGNATURE_RECEIVED, $encrypted);
			$this->updateAttempt($id, 'received');
			$this->audit($id, $actorId, 'signature_received');
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
		return $this->get($id, $actorId);
	}

	/**
	 * Complete the publication reservation exactly once. The caller MUST first
	 * publish a validated PDF through an idempotent, revision-checked workflow
	 * transaction. A repeated call returns the completed metadata, but never
	 * grants a second publication reservation.
	 *
	 * @return array<string, int|string>
	 */
	public function markCompleted(
		string $id,
		string $actorId,
		int $expectedRevision,
		string $documentRevision,
		#[\SensitiveParameter] string $currentPdfBytes,
		string $expectedCertificatePem,
	): array {
		$this->assertFeatureEnabled();
		$row = $this->load($id, $actorId);
		if ($row['status'] === ExternalSigningSessionState::COMPLETED->value) {
			return $this->metadata($row);
		}
		$this->assertContext($row, $documentRevision, $currentPdfBytes, $expectedCertificatePem);
		if ($row['status'] !== ExternalSigningSessionState::COMPLETING->value) {
			throw new \DomainException('Publication has not been reserved');
		}
		return $this->cas($row, $actorId, $expectedRevision, ExternalSigningSessionState::COMPLETED);
	}

	/** A signature is accessible only to the original server-authorized actor. */
	public function readSignature(string $id, string $actorId): string {
		$this->assertFeatureEnabled();
		$row = $this->load($id, $actorId);
		if (!in_array($row['status'], [
			ExternalSigningSessionState::SIGNATURE_RECEIVED->value,
			ExternalSigningSessionState::COMPLETING->value,
		], true) || !is_string($row['signature_enc']) || $row['signature_enc'] === '') {
			throw new \DomainException('No authenticated external signature available');
		}
		$signature = base64_decode($this->unprotect($row['signature_enc']), true);
		if (!is_string($signature) || $signature === '') {
			throw new \UnexpectedValueException('Malformed stored external signature');
		}
		return $signature;
	}

	/**
	 * Protected provider continuation data: no plaintext stored on the
	 * session row, and no public session read can access the secret table.
	 *
	 * @param array<string, string> $secrets
	 * @return array<string, int|string>
	 */
	public function saveContinuation(
		string $id,
		string $actorId,
		int $expectedRevision,
		#[\SensitiveParameter] array $secrets,
	): array {
		$this->assertFeatureEnabled();
		foreach ($secrets as $key => $value) {
			if (!is_string($key) || preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/D', $key) !== 1
				|| !is_string($value) || $value === '' || strlen($value) > 16384) {
				throw new \InvalidArgumentException('Invalid private continuation data');
			}
		}
		$json = json_encode((object)$secrets, JSON_THROW_ON_ERROR);
		if (strlen($json) > self::MAX_SECRET_JSON) {
			throw new \InvalidArgumentException('Continuation data exceeds size limit');
		}
		$row = $this->load($id, $actorId);
		if (in_array($row['status'], ['completed', 'rejected', 'expired', 'failed'], true)
			|| (new \DateTimeImmutable((string)$row['expires_at']))->getTimestamp() <= time()) {
			throw new \DomainException('Expired or terminal signing session cannot change');
		}
		$this->db->beginTransaction();
		try {
			$this->assertRevisionAndBump($row, $expectedRevision, $actorId);
			$qb = $this->db->getQueryBuilder();
			$updated = $qb->update(self::PRIVATE_TABLE)
				->set('secrets_enc', $qb->createNamedParameter($this->protect($json)))
				->where($qb->expr()->eq('session_id', $qb->createNamedParameter($id)))
				->executeStatement();
			if ($updated !== 1) {
				throw new \RuntimeException('Private session storage unavailable');
			}
			$this->audit($id, $actorId, 'continuation_updated');
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
		return $this->get($id, $actorId);
	}

	/** @return array<string, string> */
	public function readContinuation(string $id, string $actorId): array {
		$this->assertFeatureEnabled();
		$row = $this->load($id, $actorId);
		if (in_array($row['status'], ['completed', 'rejected', 'expired', 'failed'], true)) {
			throw new \DomainException('Terminal session continuation is unavailable');
		}
		$qb = $this->db->getQueryBuilder();
		$cipher = $qb->select('secrets_enc')->from(self::PRIVATE_TABLE)
			->where($qb->expr()->eq('session_id', $qb->createNamedParameter($id)))
			->executeQuery()->fetchOne();
		if (!is_string($cipher)) {
			throw new \UnexpectedValueException('Private session record missing');
		}
		if ($cipher === '') {
			return [];
		}
		$value = json_decode($this->unprotect($cipher), true, 16, JSON_THROW_ON_ERROR);
		if (!is_array($value) || ($value !== [] && array_is_list($value))) {
			throw new \UnexpectedValueException('Invalid continuation data shape');
		}
		foreach ($value as $key => $entry) {
			if (!is_string($key) || !is_string($entry)) {
				throw new \UnexpectedValueException('Invalid continuation data');
			}
		}
		return $value;
	}

	/**
	 * Termination/expiry removes token material, not audit history.
	 * The prepared state is no longer returned to a terminal caller.
	 *
	 * This does not retry an operation and is safe if two cleaners compete.
	 */
	public function expire(string $id, string $actorId, int $expectedRevision): array {
		$row = $this->load($id, $actorId);
		if (time() < (new \DateTimeImmutable((string)$row['expires_at']))->getTimestamp()) {
			throw new \DomainException('Session has not expired');
		}
		$state = ExternalSigningSessionState::from((string)$row['status']);
		// Once a private-key submission or document publication starts,
		// expiry is not proof that the side effect did not happen.
		// Neither state may be reopened or silently retried.
		if (in_array($state, [ExternalSigningSessionState::UNCERTAIN, ExternalSigningSessionState::COMPLETING], true)) {
			return $this->metadata($row);
		}
		$next = $state === ExternalSigningSessionState::SIGNING_PENDING
			? ExternalSigningSessionState::UNCERTAIN
			: ExternalSigningSessionState::EXPIRED;
		if (!$state->allows($next)) {
			throw new \DomainException('Cannot expire terminal session');
		}
		return $this->cas($row, $actorId, $expectedRevision, $next);
	}

	/**
	 * Conditional DB update linearizes concurrent requests; no SELECT-then-
	 * UPDATE race and no new cryptographic call on an expired signing claim.
	 *
	 * @param array<string, mixed> $row
	 * @return array<string, int|string>
	 */
	private function cas(
		array $row,
		string $actorId,
		int $expectedRevision,
		ExternalSigningSessionState $next,
		?string $encryptedSignature = null,
	): array {
		$this->db->beginTransaction();
		try {
			$this->assertRevisionAndBump($row, $expectedRevision, $actorId, $next, $encryptedSignature);
			if (in_array($next, [
				ExternalSigningSessionState::COMPLETED,
				ExternalSigningSessionState::REJECTED,
				ExternalSigningSessionState::EXPIRED,
				ExternalSigningSessionState::FAILED,
			], true)) {
				$purge = $this->db->getQueryBuilder();
				$purge->update(self::TABLE)
					->set('prepared_enc', $purge->createNamedParameter(''))
					->set('signature_enc', $purge->createNamedParameter(null))
					->where($purge->expr()->eq('public_id', $purge->createNamedParameter((string)$row['public_id'])))
					->executeStatement();
				$qb = $this->db->getQueryBuilder();
				$qb->update(self::PRIVATE_TABLE)->set('secrets_enc', $qb->createNamedParameter(''))
					->where($qb->expr()->eq('session_id', $qb->createNamedParameter((string)$row['public_id'])))
					->executeStatement();
			}
			if ($next === ExternalSigningSessionState::UNCERTAIN) {
				$this->updateAttempt((string)$row['public_id'], 'uncertain');
			}
			$this->audit((string)$row['public_id'], $actorId, $next->value);
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
		return $this->get((string)$row['public_id'], $actorId);
	}

	private function updateAttempt(string $sessionId, string $status): void {
		$qb = $this->db->getQueryBuilder();
		$changed = $qb->update(self::ATTEMPT_TABLE)
			->set('status', $qb->createNamedParameter($status))
			->set('updated_at', $qb->createNamedParameter($this->now(), IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->where($qb->expr()->eq('session_id', $qb->createNamedParameter($sessionId)))
			->executeStatement();
		if ($changed !== 1) {
			throw new \RuntimeException('Durable signing attempt missing');
		}
	}

	/** @param array<string, mixed> $row */
	private function assertRevisionAndBump(
		array $row,
		int $expectedRevision,
		string $actorId,
		?ExternalSigningSessionState $next = null,
		?string $encryptedSignature = null,
	): void {
		if ($expectedRevision < 1 || (int)$row['revision'] !== $expectedRevision) {
			throw new \RuntimeException('Signing session changed concurrently');
		}
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE)
			->set('revision', $qb->createFunction('revision + 1'))
			->set('updated_at', $qb->createNamedParameter($this->now(), IQueryBuilder::PARAM_DATETIME_MUTABLE));
		if ($next !== null) {
			$qb->set('status', $qb->createNamedParameter($next->value));
		}
		if ($encryptedSignature !== null) {
			$qb->set('signature_enc', $qb->createNamedParameter($encryptedSignature));
		}
		$changed = $qb->where($qb->expr()->eq('public_id', $qb->createNamedParameter((string)$row['public_id'])))
			->andWhere($qb->expr()->eq('actor_id', $qb->createNamedParameter($actorId)))
			->andWhere($qb->expr()->eq('revision', $qb->createNamedParameter($expectedRevision, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter((string)$row['status'])))
			->executeStatement();
		if ($changed !== 1) {
			throw new \RuntimeException('Signing session changed concurrently');
		}
	}

	/** @return array<string, mixed> */
	private function load(string $id, string $actorId): array {
		if (preg_match('/^[a-f0-9]{32}$/D', $id) !== 1 || $actorId === '') {
			throw new \OutOfBoundsException('Signing session not found');
		}
		$qb = $this->db->getQueryBuilder();
		$row = $qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('public_id', $qb->createNamedParameter($id)))
			->andWhere($qb->expr()->eq('actor_id', $qb->createNamedParameter($actorId)))
			->executeQuery()->fetchAssociative();
		if ($row === false) {
			throw new \OutOfBoundsException('Signing session not found');
		}
		return $row;
	}

	/** @param array<string, mixed> $row */
	private function assertContext(
		array $row,
		string $documentRevision,
		string $currentPdfBytes,
		string $expectedCertificatePem,
	): void {
		$this->assertIntegration((string)$row['integration_id'], (int)$row['integration_revision']);
		if ($row['document_revision'] !== $documentRevision
			|| !hash_equals((string)$row['document_sha256'], hash('sha256', $currentPdfBytes))) {
			throw new \DomainException('Source document revision has changed');
		}
		if (!hash_equals((string)$row['certificate_sha256'], $this->certificateFingerprint($expectedCertificatePem))) {
			throw new \DomainException('External signer certificate changed');
		}
		$expires = (new \DateTimeImmutable((string)$row['expires_at']))->getTimestamp();
		if ($expires <= time() && !in_array($row['status'], ['signing_pending', 'uncertain', 'completing'], true)) {
			throw new \DomainException('External signing session expired');
		}
	}

	private function assertIntegration(string $id, int $revision): void {
		$stored = $this->integrations->find($id);
		if ($stored === null || !$stored->enabled || $stored->revision !== $revision) {
			throw new \DomainException('External signing integration has changed or was disabled');
		}
	}

	private function certificateFingerprint(string $pem): string {
		$cert = @openssl_x509_read($pem);
		if ($cert === false || !openssl_x509_export($cert, $normalized)) {
			throw new \InvalidArgumentException('Expected certificate must be valid X.509');
		}
		$der = base64_decode((string)preg_replace('/-----[^-]+-----|\s+/', '', $normalized), true);
		if (!is_string($der)) {
			throw new \InvalidArgumentException('Invalid expected certificate');
		}
		return hash('sha256', $der);
	}

	private function restorePrepared(string $encrypted): PreparedExternalOperation {
		try {
			return ExternalSigningPreparedStateCodec::decode($this->unprotect($encrypted));
		} catch (\Throwable) {
			// The API never exposes decryption or stored-format details.
			throw new \UnexpectedValueException('Prepared signing session cannot be restored');
		}
	}

	private function protect(#[\SensitiveParameter] string $plaintext): string {
		try {
			return $this->crypto->encrypt($plaintext);
		} catch (\Throwable) {
			throw new \RuntimeException('Unable to protect external signing state');
		}
	}

	private function unprotect(#[\SensitiveParameter] string $ciphertext): string {
		try {
			return $this->crypto->decrypt($ciphertext);
		} catch (\Throwable) {
			throw new \UnexpectedValueException('Protected external signing state is invalid');
		}
	}

	/** @param array<string, mixed> $row @return array<string, int|string> */
	private function metadata(array $row): array {
		return [
			'id' => (string)$row['public_id'],
			'actorId' => (string)$row['actor_id'],
			'signRequestId' => (int)$row['sign_request_id'],
			'integrationId' => (string)$row['integration_id'],
			'integrationRevision' => (int)$row['integration_revision'],
			'credentialId' => (string)$row['credential_id'],
			'certificateSha256' => (string)$row['certificate_sha256'],
			'documentRevision' => (string)$row['document_revision'],
			'documentSha256' => (string)$row['document_sha256'],
			'status' => (string)$row['status'],
			'revision' => (int)$row['revision'],
			'expiresAt' => (string)$row['expires_at'],
		];
	}

	private function assertFeatureEnabled(): void {
		if (!$this->features->isEnabled()) {
			throw new \DomainException('Experimental signing features are disabled');
		}
	}

	private function audit(string $id, string $actorId, string $event): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert(self::AUDIT_TABLE)->values([
			'session_id' => $qb->createNamedParameter($id),
			'actor_id' => $qb->createNamedParameter($actorId),
			'event' => $qb->createNamedParameter($event),
			'created_at' => $qb->createNamedParameter($this->now(), IQueryBuilder::PARAM_DATETIME_MUTABLE),
		])->executeStatement();
	}

	private function now(): \DateTime {
		return new \DateTime('now', new \DateTimeZone('UTC'));
	}
}
