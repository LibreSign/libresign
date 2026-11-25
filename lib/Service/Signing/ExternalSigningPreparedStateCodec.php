<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing;

use OCA\Libresign\Service\Signature\PreparedExternalOperation;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\ExternalSigningPayload;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\HashAlgorithm;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\PreparedExternalSignature;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SignatureAlgorithm;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SignatureEncoding;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SigningInputType;

/**
 * Versioned, bounded serializer for trusted external signing preparations.
 *
 * This class neither encrypts nor stores state: the session store owns the
 * ciphertext boundary and transaction. Pure serialization can be tested
 * without a Nextcloud runtime, database, or cryptographic service mock.
 */
final class ExternalSigningPreparedStateCodec {
	public const FORMAT = 1;
	private const MAX_JSON_BYTES = 8 * 1024 * 1024;

	public static function encode(PreparedExternalOperation $prepared): string {
		$payload = $prepared->upstream->payload;
		$data = [
			'version' => self::FORMAT,
			'inputBase64' => $payload->inputBase64,
			'inputType' => $payload->inputType->value,
			'digestAlgorithm' => $payload->digestAlgorithm->value,
			'signatureAlgorithm' => $payload->signatureAlgorithm->value,
			'signatureEncoding' => $payload->signatureEncoding->value,
			'state' => $prepared->upstream->state,
			'preparationInputSha256' => $prepared->preparationInputSha256,
		];
		$json = json_encode($data, JSON_THROW_ON_ERROR);
		if (strlen($json) > self::MAX_JSON_BYTES) {
			throw new \InvalidArgumentException('Prepared signing state exceeds safe storage limit');
		}
		return $json;
	}

	public static function decode(#[\SensitiveParameter] string $json): PreparedExternalOperation {
		try {
			if (strlen($json) > self::MAX_JSON_BYTES) {
				throw new \UnexpectedValueException('Oversized prepared state');
			}
			$data = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
			if (!is_array($data) || array_keys($data) !== [
				'version', 'inputBase64', 'inputType', 'digestAlgorithm',
				'signatureAlgorithm', 'signatureEncoding', 'state', 'preparationInputSha256',
			] || $data['version'] !== self::FORMAT) {
				throw new \UnexpectedValueException('Unsupported prepared state schema');
			}
			foreach (array_slice($data, 1) as $value) {
				if (!is_string($value)) {
					throw new \UnexpectedValueException('Invalid prepared state field');
				}
			}
			if (!preg_match('/^[a-f0-9]{64}$/D', $data['preparationInputSha256'])
				|| $data['state'] === '' || !is_string(base64_decode($data['inputBase64'], true))) {
				throw new \UnexpectedValueException('Invalid prepared state');
			}
			$payload = new ExternalSigningPayload(
				$data['inputBase64'],
				SigningInputType::from($data['inputType']),
				HashAlgorithm::from($data['digestAlgorithm']),
				SignatureAlgorithm::from($data['signatureAlgorithm']),
				SignatureEncoding::from($data['signatureEncoding']),
			);
			return new PreparedExternalOperation(
				new PreparedExternalSignature($payload, $data['state']),
				$data['preparationInputSha256'],
			);
		} catch (\Throwable) {
			// Persisted state is never trusted, including encrypted/corrupted
			// content from older or unsupported versions.
			throw new \UnexpectedValueException('Prepared signing session cannot be restored');
		}
	}
}
