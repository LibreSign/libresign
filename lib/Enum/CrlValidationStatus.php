<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Enum;

/**
 * Represents the outcome of a CRL revocation check performed by
 * {@see \OCA\Libresign\Service\Crl\CrlRevocationChecker}.
 */
enum CrlValidationStatus: string {
	/** CRL fetched and certificate serial not listed as revoked. */
	case VALID = 'valid';
	/** Certificate serial found in the CRL – certificate is revoked. */
	case REVOKED = 'revoked';
	/** Admin disabled external CRL validation; local CRLs were not checked. */
	case DISABLED = 'disabled';
	/** All CRL Distribution Point URLs were unreachable. */
	case URLS_INACCESSIBLE = 'urls_inaccessible';
	/** A download or parse error occurred while fetching the CRL. */
	case VALIDATION_ERROR = 'validation_error';
	/** CRL was parsed but the check was inconclusive. */
	case VALIDATION_FAILED = 'validation_failed';
	/** The crlDistributionPoints extension is present but contains no URLs. */
	case NO_URLS = 'no_urls';
	/** The certificate has no crlDistributionPoints extension at all. */
	case MISSING = 'missing';
}
