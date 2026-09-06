<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\SignEngine;

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
use OCA\Libresign\Vendor\LibreSign\PdfSignatureValidator\Model\ExtractedSignature;
use OCA\Libresign\Vendor\LibreSign\PdfSignatureValidator\Model\TimestampToken;
use OCA\Libresign\Vendor\phpseclib4\Exception\UnexpectedValueException;
use OCA\Libresign\Vendor\phpseclib4\File\ASN1;
use OCP\Files\File;
use OCP\IAppConfig;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class Pkcs12Handler extends SignEngineHandler {
	use OrderCertificatesTrait;
	protected string $certificate = '';
	private ?JSignPdfHandler $jSignPdfHandler = null;
	private ?PhpNativeHandler $phpNativeHandler = null;
	private string $rootCertificatePem = '';
	private bool $isLibreSignFile = false;
	private ?string $policyUserIdForValidation = null;

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
	) {
		parent::__construct($l10n, $folderService, $logger);
	}

	#[\Override]
	protected function getCertificateEngineFactory(): CertificateEngineFactory {
		return $this->certificateEngineFactory;
	}

	public function setIsLibreSignFile(): void {
		$this->isLibreSignFile = true;
	}

	public function setPolicyUserIdForValidation(?string $userId): self {
		$this->policyUserIdForValidation = is_string($userId) && trim($userId) !== ''
			? trim($userId)
			: null;

		return $this;
	}

	/**
	 * @param resource $resource
	 * @throws LibresignException When is not a signed file
	 * @return array
	 */
	#[\Override]
	public function getCertificateChain($resource): array {
		$certificates = [];
		$certificateEngine = $this->getCertificateEngine();
		$certificateEngine->setPolicyUserIdForValidation($this->policyUserIdForValidation);

		try {
			rewind($resource);
			$validationResults = array_values(
				$this->pdfSignatureValidationService->validateFromResource($resource)
			);

			if ($validationResults === []) {
				throw new LibresignException($this->l10n->t('Unsigned file.'));
			}

			foreach ($validationResults as $validation) {
				$signature = $validation['signature'] ?? null;
				if (!$signature instanceof ExtractedSignature) {
					continue;
				}

				$result = $this->processSignature(
					$resource,
					$signature,
					$validation,
				);

				if (empty($result['chain'])) {
					continue;
				}

				$certificates[] = $result;
			}
		} finally {
			$certificateEngine->setPolicyUserIdForValidation(null);
			$this->policyUserIdForValidation = null;
		}

		return $certificates;
	}

	private function processSignature(
		$resource,
		ExtractedSignature $signature,
		array $validation = [],
	): array {
		$binarySignature = $signature->binarySignature;
		if ($binarySignature === null || $binarySignature === '') {
			return $this->enrichLeafWithNativeData(
				['chain' => [[]]],
				$signature,
				$validation,
			);
		}

		$result = [];

		try {
			$decoded = ASN1::decodeBER($binarySignature);
		} catch (UnexpectedValueException) {
			$decoded = null;
		}

		$result = $this->extractSigningTime($decoded, $result);

		$timestamp = $validation['timestamp'] ?? null;
		if ($timestamp instanceof TimestampToken) {
			$result['timestamp'] = $this->mapTimestampToken($timestamp);
		}

		$pemCertificates = $validation['certificates'] ?? [];
		if (!is_array($pemCertificates)) {
			$pemCertificates = [];
		}

		$chain = $this->extractCertificateChain($pemCertificates);
		if (!empty($chain)) {
			$result['chain'] = $this->orderCertificates($chain);
			$result = $this->enrichLeafWithNativeData(
				$result,
				$signature,
				$validation,
			);
		}

		$result = $this->extractDocMdpData($resource, $result);

		return $this->applyLibreSignRootCAFlag($result);
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
					// TRANSLATORS Status label on LibreSign signature validation when the signer certificate chains to LibreSign's own root CA and is accepted.
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

	private function extractSigningTime(?array $decoded, array $result): array {
		if ($decoded === null) {
			return $result;
		}

		$tsa = new TSA();
		$signingTime = $tsa->getSigninTime($decoded);
		if ($signingTime instanceof \DateTime) {
			$result['signingTime'] = $signingTime;
		}

		return $result;
	}

	private function mapTimestampToken(TimestampToken $timestamp): array {
		$result = [
			'genTime' => $timestamp->generatedAt,
			'policy' => $timestamp->policyOid,
			'serialNumber' => $timestamp->serialNumber,
			'cnHints' => $timestamp->certificateSubject,
			'tsaName' => $timestamp->certificateSubject['commonName'] ?? null,
		];

		return array_filter(
			$result,
			static fn (mixed $value): bool => $value !== null
				&& $value !== ''
				&& $value !== [],
		);
	}

	/**
	 * @param list<string> $pemCertificates
	 */
	private function extractCertificateChain(array $pemCertificates): array {
		$chain = [];
		$isLibreSignRootCA = false;
		$certificateEngine = $this->getCertificateEngine();

		foreach ($pemCertificates as $index => $pemCertificate) {
			if (!is_string($pemCertificate) || $pemCertificate === '') {
				continue;
			}

			$parsed = $certificateEngine->parseCertificate($pemCertificate);
			if (!$parsed) {
				continue;
			}

			if (!$isLibreSignRootCA) {
				$isLibreSignRootCA = $this->isLibreSignRootCA(
					$pemCertificate,
					$parsed,
				);
			}

			$parsed['isLibreSignRootCA'] = $isLibreSignRootCA;
			$chain[$index] = $parsed;
		}

		if ($isLibreSignRootCA || $this->isLibreSignFile) {
			foreach ($chain as &$cert) {
				$cert['isLibreSignRootCA'] = true;
			}
			unset($cert);
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
		$caPemPath = $configPath . DIRECTORY_SEPARATOR . 'ca.pem';

		if (empty($configPath)
			|| !is_dir($configPath)
			|| !is_readable($caPemPath)
		) {
			return '';
		}

		$rootCertificatePem = file_get_contents($caPemPath);
		if ($rootCertificatePem === false) {
			return '';
		}
		$this->rootCertificatePem = $rootCertificatePem;
		return $this->rootCertificatePem;
	}

	private function enrichLeafWithNativeData(
		array $result,
		ExtractedSignature $signature,
		array $validation,
	): array {
		if (empty($result['chain'])) {
			return $result;
		}

		$leaf = &$result['chain'][0];
		$metadata = $signature->metadata;

		$leaf['field'] = $metadata->field;
		$leaf['range'] = $metadata->range;
		$leaf['signature_type'] = $metadata->signatureType;
		$leaf['signing_hash_algorithm'] = $signature->hashAlgorithm;
		$leaf['covers_entire_document'] = $metadata->coversEntireDocument;

		if ($metadata->documentModificationState !== null) {
			$leaf['document_modification_state']
				= $metadata->documentModificationState->value;
		}

		if (
			isset($validation['signatureValidation'])
			&& is_array($validation['signatureValidation'])
		) {
			$leaf['signature_validation'] = $validation['signatureValidation'];
		}

		if (
			isset($validation['certificateValidation'])
			&& is_array($validation['certificateValidation'])
		) {
			$leaf['certificate_validation'] = $validation['certificateValidation'];
		}

		if (!isset($leaf['certificate_validation'])) {
			$leaf['certificate_validation'] = [
				'id' => 3,
				// TRANSLATORS Status label on LibreSign signature validation when the signer certificate's issuing CA is not in the trusted store.
				'label' => $this->l10n->t('Certificate issuer is unknown.'),
			];
		}

		return $result;
	}

	private function getHandler(): SignEngineHandler {
		$sign_engine = $this->appConfig->getValueString(Application::APP_ID, 'signature_engine', 'JSignPdf');
		$property = lcfirst($sign_engine) . 'Handler';
		if (!property_exists($this, $property)) {
			// TRANSLATORS API/config error when LibreSign's signature_engine setting names a backend that is not available (for example a mistyped JSignPdf/native engine).
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
