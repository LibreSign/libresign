<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing\External;

use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\PdfContentDto;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SignatureActorDto;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SignatureMetadataDto;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SignatureProfile;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SigningCertificateDto;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SigningContextDto;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SigningOptionsDto;

/** Pure preparation of the supported synthetic CSC signing context. */
final class ContextFactory {
	public static function create(string $pdf, string $signerName, string $profile, string $certificatePem): SigningContextDto {
		$name = trim($signerName);
		if ($name === '') {
			$name = 'Synthetic CSC signer';
		}
		$signatureProfile = match ($profile) {
			'pdf-basic' => SignatureProfile::PdfBasic,
			'pades-baseline-b' => SignatureProfile::PadesBaselineB,
			default => throw new \DomainException('Unsupported external signing PDF profile'),
		};
		return new SigningContextDto(
			new PdfContentDto($pdf),
			new SigningOptionsDto(
				metadata: new SignatureMetadataDto(actor: new SignatureActorDto(name: mb_substr($name, 0, 128))),
				signatureProfile: $signatureProfile,
				useDefaultAppearance: false,
			),
			new SigningCertificateDto($certificatePem),
		);
	}
}
