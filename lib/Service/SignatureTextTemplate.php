<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCP\IL10N;

final class SignatureTextTemplate {
	private function __construct() {
	}

	public static function translated(IL10N $l10n, bool $collectMetadata): string {
		if ($collectMetadata) {
			// TRANSLATORS Variables enclosed in double curly braces {{variableName}} are template placeholders.
			//
			// DO NOT translate or remove these variables:
			// - {{SignerCommonName}}
			// - {{IssuerCommonName}}
			// - {{ServerSignatureDate}}
			// - {{SignerIP}}
			// - {{SignerUserAgent}}
			//
			// Only translate the text outside the curly braces, such as:
			// - "Signed with LibreSign"
			// - "Issuer:"
			// - "Date:"
			// - "IP:"
			// - "User agent:"
			return $l10n->t(
				"Signed with LibreSign\n"
				. "{{SignerCommonName}}\n"
				. "Issuer: {{IssuerCommonName}}\n"
				. "Date: {{ServerSignatureDate}}\n"
				. "IP: {{SignerIP}}\n"
				. 'User agent: {{SignerUserAgent}}'
			);
		}

		// TRANSLATORS Variables enclosed in double curly braces {{variableName}} are template placeholders.
		//
		// DO NOT translate or remove these variables:
		// - {{SignerCommonName}}
		// - {{IssuerCommonName}}
		// - {{ServerSignatureDate}}
		//
		// Only translate the text outside the curly braces, such as:
		// - "Signed with LibreSign"
		// - "Issuer:"
		// - "Date:"
		return $l10n->t(
			"Signed with LibreSign\n"
			. "{{SignerCommonName}}\n"
			. "Issuer: {{IssuerCommonName}}\n"
			. 'Date: {{ServerSignatureDate}}'
		);
	}
}
