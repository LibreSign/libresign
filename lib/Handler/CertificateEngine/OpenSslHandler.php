<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\CertificateEngine;

use OCA\Libresign\Db\CrlMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\CaIdentifierService;
use OCA\Libresign\Service\CertificatePolicyService;
use OCA\Libresign\Service\SerialNumberService;
use OCP\Files\AppData\IAppDataFactory;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\ITempManager;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

/**
 * Class FileMapper
 *
 * @package OCA\Libresign\Handler
 *
 * @method CfsslHandler setClient(Client $client)
 */
class OpenSslHandler extends AEngineHandler implements IEngineHandler {
	public function __construct(
		protected IConfig $config,
		protected IAppConfig $appConfig,
		protected IAppDataFactory $appDataFactory,
		protected IDateTimeFormatter $dateTimeFormatter,
		protected ITempManager $tempManager,
		protected CertificatePolicyService $certificatePolicyService,
		protected IURLGenerator $urlGenerator,
		protected SerialNumberService $serialNumberService,
		protected CaIdentifierService $caIdentifierService,
		protected CrlMapper $crlMapper,
		protected LoggerInterface $logger,
	) {
		parent::__construct(
			$config,
			$appConfig,
			$appDataFactory,
			$dateTimeFormatter,
			$tempManager,
			$certificatePolicyService,
			$urlGenerator,
			$caIdentifierService,
		);
	}

	public function generateRootCert(
		string $commonName,
		array $names = [],
	): void {
		$privateKey = openssl_pkey_new([
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		]);

		$csr = openssl_csr_new($this->getCsrNames(), $privateKey, ['digest_alg' => 'sha256']);
		$options = $this->getRootCertOptions();

		$caDays = $this->getCaExpiryInDays();
		$serialNumberString = $this->serialNumberService->generateUniqueSerial(
			$commonName,
			$this->caIdentifierService->getInstanceId(),
			$this->caIdentifierService->getCaIdParsed()['generation'],
			new \DateTime('+' . $caDays . ' days'),
			'openssl',
		);
		$serialNumber = (int)$serialNumberString;

		$x509 = openssl_csr_sign($csr, null, $privateKey, $caDays, $options, $serialNumber);

		openssl_csr_export($csr, $csrout);
		openssl_x509_export($x509, $certout);
		openssl_pkey_export($privateKey, $pkeyout);

		$configPath = $this->getCurrentConfigPath();
		CertificateHelper::saveFile($configPath . '/ca.csr', $csrout);
		CertificateHelper::saveFile($configPath . '/ca.pem', $certout);
		CertificateHelper::saveFile($configPath . '/ca-key.pem', $pkeyout);
	}

	private function getRootCertOptions(): array {
		$configFile = $this->generateCaConfig();

		return [
			'digest_alg' => 'sha256',
			'config' => $configFile,
			'x509_extensions' => 'v3_ca',
		];
	}

	private function getLeafCertOptions(): array {
		$configFile = $this->generateLeafConfig();

		return [
			'digest_alg' => 'sha256',
			'config' => $configFile,
			'x509_extensions' => 'v3_req',
		];
	}

	public function generateCertificate(): string {
		$configPath = $this->getCurrentConfigPath();
		$rootCertificate = file_get_contents($configPath . DIRECTORY_SEPARATOR . 'ca.pem');
		$rootPrivateKey = file_get_contents($configPath . DIRECTORY_SEPARATOR . 'ca-key.pem');
		if (empty($rootCertificate) || empty($rootPrivateKey)) {
			throw new LibresignException('Invalid root certificate');
		}

		$this->inheritRootSubjectFields($rootCertificate);

		$privateKey = openssl_pkey_new([
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		]);

		$csr = @openssl_csr_new($this->getCsrNames(), $privateKey, ['digest_alg' => 'sha256']);
		if ($csr === false) {
			$message = openssl_error_string();
			throw new LibresignException('OpenSSL error: ' . $message);
		}

		$serialNumberString = $this->serialNumberService->generateUniqueSerial(
			$this->getCommonName(),
			$this->caIdentifierService->getInstanceId(),
			$this->caIdentifierService->getCaIdParsed()['generation'],
			new \DateTime('+' . $this->getLeafExpiryInDays() . ' days'),
			'openssl',
		);
		$serialNumber = (int)$serialNumberString;
		$options = $this->getLeafCertOptions();

		$x509 = openssl_csr_sign($csr, $rootCertificate, $rootPrivateKey, $this->getLeafExpiryInDays(), $options, $serialNumber);

		return parent::exportToPkcs12(
			$x509,
			$privateKey,
			[
				'friendly_name' => $this->getFriendlyName(),
				'extracerts' => [
					$x509,
					$rootCertificate,
				],
			],
		);
	}

	private function inheritRootSubjectFields(string $rootCertificate): void {
		$parsedRoot = openssl_x509_parse($rootCertificate);
		if ($parsedRoot && isset($parsedRoot['subject']) && is_array($parsedRoot['subject'])) {
			$map = [
				'C' => 'country',
				'ST' => 'state',
				'L' => 'locality',
				'O' => 'organization',
				'OU' => 'organizationalUnit',
			];
			foreach ($parsedRoot['subject'] as $k => $v) {
				if (isset($map[$k])) {
					$setter = 'set' . ucfirst($map[$k]);
					if (method_exists($this, $setter)) {
						$this->$setter($v);
					}
				}
			}
		}
	}

	private function generateCaConfig(): string {
		$config = $this->buildCaCertificateConfig();
		$this->cleanupCaConfig($config);

		return $this->saveCaConfigFile($config);
	}

	private function generateLeafConfig(): string {
		$config = $this->buildLeafCertificateConfig();
		$this->cleanupLeafConfig($config);

		return $this->saveLeafConfigFile($config);
	}

	/**
	 * More information about x509v3: https://www.openssl.org/docs/manmaster/man5/x509v3_config.html
	 */
	private function buildCaCertificateConfig(): array {
		$config = [
			'ca' => [
				'default_ca' => 'CA_default'
			],
			'CA_default' => [
				'default_crl_days' => 7,
				'default_md' => 'sha256',
				'preserve' => 'no',
				'policy' => 'policy_anything'
			],
			'policy_anything' => [
				'countryName' => 'optional',
				'stateOrProvinceName' => 'optional',
				'organizationName' => 'optional',
				'organizationalUnitName' => 'optional',
				'commonName' => 'supplied',
				'emailAddress' => 'optional'
			],
			'v3_ca' => [
				'basicConstraints' => 'critical, CA:TRUE, pathlen:1',
				'keyUsage' => 'critical, digitalSignature, keyCertSign',
				'extendedKeyUsage' => 'clientAuth, emailProtection',
				'subjectAltName' => $this->getSubjectAltNames(),
				'authorityKeyIdentifier' => 'keyid',
				'subjectKeyIdentifier' => 'hash',
				'crlDistributionPoints' => 'URI:' . $this->getCrlDistributionUrl(),
			],
			'crl_ext' => [
				'issuerAltName' => 'issuer:copy',
				'authorityKeyIdentifier' => 'keyid:always',
				'subjectKeyIdentifier' => 'hash'
			]
		];

		$this->addCaPolicies($config);

		return $config;
	}

	private function buildLeafCertificateConfig(): array {
		$config = [
			'v3_req' => [
				'basicConstraints' => 'CA:FALSE',
				'keyUsage' => 'digitalSignature, keyEncipherment, nonRepudiation',
				'extendedKeyUsage' => 'clientAuth, emailProtection',
				'subjectAltName' => $this->getSubjectAltNames(),
				'authorityKeyIdentifier' => 'keyid:always,issuer:always',
				'subjectKeyIdentifier' => 'hash',
				'crlDistributionPoints' => 'URI:' . $this->getCrlDistributionUrl(),
			],
		];

		$this->addLeafPolicies($config);

		return $config;
	}

	private function addCaPolicies(array &$config): void {
		$oid = $this->certificatePolicyService->getOid();
		$cps = $this->certificatePolicyService->getCps();

		if (!$oid || !$cps) {
			return;
		}

		$config['v3_ca']['certificatePolicies'] = '@policy_section';
		$config['policy_section'] = [
			'policyIdentifier' => $oid,
			'CPS.1' => $cps,
		];
	}

	private function addLeafPolicies(array &$config): void {
		$oid = $this->certificatePolicyService->getOid();
		$cps = $this->certificatePolicyService->getCps();

		if (!$oid || !$cps) {
			return;
		}

		$config['v3_req']['certificatePolicies'] = '@policy_section';
		$config['policy_section'] = [
			'policyIdentifier' => $oid,
			'CPS.1' => $cps,
		];
	}

	private function cleanupCaConfig(array &$config): void {
		if (empty($config['v3_ca']['subjectAltName'])) {
			unset($config['v3_ca']['subjectAltName']);
		}
	}

	private function cleanupLeafConfig(array &$config): void {
		if (empty($config['v3_req']['subjectAltName'])) {
			unset($config['v3_req']['subjectAltName']);
		}
	}

	private function saveCaConfigFile(array $config): string {
		$iniContent = CertificateHelper::arrayToIni($config);
		$configFile = $this->getCurrentConfigPath() . '/openssl.cnf';
		CertificateHelper::saveFile($configFile, $iniContent);
		return $configFile;
	}

	private function saveLeafConfigFile(array $config): string {
		$iniContent = CertificateHelper::arrayToIni($config);
		$temporaryFile = $this->tempManager->getTemporaryFile('.cfg');
		if (!$temporaryFile) {
			throw new LibresignException('Failure to create temporary file to OpenSSL .cfg file');
		}
		file_put_contents($temporaryFile, $iniContent);
		return $temporaryFile;
	}

	private function getSubjectAltNames(): string {
		$hosts = $this->getHosts();
		$altNames = [];
		foreach ($hosts as $host) {
			if (filter_var($host, FILTER_VALIDATE_EMAIL)) {
				$altNames[] = 'email:' . $host;
			}
		}
		return implode(', ', $altNames);
	}

	/**
	 * Convert to names as necessary to OpenSSL
	 *
	 * Read more here: https://www.php.net/manual/en/function.openssl-csr-new.php
	 */
	private function getCsrNames(): array {
		$distinguishedNames = [];
		$names = parent::getNames();
		foreach ($names as $name => $value) {
			if ($name === 'ST') {
				$distinguishedNames['stateOrProvinceName'] = $value;
				continue;
			}
			if ($name === 'UID') {
				$distinguishedNames['UID'] = $value;
				continue;
			}

			$longName = $this->translateToLong($name);
			$longName = lcfirst($longName) . 'Name';

			if (is_array($value)) {
				if (!empty($value)) {
					$distinguishedNames[$longName] = implode(', ', $value);
				}
			} else {
				$distinguishedNames[$longName] = $value;
			}
		}
		if ($this->getCommonName()) {
			$distinguishedNames['commonName'] = $this->getCommonName();
		}
		return $distinguishedNames;
	}

	public function isSetupOk(): bool {
		$configPath = $this->getCurrentConfigPath();
		if (empty($configPath)) {
			return false;
		}
		$certificate = file_exists($configPath . DIRECTORY_SEPARATOR . 'ca.pem');
		$privateKey = file_exists($configPath . DIRECTORY_SEPARATOR . 'ca-key.pem');
		return $certificate && $privateKey;
	}

	protected function getConfigureCheckResourceName(): string {
		return 'openssl-configure';
	}

	protected function getCertificateRegenerationTip(): string {
		return 'Consider regenerating the root certificate with: occ libresign:configure:openssl --cn="Your CA Name"';
	}

	protected function getEngineSpecificChecks(): array {
		return [];
	}

	protected function getSetupSuccessMessage(): string {
		return 'Root certificate setup is working fine.';
	}

	protected function getSetupErrorMessage(): string {
		return 'OpenSSL (root certificate) not configured.';
	}

	protected function getSetupErrorTip(): string {
		return 'Run occ libresign:configure:openssl --help';
	}

	#[\Override]
	public function generateCrlDer(array $revokedCertificates, string $instanceId, int $generation, int $crlNumber): string {
		$configPath = $this->getConfigPathByParams($instanceId, $generation);
		$caCertPath = $configPath . DIRECTORY_SEPARATOR . 'ca.pem';
		$caKeyPath = $configPath . DIRECTORY_SEPARATOR . 'ca-key.pem';
		$crlDerPath = $configPath . DIRECTORY_SEPARATOR . 'crl.der';

		if (!file_exists($caCertPath) || !file_exists($caKeyPath)) {
			throw new \RuntimeException('CA certificate or private key not found. Run: occ libresign:configure:openssl');
		}

		try {
			$caCert = file_get_contents($caCertPath);
			$caKey = file_get_contents($caKeyPath);

			if (!$caCert || !$caKey) {
				throw new \RuntimeException('Failed to read CA certificate or private key');
			}

			$issuer = new \OCA\Libresign\Vendor\phpseclib3\File\X509();
			$issuer->loadX509($caCert);
			$caPrivateKey = \OCA\Libresign\Vendor\phpseclib3\Crypt\PublicKeyLoader::load($caKey);

			if (!$caPrivateKey instanceof \OCA\Libresign\Vendor\phpseclib3\Crypt\Common\PrivateKey) {
				throw new \RuntimeException('Loaded key is not a private key');
			}

			$issuer->setPrivateKey($caPrivateKey);

			$crlStructure = [
				'tbsCertList' => [
					'version' => 'v2',
					'signature' => ['algorithm' => 'sha256WithRSAEncryption'],
					'issuer' => $issuer->getSubjectDN(\OCA\Libresign\Vendor\phpseclib3\File\X509::DN_ARRAY),
					'thisUpdate' => ['utcTime' => date('ymdHis\Z')],
					'nextUpdate' => ['utcTime' => date('ymdHis\Z', strtotime('+7 days'))],
					'revokedCertificates' => [],
				],
				'signatureAlgorithm' => ['algorithm' => 'sha256WithRSAEncryption'],
			];

			foreach ($revokedCertificates as $cert) {
				$serialHex = $cert->getSerialNumber();
				$revokedAt = $cert->getRevokedAt();

				$crlStructure['tbsCertList']['revokedCertificates'][] = [
					'userCertificate' => new \OCA\Libresign\Vendor\phpseclib3\Math\BigInteger($serialHex, 16),
					'revocationDate' => ['utcTime' => $revokedAt->format('ymdHis\Z')],
				];
			}

			$crl = new \OCA\Libresign\Vendor\phpseclib3\File\X509();
			$crl->loadCRL($crlStructure);
			$crl->setSerialNumber($crlNumber);

			$signedCrl = $crl->signCRL($issuer, $crl, 'sha256WithRSAEncryption');

			if ($signedCrl === false) {
				throw new \RuntimeException('Failed to sign CRL with phpseclib3');
			}

			if (!isset($signedCrl['signatureAlgorithm'])) {
				$signedCrl['signatureAlgorithm'] = ['algorithm' => 'sha256WithRSAEncryption'];
			}

			$saver = new \OCA\Libresign\Vendor\phpseclib3\File\X509();
			$crlDerData = $saver->saveCRL($signedCrl, \OCA\Libresign\Vendor\phpseclib3\File\X509::FORMAT_DER);

			if ($crlDerData === false) {
				throw new \RuntimeException('Failed to save CRL in DER format');
			}

			if (file_put_contents($crlDerPath, $crlDerData) === false) {
				throw new \RuntimeException('Failed to write CRL DER file');
			}

			return $crlDerData;
		} catch (\Exception $e) {
			$this->logger->error('CRL generation failed: ' . $e->getMessage(), ['exception' => $e]);
			throw new \RuntimeException('Failed to generate CRL: ' . $e->getMessage(), 0, $e);
		}
	}
}
