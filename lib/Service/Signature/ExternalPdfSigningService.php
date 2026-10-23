<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 rodrigodev
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signature;

use OCA\Libresign\Service\Signature\Exception\ExternalSigningException;
use SignerPHP\PdfSigner\Application\DTO\HashAlgorithm;
use SignerPHP\PdfSigner\Application\DTO\PreparedExternalSignature;
use SignerPHP\PdfSigner\Application\DTO\SigningContextDto;
use SignerPHP\PdfSigner\Infrastructure\Native\Service\ExternalPdfSigningService as UpstreamExternalPdfSigningService;

/**
 * Internal, resumable external PDF-signing integration for issue #8354.
 *
 * Delegates PDF/CMS preparation and completion to the upstream
 * signerphp/pdf-signer prepare/complete API and validates the completed
 * output with LibreSign's existing validation layer before returning it.
 *
 * Trust boundary: this is an internal service for TRUSTED prepared
 * operations. Passing prepared state, original PDF bytes and an expected
 * certificate together does NOT authenticate any of them when all are
 * client-controlled. Checksums, content hashes and even a valid signature
 * are not proof of authorization. Future orchestration must bind operation
 * identity, document revision, certificate, user, SignRequest, options,
 * expiry and completion status on the server side.
 *
 * The service performs no file writes, no SignRequest/FileEntity status
 * updates, no events, no notifications, no credential handling and no
 * persistence. Returning valid bytes does not authorize publication of a
 * document; a future publication path must recheck the authoritative
 * document revision under appropriate coordination before writing.
 *
 * @psalm-import-type MappedPdfValidationResult from PdfSignatureValidationDefinitions
 */
final class ExternalPdfSigningService {
	public function __construct(
		private PdfSignatureValidationService $signatureValidation,
	) {
	}

	/**
	 * Prepare a PDF signature without any private key material.
	 *
	 * The context carries only public certificate material (signer
	 * certificate PEM plus optional chain) and the signing options. The
	 * returned value contains the cryptographic payload for the external
	 * signer (a pre-computed digest) and an opaque, versioned state string
	 * that survives process termination.
	 *
	 * One call represents exactly one cryptographic operation. The existing
	 * multi-visible-element signing flow performs several operations and is
	 * out of scope here.
	 *
	 * @throws ExternalSigningException when the requested options are not supported
	 */
	public function prepare(
		SigningContextDto $context,
		HashAlgorithm $digestAlgorithm = HashAlgorithm::Sha256,
	): PreparedExternalSignature {
		$this->assertSupportedOptions($context);

		return (new UpstreamExternalPdfSigningService())->prepare($context, $digestAlgorithm);
	}

	/**
	 * Complete a prepared operation with the externally produced signature
	 * and return validated PDF bytes.
	 *
	 * `$originalPdf` must be the exact PDF bytes previously supplied to
	 * `prepare()` through `SigningContextDto::pdf->content` — after any
	 * caller-owned preprocessing, i.e. the revision the prepared operation
	 * was created for. It is not the originally uploaded file and not the
	 * prepared internal revision.
	 *
	 * Upstream completion embeds the returned signature bytes verbatim: a
	 * completed PDF alone is not evidence of a valid signature. Before
	 * returning bytes, this service establishes that exactly one new
	 * signature was added, that it validates cryptographically, that its
	 * signer certificate matches `$expectedCertificatePem` by DER
	 * fingerprint, that it covers the completed revision, and that prior
	 * signatures kept their baseline validity.
	 *
	 * @throws ExternalSigningException when the completed output fails validation
	 * @throws \SignerPHP\PdfSigner\Domain\Exception\SignerException on upstream rejection (empty signature, corrupted state, size overflow)
	 */
	public function complete(
		PreparedExternalSignature $prepared,
		string $rawSignature,
		string $originalPdf,
		string $expectedCertificatePem,
	): string {
		$baseline = $this->signatureValidation->validateFromString($originalPdf);
		$completed = (new UpstreamExternalPdfSigningService())
			->complete($prepared->state, $rawSignature);
		$this->assertCompletedRevision($completed, $originalPdf, $baseline, $expectedCertificatePem);

		return $completed;
	}

	/**
	 * The upstream external prepare API ignores the RFC3161 timestamp
	 * option. Reject it instead of silently dropping a requested behavior.
	 *
	 * @throws ExternalSigningException on unsupported options
	 */
	private function assertSupportedOptions(SigningContextDto $context): void {
		if ($context->options->timestamp !== null) {
			throw new ExternalSigningException(
				'RFC3161 timestamping is not supported by the external prepare API.'
				. ' Remove the timestamp option or sign through the synchronous engine.',
			);
		}
	}

	/**
	 * @param list<MappedPdfValidationResult> $baseline validation of the original PDF before completion
	 * @throws ExternalSigningException on any failed guarantee
	 */
	private function assertCompletedRevision(
		string $completed,
		string $originalPdf,
		array $baseline,
		string $expectedCertificatePem,
	): void {
		$completedEntries = $this->signatureValidation->validateFromString($completed);
		if ($completedEntries === []) {
			throw new ExternalSigningException('Completed document contains no signatures.');
		}

		$baselineByKey = [];
		foreach ($baseline as $entry) {
			$baselineByKey[$this->signatureEntryKey($entry)] = $entry;
		}

		$newEntries = array_values(array_filter(
			$completedEntries,
			fn (array $entry): bool => ! isset($baselineByKey[$this->signatureEntryKey($entry)]),
		));
		if (count($newEntries) !== 1) {
			throw new ExternalSigningException(sprintf(
				'Expected exactly one new signature after completion, found %d.',
				count($newEntries),
			));
		}
		$newEntry = $newEntries[0];

		$this->assertCoversCompletedRevision($newEntry, $completed);
		$this->assertCryptographicallyValid($newEntry);
		$this->assertSignerCertificateMatches($newEntry, $expectedCertificatePem);
		$this->assertPriorSignaturesRetained($baselineByKey, $completedEntries);
	}

	/**
	 * The new signature must be the latest incremental revision of the
	 * completed document: its ByteRange starts at zero and its second
	 * segment ends exactly at the end of the completed bytes.
	 *
	 * @param MappedPdfValidationResult $entry
	 */
	private function assertCoversCompletedRevision(array $entry, string $completed): void {
		$metadata = $entry['signature']->metadata;
		$range = $metadata->range;
		if ($range === null) {
			throw new ExternalSigningException('The new signature has no ByteRange information.');
		}
		if ($range['offset1'] !== 0 || strlen($completed) !== $range['length2']) {
			throw new ExternalSigningException(
				'The new signature does not cover the completed document revision.'
				. sprintf(' ByteRange ends at %d, document is %d bytes.', $range['length2'], strlen($completed)),
			);
		}
		$modificationState = $metadata->documentModificationState;
		if ($modificationState !== null && $modificationState->value !== 'unchanged') {
			throw new ExternalSigningException(sprintf(
				'The completed revision was modified after signing (%s).',
				$modificationState->value,
			));
		}
	}

	/**
	 * @param MappedPdfValidationResult $entry
	 */
	private function assertCryptographicallyValid(array $entry): void {
		$signatureValidation = $entry['signatureValidation'];
		if (($signatureValidation['isValid'] ?? false) !== true) {
			throw new ExternalSigningException(sprintf(
				'The new signature is not cryptographically valid (validation id %d).',
				$signatureValidation['id'] ?? -1,
			));
		}
	}

	/**
	 * Certificate trust is deliberately NOT required here: a valid signer
	 * with an untrusted issuer is a policy decision for the caller, not
	 * malformed signature bytes.
	 *
	 * @param MappedPdfValidationResult $entry
	 */
	private function assertSignerCertificateMatches(array $entry, string $expectedCertificatePem): void {
		$signerPem = $entry['certificates'][0] ?? null;
		if (! is_string($signerPem) || $signerPem === '') {
			throw new ExternalSigningException('The new signature carries no signer certificate.');
		}
		if ($this->certificateFingerprint($signerPem) !== $this->certificateFingerprint($expectedCertificatePem)) {
			throw new ExternalSigningException('The new signature was produced by an unexpected certificate.');
		}
	}

	/**
	 * Every pre-existing signature must still be present. Signatures that
	 * were valid before completion must remain cryptographically valid;
	 * signatures that were already invalid before completion do not fail
	 * the operation, but they cannot be silently "repaired" either.
	 *
	 * @param array<string, MappedPdfValidationResult> $baselineByKey
	 * @param list<MappedPdfValidationResult> $completedEntries
	 */
	private function assertPriorSignaturesRetained(array $baselineByKey, array $completedEntries): void {
		$completedByKey = [];
		foreach ($completedEntries as $entry) {
			$completedByKey[$this->signatureEntryKey($entry)] = $entry;
		}

		foreach ($baselineByKey as $key => $baselineEntry) {
			$completedEntry = $completedByKey[$key] ?? null;
			if ($completedEntry === null) {
				throw new ExternalSigningException('An existing signature was removed during completion.');
			}
			$wasValid = $baselineEntry['signatureValidation']['isValid'] ?? false;
			$isValid = $completedEntry['signatureValidation']['isValid'] ?? false;
			if ($wasValid && ! $isValid) {
				throw new ExternalSigningException('A previously valid signature no longer validates after completion.');
			}
		}
	}

	/**
	 * Stable semantic identity of one signature across validations: the
	 * signature field name plus its exact ByteRange geometry. Array
	 * positions are never used.
	 *
	 * @param MappedPdfValidationResult $entry
	 */
	private function signatureEntryKey(array $entry): string {
		$metadata = $entry['signature']->metadata;
		$range = $metadata->range;

		return $metadata->field
			. '|' . ($range === null ? '?' : "{$range['offset1']}:{$range['length1']}:{$range['offset2']}:{$range['length2']}");
	}

	/**
	 * SHA-256 over the DER bytes of the certificate. Stable regardless of
	 * PEM line wrapping.
	 */
	private function certificateFingerprint(string $certificatePem): string {
		$certificate = openssl_x509_read($certificatePem);
		if ($certificate === false) {
			throw new ExternalSigningException('The expected certificate is not a readable X.509 certificate.');
		}
		if (! openssl_x509_export($certificate, $normalizedPem)) {
			throw new ExternalSigningException('Could not normalize the expected certificate.');
		}
		$der = base64_decode(
			(string)preg_replace('/-----[^-]+-----|\s+/', '', $normalizedPem),
			true,
		);
		if ($der === false) {
			throw new ExternalSigningException('Could not decode the expected certificate.');
		}

		return hash('sha256', $der);
	}
}
