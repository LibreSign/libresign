<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\SignEngine\JSignPdf;

use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\SignatureHashAlgorithm\SignatureHashAlgorithmPolicy;
use OCA\Libresign\Service\Policy\Provider\Tsa\TsaPolicy;
use OCA\Libresign\Service\Policy\Provider\Tsa\TsaPolicyValue;

/**
 * Resolves which hash algorithm JSignPdf has to use.
 *
 * The algorithm that signs the document depends on the PDF version, so it is
 * not a valid answer for the other hashes JSignPdf takes: each one needs its
 * own method here.
 */
class HashAlgorithmResolver {
	private const float MIN_PDF_VERSION_SHA256 = 1.6;
	private const float MIN_PDF_VERSION_SHA1_REJECT = 1.7;
	private const string DEFAULT_ALGORITHM = 'SHA256';
	/** @var string[] */
	private const array SUPPORTED_ALGORITHMS = ['SHA1', 'SHA256', 'SHA384', 'SHA512', 'RIPEMD160'];

	public function __construct(
		private PolicyService $policyService,
	) {
	}

	/**
	 * Algorithm used to sign a document with the given PDF version.
	 *
	 * @param float|null $pdfVersion null when the content carries no readable PDF header
	 */
	public function forSignature(?float $pdfVersion): string {
		$configuredAlgorithm = $this->getConfiguredAlgorithm();
		/**
		 * Need to respect the follow code:
		 * https://github.com/intoolswetrust/jsignpdf/blob/JSignPdf_2_2_2/jsignpdf/src/main/java/net/sf/jsignpdf/types/HashAlgorithm.java#L46-L47
		 */
		if ($pdfVersion === null) {
			return $this->validate($configuredAlgorithm);
		}

		return $this->forPdfVersion($pdfVersion, $configuredAlgorithm);
	}

	/**
	 * Algorithm of the timestamp query, taken from the TSA policy.
	 *
	 * It is never derived from the signature: that one depends on the PDF
	 * version and would ask the authority for SHA1 whenever the document is
	 * older than PDF 1.6, which is the rejection reported in #8145.
	 */
	public function forTsa(): string {
		$tsaSettings = TsaPolicyValue::decode($this->policyService->resolve(TsaPolicy::KEY)->getEffectiveValue());

		// JSignPdf spells the TSA algorithm with a hyphen, unlike --hash-algorithm.
		return match ($tsaSettings['hash_algorithm']) {
			'SHA384' => 'SHA-384',
			'SHA512' => 'SHA-512',
			default => 'SHA-256',
		};
	}

	/**
	 * PDFs older than 1.6 have to be upgraded before JSignPdf accepts SHA-256.
	 */
	public function requiresPdfVersionUpgradeForSha256(float $pdfVersion): bool {
		if ($pdfVersion >= self::MIN_PDF_VERSION_SHA256) {
			return false;
		}

		return $this->getConfiguredAlgorithm() === self::DEFAULT_ALGORITHM;
	}

	private function forPdfVersion(float $pdfVersion, string $configuredAlgorithm): string {
		// Legacy compatibility: JSignPdf still requires SHA1 for very old PDFs (< 1.6).
		// The policy still exposes SHA1 for supported legacy workflows, and the runtime
		// must continue enforcing this fallback for ancient PDFs that JSignPdf cannot sign otherwise.
		if ($pdfVersion < self::MIN_PDF_VERSION_SHA256) {
			return 'SHA1';
		}
		if ($pdfVersion < self::MIN_PDF_VERSION_SHA1_REJECT) {
			return self::DEFAULT_ALGORITHM;
		}
		if ($configuredAlgorithm === 'SHA1') {
			return self::DEFAULT_ALGORITHM;
		}

		return $this->validate($configuredAlgorithm);
	}

	private function validate(string $algorithm): string {
		return in_array($algorithm, self::SUPPORTED_ALGORITHMS, true) ? $algorithm : self::DEFAULT_ALGORITHM;
	}

	private function getConfiguredAlgorithm(): string {
		return (string)$this->policyService->resolve(SignatureHashAlgorithmPolicy::KEY)->getEffectiveValue();
	}
}
