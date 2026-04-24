<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\SignEngine;

use LibreSign\PdfSignatureValidator\Parser\PdfSignatureExtractor;
use LibreSign\PdfSignatureValidator\Model\ValidationReason;
use LibreSign\PdfSignatureValidator\Model\ValidationResult;
use LibreSign\PdfSignatureValidator\Model\ValidationState;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\CertificateEngine\OrderCertificatesTrait;
use OCA\Libresign\Handler\DocMdpHandler;
use OCA\Libresign\Handler\FooterHandler;
use OCA\Libresign\Service\CaIdentifierService;
use OCA\Libresign\Service\Crl\CrlService;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\Signature\PdfSignatureValidationService;
use OCP\Files\File;
use OCP\IAppConfig;
use OCP\IL10N;
use phpseclib3\File\ASN1;
use Psr\Log\LoggerInterface;

class Pkcs12Handler extends SignEngineHandler {
	use OrderCertificatesTrait;
	protected string $certificate = '';
	private ?JSignPdfHandler $jSignPdfHandler = null;
	private ?PhpNativeHandler $phpNativeHandler = null;
	private string $rootCertificatePem = '';
	private bool $isLibreSignFile = false;

	public function __construct(
		private FolderService $folderService,
		private IAppConfig $appConfig,
		protected CertificateEngineFactory $certificateEngineFactory,
		private IL10N $l10n,
		private FooterHandler $footerHandler,
		private LoggerInterface $logger,
		private CaIdentifierService $caIdentifierService,
		private DocMdpHandler $docMdpHandler,
		private CrlService $crlService,
		private PdfSignatureValidationService $pdfSignatureValidationService,
		private PdfSignatureExtractor $pdfSignatureExtractor,
	) {
		parent::__construct($l10n, $folderService, $logger);
	}

	/**
	 * @throws LibresignException When is not a signed file
	 */
	private function getSignatures($resource): iterable {
		rewind($resource);
		$content = stream_get_contents($resource);

		preg_match_all('/\/Contents\s*<([0-9a-fA-F]+)>/', $content, $contents, PREG_OFFSET_CAPTURE);

		if (empty($contents[1])) {
			throw new LibresignException($this->l10n->t('Unsigned file.'));
		}

		$seenHexSignatures = [];
		foreach ($contents[1] as $match) {
			$signatureHex = $match[0];

			if (isset($seenHexSignatures[$signatureHex])) {
				continue;
			}
			$seenHexSignatures[$signatureHex] = true;

			$decodedSignature = @hex2bin($signatureHex);
			if ($decodedSignature === false) {
				yield null;
				continue;
			}
			yield $decodedSignature;
		}
	}

	public function setIsLibreSignFile(): void {
		$this->isLibreSignFile = true;
	}

	/**
	 * @param resource $resource
	 * @throws LibresignException When is not a signed file
	 * @return array
	 */
	#[\Override]
	public function getCertificateChain($resource): array {
		$certificates = [];
		$nativeMetadata = array_values($this->extractNativeSignatureMetadata($resource));
		rewind($resource);
		$nativeValidation = array_values($this->pdfSignatureValidationService->validateFromResource($resource));
		$index = 0;

		foreach ($this->getSignatures($resource) as $signature) {
			$metadata = $nativeMetadata[$index] ?? [];
			$validation = $nativeValidation[$index] ?? [];
			$index++;

			if (!$signature) {
				continue;
			}

			$result = $this->processSignature(
				$resource,
				$signature,
				$metadata,
				$validation
			);

			if (empty($result['chain'])) {
				continue;
			}

			$certificates[] = $result;
		}
		return $certificates;
	}

	private function processSignature($resource, ?string $signature, array $metadata = [], array $validation = []): array {
		$result = [];

		if (!$signature) {
			$result['chain'][0]['signature_validation'] = [
				'id' => 3,
				'label' => $this->l10n->t('Digest mismatch.'),
			];
			return $result;
		}

		$decoded = ASN1::decodeBER($signature);
		$result = $this->extractTimestampData($decoded, $result);

		$chain = $this->extractCertificateChain($signature);
		if (!empty($chain)) {
			$result['chain'] = $this->orderCertificates($chain);
			$result = $this->enrichLeafWithNativeData($result, $metadata, $validation);
		}

		$result = $this->extractDocMdpData($resource, $result);

		$result = $this->applyLibreSignRootCAFlag($result);
		return $result;
	}

	private function applyLibreSignRootCAFlag(array $signer): array {
		if (empty($signer['chain'])) {
			return $signer;
		}

		foreach ($signer['chain'] as $key => $cert) {
			if ($cert['isLibreSignRootCA']
				&& isset($cert['certificate_validation'])
				&& $cert['certificate_validation']['id'] !== 1
			) {
				$signer['chain'][$key]['certificate_validation'] = [
					'id' => 1,
					'label' => $this->l10n->t('Certificate is trusted.'),
				];
			}
		}

		return $signer;
	}


	private function extractDocMdpData($resource, array $result): array {
		if (empty($result['chain'])) {
			return $result;
		}

		$docMdpData = $this->docMdpHandler->extractDocMdpData($resource);
		return array_merge($result, $docMdpData);
	}

	private function extractTimestampData(array $decoded, array $result): array {
		$tsa = new TSA();

		try {
			$timestampData = $tsa->extract($decoded);
			if (!empty($timestampData['genTime']) || !empty($timestampData['policy']) || !empty($timestampData['serialNumber'])) {
				$result['timestamp'] = $timestampData;
			}
		} catch (\Throwable) {
		}

		if (!isset($result['signingTime']) || !$result['signingTime'] instanceof \DateTime) {
			$result['signingTime'] = $tsa->getSigninTime($decoded);
		}
		return $result;
	}

	private function extractCertificateChain(string $signature): array {
		$pkcs7PemSignature = $this->der2pem($signature);
		$pemCertificates = [];

		if (!openssl_pkcs7_read($pkcs7PemSignature, $pemCertificates)) {
			return [];
		}

		$chain = [];
		$isLibreSignRootCA = false;
		$certificateEngine = $this->getCertificateEngine();

		foreach ($pemCertificates as $index => $pemCertificate) {
			$parsed = $certificateEngine->parseCertificate($pemCertificate);
			if ($parsed) {
				$parsed['signature_validation'] = [
					'id' => 1,
					'label' => $this->l10n->t('Signature is valid.'),
				];
				if (!$isLibreSignRootCA) {
					$isLibreSignRootCA = $this->isLibreSignRootCA($pemCertificate, $parsed);
				}
				$parsed['isLibreSignRootCA'] = $isLibreSignRootCA;
				$chain[$index] = $parsed;
			}
		}
		if ($isLibreSignRootCA || $this->isLibreSignFile) {
			foreach ($chain as &$cert) {
				$cert['isLibreSignRootCA'] = true;
			}
		}

		return $chain;
	}

	private function isLibreSignRootCA(string $certificate, array $parsed): bool {
		$crlUrls = $parsed['crl_urls'] ?? [];
		$rootCertificatePem = is_array($crlUrls) ? $this->crlService->getRootCertificateFromCrlUrls($crlUrls) : '';

		if (empty($rootCertificatePem)) {
			$rootCertificatePem = $this->getRootCertificatePem();
		}

		if (empty($rootCertificatePem)) {
			return false;
		}

		$rootFingerprint = openssl_x509_fingerprint($rootCertificatePem, 'sha256');
		$fingerprint = openssl_x509_fingerprint($certificate, 'sha256');
		if ($rootFingerprint === $fingerprint) {
			return true;
		}

		return $this->hasLibreSignCaId($parsed);
	}

	private function hasLibreSignCaId(array $parsed): bool {
		$instanceId = $this->appConfig->getValueString(Application::APP_ID, 'instance_id', '');
		if (strlen($instanceId) !== 10 || !isset($parsed['subject']['OU'])) {
			return false;
		}

		$organizationalUnits = $parsed['subject']['OU'];
		if (is_string($organizationalUnits)) {
			$organizationalUnits = [$organizationalUnits];
		}

		foreach ($organizationalUnits as $ou) {
			$ou = trim((string)$ou);
			if ($this->caIdentifierService->isValidCaId($ou, $instanceId)) {
				return true;
			}
		}

		return false;
	}

	private function getRootCertificatePem(): string {
		if (!empty($this->rootCertificatePem)) {
			return $this->rootCertificatePem;
		}
		$configPath = $this->appConfig->getValueString(Application::APP_ID, 'config_path');
		if (empty($configPath)
			|| !is_dir($configPath)
			|| !is_readable($configPath . DIRECTORY_SEPARATOR . 'ca.pem')
		) {
			return '';
		}
		$rootCertificatePem = file_get_contents($configPath . DIRECTORY_SEPARATOR . 'ca.pem');
		if ($rootCertificatePem === false) {
			return '';
		}
		$this->rootCertificatePem = $rootCertificatePem;
		return $this->rootCertificatePem;
	}

	private function enrichLeafWithNativeData(array $result, array $metadata, array $validation): array {
		if (empty($result['chain'])) {
			return $result;
		}

		$leaf = &$result['chain'][0];

		foreach (['field', 'range', 'signature_type', 'signing_hash_algorithm', 'covers_entire_document'] as $key) {
			if (array_key_exists($key, $metadata)) {
				$leaf[$key] = $metadata[$key];
			}
		}

		if (isset($validation['signatureValidation']) && is_array($validation['signatureValidation'])) {
			$signatureValidation = $validation['signatureValidation'];

			// Keep legacy OpenSSL result when native validator reports this known false-positive.
			if (!$this->isDigestMismatchSignatureValidation($validation)) {
				$leaf['signature_validation'] = $signatureValidation;
			}
		}

		if (isset($validation['certificateValidation']) && is_array($validation['certificateValidation'])) {
			$leaf['certificate_validation'] = $validation['certificateValidation'];
		}

		if (!isset($leaf['certificate_validation'])) {
			$leaf['certificate_validation'] = [
				'id' => 3,
				'label' => $this->l10n->t('Certificate issuer is unknown.'),
			];
		}

		return $result;
	}

	/**
	 * signer engines can produce signatures that the native validator currently flags as digest mismatch.
	 * In this case we preserve the legacy validation computed from the PKCS#7 signature.
	 */
	private function isDigestMismatchSignatureValidation(array $validation): bool {
		$rawSignatureValidation = $validation['raw']['signature'] ?? null;
		if ($rawSignatureValidation instanceof ValidationResult) {
			return $rawSignatureValidation->reasonCode === ValidationReason::DIGEST_MISMATCH
				|| $rawSignatureValidation->state === ValidationState::DIGEST_MISMATCH;
		}

		$signatureValidation = $validation['signatureValidation'] ?? null;
		return is_array($signatureValidation) && ($signatureValidation['id'] ?? null) === 3;
	}

	/**
	 * @param resource $resource
	 * @return array<int, array{field: ?string, range: ?array{offset1: int, offset2: int, length1: int, length2: int}, signature_type: ?string, covers_entire_document: bool}>
	 */
	private function extractNativeSignatureMetadata($resource): array {
		rewind($resource);
		$content = stream_get_contents($resource);
		if (!is_string($content) || $content === '') {
			return [];
		}

		try {
			$signatures = $this->pdfSignatureExtractor->extractFromString($content);
		} catch (\Throwable) {
			return [];
		}
		$metadata = [];

		foreach ($signatures as $index => $signature) {
			$metadata[$index] = [
				'field' => $signature->metadata->field,
				'range' => $signature->metadata->range,
				'signature_type' => $signature->metadata->signatureType,
				'covers_entire_document' => $signature->metadata->coversEntireDocument,
			];
		}

		return $metadata;
	}

	private function der2pem($derData) {
		$pem = chunk_split(base64_encode((string)$derData), 64, "\n");
		$pem = "-----BEGIN CERTIFICATE-----\n" . $pem . "-----END CERTIFICATE-----\n";
		return $pem;
	}

	private function getHandler(): SignEngineHandler {
		$sign_engine = $this->appConfig->getValueString(Application::APP_ID, 'signature_engine', 'JSignPdf');
		$property = lcfirst($sign_engine) . 'Handler';
		if (!property_exists($this, $property)) {
			throw new LibresignException($this->l10n->t('Invalid Sign engine.'), 400);
		}
		$classHandler = 'OCA\\Libresign\\Handler\\SignEngine\\' . ucfirst($property);
		if (!$this->$property instanceof $classHandler) {
			$this->$property = \OCP\Server::get($classHandler);
		}
		return $this->$property;
	}

	#[\Override]
	public function sign(): File {
		$this->beforeSign();

		$signedContent = $this->getHandler()
			->setCertificate($this->getCertificate())
			->setInputFile($this->getInputFile())
			->setPassword($this->getPassword())
			->setSignatureParams($this->getSignatureParams())
			->setVisibleElements($this->getVisibleElements())
			->getSignedContent();
		$this->getInputFile()->putContent($signedContent);
		return $this->getInputFile();
	}

	public function isHandlerOk(): bool {
		return $this->certificateEngineFactory->getEngine()->isSetupOk();
	}
}
