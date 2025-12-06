<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\SignEngine;

use DateTime;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\CertificateEngine\OrderCertificatesTrait;
use OCA\Libresign\Handler\DocMdpHandler;
use OCA\Libresign\Handler\FooterHandler;
use OCA\Libresign\Service\CaIdentifierService;
use OCA\Libresign\Service\FolderService;
use OCP\Files\File;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\ITempManager;
use phpseclib3\File\ASN1;
use Psr\Log\LoggerInterface;

class Pkcs12Handler extends SignEngineHandler {
	use OrderCertificatesTrait;
	protected string $certificate = '';
	private array $signaturesFromPoppler = [];
	private ?JSignPdfHandler $jSignPdfHandler = null;
	private string $rootCertificatePem = '';
	private bool $isLibreSignFile = false;

	public function __construct(
		private FolderService $folderService,
		private IAppConfig $appConfig,
		protected CertificateEngineFactory $certificateEngineFactory,
		private IL10N $l10n,
		private FooterHandler $footerHandler,
		private ITempManager $tempManager,
		private LoggerInterface $logger,
		private CaIdentifierService $caIdentifierService,
		private DocMdpHandler $docMdpHandler,
	) {
		parent::__construct($l10n, $folderService, $logger);
	}

	/**
	 * @throws LibresignException When is not a signed file
	 */
	private function getSignatures($resource): iterable {
		rewind($resource);
		$content = stream_get_contents($resource);
		preg_match_all(
			'/ByteRange\s*\[\s*(?<offset1>\d+)\s+(?<length1>\d+)\s+(?<offset2>\d+)\s+(?<length2>\d+)\s*\]/',
			$content,
			$bytes
		);
		if (empty($bytes['offset1']) || empty($bytes['length1']) || empty($bytes['offset2']) || empty($bytes['length2'])) {
			throw new LibresignException($this->l10n->t('Unsigned file.'));
		}

		for ($i = 0; $i < count($bytes['offset1']); $i++) {
			$offset1 = (int)$bytes['offset1'][$i];
			$length1 = (int)$bytes['length1'][$i];
			$offset2 = (int)$bytes['offset2'][$i];

			$signatureStart = $offset1 + $length1 + 1;
			$signatureLength = $offset2 - $signatureStart - 1;

			rewind($resource);

			$signature = stream_get_contents($resource, $signatureLength, $signatureStart);
			if ($signature === false) {
				yield null;
				continue;
			}

			$decodedSignature = @hex2bin($signature);
			if ($decodedSignature === false) {
				yield null;
				continue;
			}
			yield $decodedSignature;
		}

		$this->tempManager->clean();
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

		foreach ($this->getSignatures($resource) as $signature) {
			$certificates[] = $this->processSignature($resource, $signature);
		}
		return $certificates;
	}

	private function processSignature($resource, ?string $signature): array {
		$result = [];

		if (!$signature) {
			$result['chain'][0]['signature_validation'] = $this->getReadableSigState('Digest Mismatch.');
			return $result;
		}

		$decoded = ASN1::decodeBER($signature);
		$result = $this->extractTimestampData($decoded, $result);

		$chain = $this->extractCertificateChain($signature);
		if (!empty($chain)) {
			$result['chain'] = $this->orderCertificates($chain);
			$result = $this->enrichLeafWithPopplerData($resource, $result);
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
		} catch (\Throwable $e) {
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
		$rootCertificatePem = $this->getRootCertificatePem();
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
			$ou = trim($ou);
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

	private function enrichLeafWithPopplerData($resource, array $result): array {
		if (empty($result['chain'])) {
			return $result;
		}

		$popplerOnlyFields = ['field', 'range', 'certificate_validation'];
		if (!isset($result['chain'][0]['subject'])) {
			return $result;
		}
		$needPoppler = false;
		foreach ($popplerOnlyFields as $field) {
			if (empty($result['chain'][0][$field])) {
				$needPoppler = true;
				break;
			}
		}
		if (!isset($result['chain'][0]['signature_validation']) || $result['chain'][0]['signature_validation']['id'] !== 1) {
			$needPoppler = true;
		}
		if (!$needPoppler) {
			return $result;
		}
		$popplerChain = $this->chainFromPoppler($result['chain'][0]['subject'], $resource);
		if (empty($popplerChain)) {
			return $result;
		}
		foreach ($popplerOnlyFields as $field) {
			if (isset($popplerChain[$field])) {
				$result['chain'][0][$field] = $popplerChain[$field];
			}
		}
		if (!isset($result['chain'][0]['signature_validation']) || $result['chain'][0]['signature_validation']['id'] !== 1) {
			if (isset($popplerChain['signature_validation'])) {
				$result['chain'][0]['signature_validation'] = $popplerChain['signature_validation'];
			}
		}
		return $result;
	}

	private function chainFromPoppler(array $subject, $resource): array {
		$fromFallback = $this->popplerUtilsPdfSignFallback($resource);
		foreach ($fromFallback as $popplerSig) {
			if (!isset($popplerSig['chain'][0]['subject'])) {
				continue;
			}
			if ($popplerSig['chain'][0]['subject'] == $subject) {
				return $popplerSig['chain'][0];
			}
		}
		return [];
	}

	private function popplerUtilsPdfSignFallback($resource): array {
		if (!empty($this->signaturesFromPoppler)) {
			return $this->signaturesFromPoppler;
		}
		if (shell_exec('which pdfsig') === null) {
			return $this->signaturesFromPoppler;
		}
		rewind($resource);
		$content = stream_get_contents($resource);
		$tempFile = $this->tempManager->getTemporaryFile('file.pdf');
		file_put_contents($tempFile, $content);

		$content = shell_exec('env TZ=UTC pdfsig ' . $tempFile);
		if (empty($content)) {
			return $this->signaturesFromPoppler;
		}
		$lines = explode("\n", $content);

		$lastSignature = 0;
		foreach ($lines as $item) {
			$isFirstLevel = preg_match('/^Signature\s#(\d)/', $item, $match);
			if ($isFirstLevel) {
				$lastSignature = (int)$match[1] - 1;
				$this->signaturesFromPoppler[$lastSignature] = [];
				continue;
			}

			$match = [];
			$isSecondLevel = preg_match('/^\s+-\s(?<key>.+):\s(?<value>.*)/', $item, $match);
			if ($isSecondLevel) {
				switch ((string)$match['key']) {
					case 'Signing Time':
						$this->signaturesFromPoppler[$lastSignature]['signingTime'] = DateTime::createFromFormat('M d Y H:i:s', $match['value'], new \DateTimeZone('UTC'));
						break;
					case 'Signer full Distinguished Name':
						$this->signaturesFromPoppler[$lastSignature]['chain'][0]['subject'] = $this->parseDistinguishedNameWithMultipleValues($match['value']);
						$this->signaturesFromPoppler[$lastSignature]['chain'][0]['name'] = $match['value'];
						break;
					case 'Signing Hash Algorithm':
						$this->signaturesFromPoppler[$lastSignature]['chain'][0]['signatureTypeSN'] = $match['value'];
						break;
					case 'Signature Validation':
						$this->signaturesFromPoppler[$lastSignature]['chain'][0]['signature_validation'] = $this->getReadableSigState($match['value']);
						break;
					case 'Certificate Validation':
						$this->signaturesFromPoppler[$lastSignature]['chain'][0]['certificate_validation'] = $this->getReadableCertState($match['value']);
						break;
					case 'Signed Ranges':
						if (preg_match('/\[(\d+) - (\d+)\], \[(\d+) - (\d+)\]/', $match['value'], $ranges)) {
							$this->signaturesFromPoppler[$lastSignature]['chain'][0]['range'] = [
								'offset1' => (int)$ranges[1],
								'length1' => (int)$ranges[2],
								'offset2' => (int)$ranges[3],
								'length2' => (int)$ranges[4],
							];
						}
						break;
					case 'Signature Field Name':
						$this->signaturesFromPoppler[$lastSignature]['chain'][0]['field'] = $match['value'];
						break;
					case 'Signature Validation':
					case 'Signature Type':
					case 'Total document signed':
					case 'Not total document signed':
					default:
						break;
				}
			}
		}
		return $this->signaturesFromPoppler;
	}

	private function getReadableSigState(string $status) {
		return match ($status) {
			'Signature is Valid.' => [
				'id' => 1,
				'label' => $this->l10n->t('Signature is valid.'),
			],
			'Signature is Invalid.' => [
				'id' => 2,
				'label' => $this->l10n->t('Signature is invalid.'),
			],
			'Digest Mismatch.' => [
				'id' => 3,
				'label' => $this->l10n->t('Digest mismatch.'),
			],
			"Document isn't signed or corrupted data." => [
				'id' => 4,
				'label' => $this->l10n->t("Document isn't signed or corrupted data."),
			],
			'Signature has not yet been verified.' => [
				'id' => 5,
				'label' => $this->l10n->t('Signature has not yet been verified.'),
			],
			default => [
				'id' => 6,
				'label' => $this->l10n->t('Unknown validation failure.'),
			],
		};
	}


	private function getReadableCertState(string $status) {
		return match ($status) {
			'Certificate is Trusted.' => [
				'id' => 1,
				'label' => $this->l10n->t('Certificate is trusted.'),
			],
			"Certificate issuer isn't Trusted." => [
				'id' => 2,
				'label' => $this->l10n->t("Certificate issuer isn't trusted."),
			],
			'Certificate issuer is unknown.' => [
				'id' => 3,
				'label' => $this->l10n->t('Certificate issuer is unknown.'),
			],
			'Certificate has been Revoked.' => [
				'id' => 4,
				'label' => $this->l10n->t('Certificate has been revoked.'),
			],
			'Certificate has Expired' => [
				'id' => 5,
				'label' => $this->l10n->t('Certificate has expired'),
			],
			'Certificate has not yet been verified.' => [
				'id' => 6,
				'label' => $this->l10n->t('Certificate has not yet been verified.'),
			],
			default => [
				'id' => 7,
				'label' => $this->l10n->t('Unknown issue with Certificate or corrupted data.')
			],
		};
	}


	private function parseDistinguishedNameWithMultipleValues(string $dn): array {
		$result = [];
		$pairs = preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', $dn);

		foreach ($pairs as $pair) {
			[$key, $value] = explode('=', $pair, 2);
			if (empty($key) || empty($value)) {
				return $result;
			}
			$key = trim($key);
			$value = trim($value);
			$value = trim($value, '"');

			if (!isset($result[$key])) {
				$result[$key] = $value;
			} else {
				if (!is_array($result[$key])) {
					$result[$key] = [$result[$key]];
				}
				$result[$key][] = $value;
			}
		}

		return $result;
	}

	private function der2pem($derData) {
		$pem = chunk_split(base64_encode((string)$derData), 64, "\n");
		$pem = "-----BEGIN CERTIFICATE-----\n" . $pem . "-----END CERTIFICATE-----\n";
		return $pem;
	}

	private function getHandler(): SignEngineHandler {
		$sign_engine = $this->appConfig->getValueString(Application::APP_ID, 'sign_engine', 'JSignPdf');
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
