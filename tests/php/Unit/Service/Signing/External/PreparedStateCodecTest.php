<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing\External;

use OCA\Libresign\Service\Signature\PreparedExternalOperation;
use OCA\Libresign\Service\Signing\External\PreparedStateCodec;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\ExternalSigningPayload;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\HashAlgorithm;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\PreparedExternalSignature;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SignatureAlgorithm;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SignatureEncoding;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SigningInputType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests: no database, Nextcloud server or mocked infrastructure.
 */
final class PreparedStateCodecTest extends TestCase {
	/** @return array<string, int|string> */
	private static function validData(): array {
		return [
			'version' => PreparedStateCodec::FORMAT,
			'inputBase64' => base64_encode('synthetic-digest'),
			'inputType' => SigningInputType::Digest->value,
			'digestAlgorithm' => HashAlgorithm::Sha256->value,
			'signatureAlgorithm' => SignatureAlgorithm::RsaPkcs1V15->value,
			'signatureEncoding' => SignatureEncoding::RsaPkcs1->value,
			'state' => 'opaque-server-side-preparation',
			'preparationInputSha256' => hash('sha256', 'synthetic-pdf'),
		];
	}

	public function testRoundTripPreservesExactlyThePreparedOperation(): void {
		$data = self::validData();
		$prepared = new PreparedExternalOperation(
			new PreparedExternalSignature(
				new ExternalSigningPayload(
					(string)$data['inputBase64'],
					SigningInputType::Digest,
					HashAlgorithm::Sha256,
					SignatureAlgorithm::RsaPkcs1V15,
					SignatureEncoding::RsaPkcs1,
				),
				(string)$data['state'],
			),
			(string)$data['preparationInputSha256'],
		);

		$json = PreparedStateCodec::encode($prepared);
		self::assertSame($data, json_decode($json, true, 16, JSON_THROW_ON_ERROR));
		$restored = PreparedStateCodec::decode($json);
		self::assertEquals($prepared, $restored);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalidStates(): array {
		$valid = self::validData();
		$cases = [
			'unsupported schema version' => array_replace($valid, ['version' => 2]),
			'missing state' => array_diff_key($valid, ['state' => true]),
			'field order changed' => array_reverse($valid, true),
			'non-string state' => array_replace($valid, ['state' => 123]),
			'empty opaque state' => array_replace($valid, ['state' => '']),
			'invalid base64' => array_replace($valid, ['inputBase64' => '***']),
			'invalid document hash' => array_replace($valid, ['preparationInputSha256' => str_repeat('z', 64)]),
			'unknown signature algorithm' => array_replace($valid, ['signatureAlgorithm' => 'invented']),
		];
		$values = [
			'malformed JSON' => ['{"version":1'],
			'JSON list instead of object' => ['[]'],
			'JSON scalar instead of object' => ['null'],
		];
		foreach ($cases as $label => $value) {
			$values[$label] = [json_encode($value, JSON_THROW_ON_ERROR)];
		}
		return $values;
	}

	#[DataProvider('invalidStates')]
	public function testUntrustedStoredStateIsRejectedWithoutLeakingItsContents(string $json): void {
		$this->expectException(\UnexpectedValueException::class);
		$this->expectExceptionMessage('Prepared signing session cannot be restored');
		PreparedStateCodec::decode($json);
	}

	public function testRecoveryCertificateIsStoredOnlyInVersionTwoAndDoesNotChangePreparedOperation(): void {
		$data = self::validData();
		$prepared = new PreparedExternalOperation(
			new PreparedExternalSignature(
				new ExternalSigningPayload(
					(string)$data['inputBase64'],
					SigningInputType::Digest,
					HashAlgorithm::Sha256,
					SignatureAlgorithm::RsaPkcs1V15,
					SignatureEncoding::RsaPkcs1,
				),
				(string)$data['state'],
			),
			(string)$data['preparationInputSha256'],
		);
		$pem = "-----BEGIN CERTIFICATE-----\nsynthetic-public-data\n-----END CERTIFICATE-----";
		$encoded = PreparedStateCodec::encode($prepared, $pem);
		$fields = json_decode($encoded, true, 16, JSON_THROW_ON_ERROR);
		self::assertSame(PreparedStateCodec::RECOVERY_FORMAT, $fields['version']);
		self::assertSame($pem, PreparedStateCodec::recoveryCertificate($encoded));
		self::assertEquals($prepared, PreparedStateCodec::decode($encoded));
		self::assertNull(PreparedStateCodec::recoveryCertificate(PreparedStateCodec::encode($prepared)));
	}

	public function testRecoveryFormatRejectsMissingOrMalformedCertificateField(): void {
		$versionTwo = array_replace(self::validData(), ['version' => PreparedStateCodec::RECOVERY_FORMAT]);
		$versionTwo['certificatePem'] = '';
		$this->expectException(\UnexpectedValueException::class);
		PreparedStateCodec::recoveryCertificate(json_encode($versionTwo, JSON_THROW_ON_ERROR));
	}

	public function testDecodingRejectsOversizedStateBeforeParsing(): void {
		$this->expectException(\UnexpectedValueException::class);
		PreparedStateCodec::decode(str_repeat('x', 8 * 1024 * 1024 + 1));
	}

	public function testEncodingRejectsOversizedOpaquePreparation(): void {
		$valid = self::validData();
		$prepared = new PreparedExternalOperation(
			new PreparedExternalSignature(
				new ExternalSigningPayload(
					(string)$valid['inputBase64'],
					SigningInputType::Digest,
					HashAlgorithm::Sha256,
					SignatureAlgorithm::RsaPkcs1V15,
					SignatureEncoding::RsaPkcs1,
				),
				str_repeat('x', 8 * 1024 * 1024),
			),
			(string)$valid['preparationInputSha256'],
		);
		$this->expectException(\InvalidArgumentException::class);
		PreparedStateCodec::encode($prepared);
	}
}
