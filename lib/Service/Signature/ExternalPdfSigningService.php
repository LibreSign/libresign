<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 rodrigodev
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signature;

use OCA\Libresign\Handler\DocMdpHandler;
use OCA\Libresign\Service\Signature\Exception\ExternalSigningException;
use OCA\Libresign\Vendor\phpseclib4\File\CMS\SignedData;
use OCA\Libresign\Vendor\phpseclib4\File\X509;
use SignerPHP\PdfSigner\Application\DTO\HashAlgorithm;
use SignerPHP\PdfSigner\Application\DTO\SigningContextDto;
use SignerPHP\PdfSigner\Infrastructure\Native\Service\ExternalPdfSigningService as UpstreamExternalPdfSigningService;

/**
 * Internal, resumable external PDF-signing integration for issue #8354.
 *
 * Delegates PDF/CMS preparation and completion to the upstream
 * signerphp/pdf-signer prepare/complete API and validates the completed
 * output with LibreSign's existing validation layer before returning it.
 *
 * Guarantees established before validated bytes are returned:
 *
 * - Document binding: a prepared operation remembers the SHA-256 of the
 *   exact PDF bytes handed to prepare(); complete() rejects any
 *   $originalPdf that does not match, even when the supplied signature is
 *   cryptographically valid for a different document. This is internal
 *   consistency between a trusted prepared operation and its input; it is
 *   NOT authentication of externally supplied state.
 * - DocMDP: preparation is refused when the input document carries a
 *   certification signature whose DocMDP level forbids additional
 *   signatures (checked through LibreSign's existing DocMdpHandler).
 * - Signer identity: the certificate actually bound to the new signature
 *   is resolved from the CMS SignerInfo (issuer and serial) via the CMS
 *   engine the validation layer already uses, never inferred from
 *   certificate-set ordering, and must match the expected certificate.
 * - Prior signatures: every pre-existing signature must still be present
 *   with unchanged signature contents and its earlier validity state.
 *   Duplicate or ambiguous signature identities are rejected.
 *
 * Trust boundary: this is an internal service for TRUSTED prepared
 * operations. Passing prepared state, original PDF bytes and an expected
 * certificate together does NOT authenticate any of them when all are
 * client-controlled. The preparation-input fingerprint is compared, not
 * authenticated: an attacker who controls the prepared operation also
 * controls the recorded fingerprint. Session storage, MAC/token
 * infrastructure and authorization to sign or publish remain outside this
 * service.
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
		private DocMdpHandler $docMdpHandler,
	) {
	}

	/**
	 * Prepare a PDF signature without any private key material.
	 *
	 * The context carries only public certificate material (signer
	 * certificate PEM plus optional chain) and the signing options. The
	 * returned envelope contains the upstream cryptographic payload for the
	 * external signer (a pre-computed digest), the opaque, versioned state
	 * string that survives process termination, and the fingerprint of the
	 * exact preparation input.
	 *
	 * One call represents exactly one cryptographic operation. The existing
	 * multi-visible-element signing flow performs several operations and is
	 * out of scope here.
	 *
	 * @throws ExternalSigningException when the requested options are not supported or the document forbids additional signatures
	 */
	public function prepare(
		SigningContextDto $context,
		HashAlgorithm $digestAlgorithm = HashAlgorithm::Sha256,
	): PreparedExternalOperation {
		$this->assertSupportedOptions($context);
		$this->assertDocMdpAllowsAdditionalSignatures($context->pdf->content);

		$upstream = (new UpstreamExternalPdfSigningService())->prepare($context, $digestAlgorithm);

		return new PreparedExternalOperation(
			$upstream,
			hash('sha256', $context->pdf->content),
		);
	}

	/**
	 * Complete a prepared operation with the externally produced signature
	 * and return validated PDF bytes.
	 *
	 * `$originalPdf` must be the exact PDF bytes previously supplied to
	 * `prepare()` through `SigningContextDto::pdf->content` — after any
	 * caller-owned preprocessing, i.e. the revision the prepared operation
	 * was created for. It is not the originally uploaded file and not the
	 * prepared internal revision. A mismatch is rejected before completion,
	 * regardless of the signature's cryptographic validity.
	 *
	 * Upstream completion embeds the returned signature bytes verbatim: a
	 * completed PDF alone is not evidence of a valid signature. Before
	 * returning bytes, this service establishes that exactly one new
	 * signature was added, that it validates cryptographically, that its
	 * resolved signer certificate matches `$expectedCertificatePem`, that
	 * it covers the completed revision, and that prior signatures kept
	 * their contents and baseline validity.
	 *
	 * @throws ExternalSigningException when the completed output fails validation
	 * @throws \SignerPHP\PdfSigner\Domain\Exception\SignerException on upstream rejection (empty signature, corrupted state, size overflow)
	 */
	public function complete(
		PreparedExternalOperation $prepared,
		string $rawSignature,
		string $originalPdf,
		string $expectedCertificatePem,
	): string {
		$this->assertPreparationInputMatches($prepared, $originalPdf);
		$baseline = $this->signatureValidation->validateFromString($originalPdf);
		$completed = (new UpstreamExternalPdfSigningService())
			->complete($prepared->upstream->state, $rawSignature);
		$this->assertCompletedRevision($completed, $baseline, $expectedCertificatePem);

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
	 * A certification signature with DocMDP "no changes allowed" forbids
	 * adding another signature. Reuse LibreSign's existing handler instead
	 * of duplicating PDF parsing.
	 *
	 * @throws ExternalSigningException when the input document forbids additional signatures
	 */
	private function assertDocMdpAllowsAdditionalSignatures(string $pdfBytes): void {
		$resource = fopen('php://temp', 'r+');
		if ($resource === false) {
			throw new ExternalSigningException('Could not buffer the document for DocMDP inspection.');
		}
		fwrite($resource, $pdfBytes);
		rewind($resource);
		try {
			if (! $this->docMdpHandler->allowsAdditionalSignatures($resource)) {
				throw new ExternalSigningException(
					'The document has a certification signature whose DocMDP policy'
					. ' forbids additional signatures.',
				);
			}
		} finally {
			fclose($resource);
		}
	}

	/**
	 * Internal consistency between the trusted prepared operation and the
	 * supplied original document: the exact bytes given to prepare() are
	 * the only acceptable completion input. This is a comparison against
	 * the operation's own record, not authentication of client state.
	 *
	 * @throws ExternalSigningException on substitution
	 */
	private function assertPreparationInputMatches(PreparedExternalOperation $prepared, string $originalPdf): void {
		if (! hash_equals($prepared->preparationInputSha256, hash('sha256', $originalPdf))) {
			throw new ExternalSigningException(
				'The supplied original PDF does not match the document the prepared'
				. ' operation was created for.',
			);
		}
	}

	/**
	 * @param list<MappedPdfValidationResult> $baseline validation of the original PDF before completion
	 * @throws ExternalSigningException on any failed guarantee
	 */
	private function assertCompletedRevision(
		string $completed,
		array $baseline,
		string $expectedCertificatePem,
	): void {
		$completedEntries = $this->signatureValidation->validateFromString($completed);
		if ($completedEntries === []) {
			throw new ExternalSigningException('Completed document contains no signatures.');
		}

		$baselineByKey = $this->mapByIdentity($baseline, 'baseline');
		$completedByKey = $this->mapByIdentity($completedEntries, 'completed');

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
		$this->assertPriorSignaturesRetained($baselineByKey, $completedByKey);
	}

	/**
	 * Index validation entries by stable semantic identity. Two entries
	 * with the same identity are ambiguous and rejected instead of being
	 * silently overwritten in the associative map.
	 *
	 * @param list<MappedPdfValidationResult> $entries
	 * @return array<string, MappedPdfValidationResult>
	 * @throws ExternalSigningException on duplicate identities
	 */
	private function mapByIdentity(array $entries, string $origin): array {
		$mapped = [];
		foreach ($entries as $entry) {
			$key = $this->signatureEntryKey($entry);
			if (isset($mapped[$key])) {
				throw new ExternalSigningException(sprintf(
					'Ambiguous signature identity: two signatures in the %s document'
					. ' share field name and ByteRange geometry.',
					$origin,
				));
			}
			$mapped[$key] = $entry;
		}

		return $mapped;
	}

	/**
	 * The new signature must be the latest incremental revision of the
	 * completed document: its ByteRange starts at zero and its second
	 * segment ends exactly at the end of the completed bytes.
	 *
	 * The pinned validator normalizes `range['length2']` to the END OFFSET
	 * of the second ByteRange segment (offset2 + its PDF length), not the
	 * raw PDF ByteRange length value, so comparing it with
	 * `strlen($completed)` is the correct revision-coverage check for that
	 * model; adding offset2 again would be wrong.
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
	 * The signer certificate is resolved from the CMS SignerInfo (issuer
	 * and serial number) through the CMS engine the validation layer
	 * already depends on. Certificate-set ordering carries no signer
	 * identity and is never used.
	 *
	 * Certificate trust is deliberately NOT required here: a valid signer
	 * with an untrusted issuer is a policy decision for the caller, not
	 * malformed signature bytes.
	 *
	 * @param MappedPdfValidationResult $entry
	 * @throws ExternalSigningException when the signer cannot be resolved or differs from the expected certificate
	 */
	private function assertSignerCertificateMatches(array $entry, string $expectedCertificatePem): void {
		$signerPem = $this->resolveSignerCertificatePem($entry);
		if ($this->certificateFingerprint($signerPem) !== $this->certificateFingerprint($expectedCertificatePem)) {
			throw new ExternalSigningException('The new signature was produced by an unexpected certificate.');
		}
	}

	/**
	 * Resolve the certificate actually bound to the signature: the single
	 * CMS SignerInfo's identity (issuer and serial number) is matched
	 * against the certificate set. This is identity resolution, not
	 * certificate-policy enforcement: it must not depend on keyUsage
	 * bits, so the CMS engine's global keyUsage checking is disabled for
	 * the duration and its previous state is restored afterwards — even
	 * on exceptions. Whether a certificate is ALLOWED to sign documents
	 * is a separate policy decision that lives outside this resolution.
	 *
	 * One call represents one cryptographic operation, so an unexpected
	 * SignerInfo count is rejected instead of silently resolved from the
	 * first entry.
	 *
	 * @param MappedPdfValidationResult $entry
	 * @throws ExternalSigningException when the CMS structure does not carry exactly one resolvable SignerInfo
	 */
	private function resolveSignerCertificatePem(array $entry): string {
		$binarySignature = $entry['signature']->binarySignature;
		if (! is_string($binarySignature) || $binarySignature === '') {
			throw new ExternalSigningException('The new signature carries no CMS structure to resolve.');
		}

		$keyUsageWasEnabled = X509::isCheckKeyUsageEnabled();
		X509::ignoreKeyUsage();
		try {
			$signedData = SignedData::load($binarySignature);
			$signerInfos = $signedData['content']['signerInfos'];
			$signerInfoCount = count($signerInfos);
			if ($signerInfoCount !== 1) {
				throw new ExternalSigningException(sprintf(
					'Expected exactly one SignerInfo in the new signature, found %d.',
					$signerInfoCount,
				));
			}
			$signerInfo = $signerInfos[0];
			$certificate = $signerInfo->getCertificate();
			if ($certificate === null) {
				throw new ExternalSigningException(
					'Could not resolve the signer certificate from the new signature.'
					. ' No certificate in the signature matches the SignerInfo identity.',
				);
			}
			$der = $certificate->getEncoded();
			if ($der === '' || $der === false) {
				throw new ExternalSigningException('Could not encode the resolved signer certificate.');
			}

			return "-----BEGIN CERTIFICATE-----\n"
				. chunk_split(base64_encode($der), 64, "\n")
				. "-----END CERTIFICATE-----\n";
		} finally {
			if ($keyUsageWasEnabled) {
				X509::checkKeyUsage();
			}
		}
	}

	/**
	 * Every pre-existing signature must still be present with UNCHANGED
	 * signature contents, not merely the same field name, range geometry
	 * and validity verdict. Signatures that were valid before completion
	 * must remain cryptographically valid; signatures that were already
	 * invalid before completion do not fail the operation, but they cannot
	 * be silently replaced either.
	 *
	 * @param array<string, MappedPdfValidationResult> $baselineByKey
	 * @param array<string, MappedPdfValidationResult> $completedByKey
	 * @throws ExternalSigningException on any failed retention guarantee
	 */
	private function assertPriorSignaturesRetained(array $baselineByKey, array $completedByKey): void {
		foreach ($baselineByKey as $key => $baselineEntry) {
			$completedEntry = $completedByKey[$key] ?? null;
			if ($completedEntry === null) {
				throw new ExternalSigningException('An existing signature was removed during completion.');
			}
			$baselineContents = $baselineEntry['signature']->binarySignature;
			$completedContents = $completedEntry['signature']->binarySignature;
			if (! is_string($baselineContents) || ! is_string($completedContents)
				|| $baselineContents === '' || $completedContents === ''
				|| ! hash_equals($baselineContents, $completedContents)) {
				throw new ExternalSigningException(
					'An existing signature\'s contents changed during completion.',
				);
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
