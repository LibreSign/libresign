<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing;

use OCA\Libresign\Service\Signing\External\SessionStore;
use OCA\Libresign\Service\Signing\External\SessionState;
use OCA\Libresign\Service\Signature\ExternalPdfSigningService;
use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscCredentialEligibility;
use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscDriver;
use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscIntegration;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\HashAlgorithm;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SignatureAlgorithm;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SignatureEncoding;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SigningContextDto;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SigningInputType;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\RemoteSigning\DTO\RemoteCertificate;

/**
 * Internal cryptographic half of the experimental CSC v1 workflow.
 *
 * Only a dedicated, configured synthetic CSC laboratory is accepted. No
 * administrator-controlled CSC token proves the signer's key authorization.
 * The caller MUST resolve the current SignRequest, its authorized signer,
 * effective signing policy, and current PDF bytes from trusted storage before
 * every operation. No method here is a public controller or a permission
 * bypass. A later idempotent publisher owns the durable File/SignRequest
 * update, audit/notifications and markCompleted().
 *
 * Once a private-key operation is reserved, failures are conservatively
 * UNCERTAIN: never retry a potentially submitted signHash blindly.
 */
final class SyntheticCscSigningOperation {
	public function __construct(
		private ExternalPdfSigningService $pdfSigning,
		private SessionStore $sessions,
	) {
	}

	/**
	 * Prepare the exact server-owned PDF and persist an actor/request-bound
	 * session. $credentialId is a trusted synthetic laboratory allowance,
	 * never an unrestricted identifier supplied by the browser.
	 *
	 * @return array<string, int|string>
	 */
	public function prepare(
		CscIntegration $laboratory,
		ExternalIntegrationRecord $integration,
		string $credentialId,
		SigningContextDto $trustedContext,
		string $actorId,
		int $signRequestId,
		string $attemptId,
		string $documentRevision,
		\DateTimeImmutable $expiresAt,
	): array {
		$this->assertLaboratory($laboratory, $integration);
		$certificate = $this->selectCertificate($laboratory, $credentialId);
		$this->assertExpectedCertificate($trustedContext, $certificate);
		$prepared = $this->pdfSigning->prepare($trustedContext);
		$this->assertSupportedPayload($prepared->upstream->payload);

		return $this->sessions->create(
			$actorId, $signRequestId, $attemptId, $integration,
			$certificate->identifier, $certificate->certificate->certificatePem,
			$documentRevision, $trustedContext->pdf->content, $prepared, $expiresAt,
		);
	}

	/**
	 * Start exactly one lab private-key operation. Returns validated PDF
	 * bytes and a reserved, NOT YET PUBLISHED session. Callers must publish
	 * with a revision-checked idempotent workflow and ONLY then markCompleted.
	 * Never invoke this method again for an uncertain or pending session.
	 *
	 * @return array{session: array<string, int|string>, pdf: string}
	 */
	public function sign(
		CscIntegration $laboratory,
		ExternalIntegrationRecord $integration,
		string $sessionId,
		int $signRequestId,
		string $actorId,
		string $documentRevision,
		#[\SensitiveParameter] string $currentPdfBytes,
	): array {
		$this->assertLaboratory($laboratory, $integration);
		$session = $this->sessions->getForSignRequest($sessionId, $signRequestId, $actorId);
		$this->assertSessionIntegration($session, $integration);
		$certificate = $this->selectCertificate($laboratory, (string)$session['credentialId']);
		$certificatePem = $certificate->certificate->certificatePem;
		$prepared = $this->sessions->resume($sessionId, $actorId, $documentRevision, $currentPdfBytes, $certificatePem);
		$this->assertSupportedPayload($prepared->upstream->payload);

		if ($session['status'] === SessionState::PREPARING->value) {
			$session = $this->sessions->advance($sessionId, $actorId, (int)$session['revision'],
				SessionState::READY_TO_SIGN, $documentRevision, $currentPdfBytes, $certificatePem);
		}
		if ($session['status'] !== SessionState::READY_TO_SIGN->value) {
			throw new \DomainException('CSC session is not available for a new signing submission');
		}

		$session = $this->sessions->claimSigning($sessionId, $actorId, (int)$session['revision'],
			$documentRevision, $currentPdfBytes, $certificatePem);
		try {
			$rawSignature = $laboratory->sign($prepared->upstream->payload, $certificate)->bytes;
		} catch (\Throwable $e) {
			// Even an apparently definite pre-submission failure cannot justify
			// reusing this write-ahead reservation in the first lab workflow.
			try {
				$this->sessions->advance($sessionId, $actorId, (int)$session['revision'],
					SessionState::UNCERTAIN, $documentRevision, $currentPdfBytes, $certificatePem);
			} catch (\Throwable) {
				// A lost state update leaves SIGNING_PENDING, which also forbids
				// resubmission. Never hide the original provider exception.
			}
			throw new \DomainException('CSC submission outcome is uncertain and requires reconciliation', 0, $e);
		}
		$this->sessions->receiveSignature($sessionId, $actorId, (int)$session['revision'],
			$rawSignature, $documentRevision, $currentPdfBytes, $certificatePem);
		return $this->completeAndReserve($sessionId, $signRequestId, $actorId,
			$documentRevision, $currentPdfBytes, $certificatePem);
	}

	/**
	 * Reconstruct an already-received signature without calling the provider.
	 * The method is safe after a PHP crash but a second publication reservation
	 * is NEVER granted. A session in COMPLETING requires explicit idempotent
	 * publication reconciliation instead of replaying a success notification.
	 *
	 * @return array{session: array<string, int|string>, pdf: string}
	 */
	public function completeAndReserve(
		string $sessionId,
		int $signRequestId,
		string $actorId,
		string $documentRevision,
		#[\SensitiveParameter] string $currentPdfBytes,
		string $expectedCertificatePem,
	): array {
		$metadata = $this->sessions->getForSignRequest($sessionId, $signRequestId, $actorId);
		if ($metadata['status'] !== SessionState::SIGNATURE_RECEIVED->value) {
			throw new \DomainException('Publication already reserved or signing result unavailable');
		}
		$prepared = $this->sessions->preparedForCompletion($sessionId, $actorId,
			$documentRevision, $currentPdfBytes, $expectedCertificatePem);
		$signature = $this->sessions->readSignature($sessionId, $actorId);
		// This verifies exact PDF revision, CMS signature, actual X.509 signer,
		// prior signatures, and DocMDP before any publication is reserved.
		$validatedPdf = $this->pdfSigning->complete($prepared, $signature,
			$currentPdfBytes, $expectedCertificatePem);
		$session = $this->sessions->reservePublication($sessionId, $actorId, (int)$metadata['revision'],
			$documentRevision, $currentPdfBytes, $expectedCertificatePem);
		return ['session' => $session, 'pdf' => $validatedPdf];
	}

	/**
	 * Rebuild a cryptographically validated PDF after publication reservation
	 * without submitting the private-key operation again. Returning bytes is
	 * NOT a second permission to publish: the caller MUST reconcile the
	 * authoritative stored document and SignRequest using an idempotent
	 * revision-checked publication transaction before marking completion.
	 */
	public function reconstructForReconciliation(
		string $sessionId,
		int $signRequestId,
		string $actorId,
		string $documentRevision,
		#[\SensitiveParameter] string $currentPdfBytes,
		string $expectedCertificatePem,
	): string {
		$metadata = $this->sessions->getForSignRequest($sessionId, $signRequestId, $actorId);
		if ($metadata['status'] !== SessionState::COMPLETING->value) {
			throw new \DomainException('No reserved CSC publication to reconcile');
		}
		$prepared = $this->sessions->preparedForCompletion($sessionId, $actorId,
			$documentRevision, $currentPdfBytes, $expectedCertificatePem);
		$signature = $this->sessions->readSignature($sessionId, $actorId);
		return $this->pdfSigning->complete($prepared, $signature,
			$currentPdfBytes, $expectedCertificatePem);
	}

	private function assertLaboratory(CscIntegration $laboratory, ExternalIntegrationRecord $integration): void {
		if ($integration->driverId !== CscDriver::ID || !$integration->enabled || $integration->hasSecrets
			|| array_keys($integration->configuration) !== ['baseUrl']
			|| !is_string($integration->configuration['baseUrl'])) {
			throw new \DomainException('CSC synthetic signing requires a dedicated enabled laboratory integration without administrator secrets');
		}
		$laboratory->assertSyntheticLaboratory($integration->configuration['baseUrl']);
	}

	/** @param array<string, int|string> $session */
	private function assertSessionIntegration(array $session, ExternalIntegrationRecord $integration): void {
		if ($session['integrationId'] !== $integration->id
			|| (int)$session['integrationRevision'] !== $integration->revision) {
			throw new \DomainException('CSC session integration context changed');
		}
	}

	private function selectCertificate(CscIntegration $laboratory, string $credentialId): RemoteCertificate {
		if ($credentialId === '' || strlen($credentialId) > 255) {
			throw new \InvalidArgumentException('Invalid CSC laboratory credential');
		}
		$available = $laboratory->certificates($credentialId);
		if (count($available) !== 1 || !hash_equals($credentialId, $available[0]->identifier)) {
			throw new \DomainException('CSC laboratory credential is not eligible');
		}
		(new CscCredentialEligibility())->describe($available[0]);
		return $available[0];
	}

	private function assertExpectedCertificate(SigningContextDto $context, RemoteCertificate $certificate): void {
		$selected = openssl_x509_fingerprint($certificate->certificate->certificatePem, 'sha256');
		$prepared = openssl_x509_fingerprint($context->certificate->certificatePem, 'sha256');
		if (!is_string($selected) || !is_string($prepared) || !hash_equals($selected, $prepared)) {
			throw new \DomainException('CSC signing certificate does not match the prepared signer');
		}
	}

	private function assertSupportedPayload(\OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\ExternalSigningPayload $payload): void {
		if ($payload->inputType !== SigningInputType::Digest
			|| $payload->digestAlgorithm !== HashAlgorithm::Sha256
			|| $payload->signatureAlgorithm !== SignatureAlgorithm::RsaPkcs1V15
			|| $payload->signatureEncoding !== SignatureEncoding::RsaPkcs1
			|| strlen($payload->input()) !== 32) {
			throw new \DomainException('CSC laboratory only supports exact SHA-256 RSA PKCS#1 v1.5 digests');
		}
	}
}
