<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing;

use OCA\Libresign\Vendor\Wobeto\EmailBlur\Blur;

/**
 * Remove recipient email addresses from the public signing page response.
 * The exact identity stays on the server for OTP and CSC authorization.
 */
final class PublicSigningIdentityPresenter {
	/** @param list<array<string, mixed>> $signers
	 * @return list<array<string, mixed>>
	 */
	public static function redactSigners(array $signers): array {
		foreach ($signers as &$signer) {
			if (is_array($signer['identifyMethods'] ?? null)) {
				foreach ($signer['identifyMethods'] as &$method) {
					if (($method['method'] ?? '') !== 'email' || !self::isEmail($method['value'] ?? null)) {
						continue;
					}
					$method['maskedValue'] = self::mask($method['value']);
					$method['hashOfEmail'] = md5(strtolower($method['value']));
					$method['value'] = '';
				}
				unset($method);
			}

			foreach (['email', 'displayName'] as $key) {
				if (self::isEmail($signer[$key] ?? null)) {
					$signer[$key] = self::mask($signer[$key]);
				}
			}
			if (is_string($signer['uid'] ?? null) && str_starts_with($signer['uid'], 'email:')) {
				$uidEmail = substr($signer['uid'], strlen('email:'));
				if (self::isEmail($uidEmail)) {
					$signer['uid'] = 'email:' . self::mask($uidEmail);
				}
			}
		}
		unset($signer);
		return $signers;
	}

	/** @param list<array<string, mixed>> $files
	 * @return list<array<string, mixed>>
	 */
	public static function redactEnvelopeFiles(array $files): array {
		foreach ($files as &$file) {
			if (is_array($file['signers'] ?? null)) {
				$file['signers'] = self::redactSigners($file['signers']);
			}
		}
		unset($file);
		return $files;
	}

	/** @param array<string, mixed> $response
	 * @return array<string, mixed>
	 */
	public static function redactValidationResponse(array $response): array {
		if (is_array($response['signers'] ?? null)) {
			$response['signers'] = self::redactSigners($response['signers']);
		}
		if (is_array($response['files'] ?? null)) {
			$response['files'] = self::redactEnvelopeFiles($response['files']);
		}
		return $response;
	}

	private static function isEmail(mixed $value): bool {
		return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
	}

	private static function mask(string $email): string {
		return (new Blur(strtolower($email)))->make();
	}
}
