<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\CertificateEngine;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\CertificatePolicyService;
use OCA\Libresign\Service\SerialNumberService;
use OCP\Files\AppData\IAppDataFactory;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\ITempManager;
use OCP\IURLGenerator;

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
	) {
		parent::__construct($config, $appConfig, $appDataFactory, $dateTimeFormatter, $tempManager, $certificatePolicyService);
	}



	#[\Override]
	public function generateRootCert(
		string $commonName,
		array $names = [],
	): string {
		$privateKey = openssl_pkey_new([
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		]);

		$csr = openssl_csr_new($this->getCsrNames(), $privateKey, ['digest_alg' => 'sha256']);
		$options = $this->getRootCertOptions();

		$serialNumber = $this->serialNumberService->generateUniqueSerial(
			$commonName,
			new \DateTime('+5 years')
		);

		$x509 = openssl_csr_sign($csr, null, $privateKey, $days = 365 * 5, $options, $serialNumber);

		openssl_csr_export($csr, $csrout);
		openssl_x509_export($x509, $certout);
		openssl_pkey_export($privateKey, $pkeyout);

		$configPath = $this->getConfigPath();
		CertificateHelper::saveFile($configPath . '/ca.csr', $csrout);
		CertificateHelper::saveFile($configPath . '/ca.pem', $certout);
		CertificateHelper::saveFile($configPath . '/ca-key.pem', $pkeyout);

		return $pkeyout;
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

	#[\Override]
	public function generateCertificate(): string {
		$configPath = $this->getConfigPath();
		$rootCertificate = file_get_contents($configPath . DIRECTORY_SEPARATOR . 'ca.pem');
		$rootPrivateKey = file_get_contents($configPath . DIRECTORY_SEPARATOR . 'ca-key.pem');
		if (empty($rootCertificate) || empty($rootPrivateKey)) {
			throw new LibresignException('Invalid root certificate');
		}

		$privateKey = openssl_pkey_new([
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		]);

		$csr = @openssl_csr_new($this->getCsrNames(), $privateKey, ['digest_alg' => 'sha256']);
		if ($csr === false) {
			$message = openssl_error_string();
			throw new LibresignException('OpenSSL error: ' . $message);
		}

		$serialNumber = $this->serialNumberService->generateUniqueSerial(
			$this->getCommonName(),
			new \DateTime('+' . $this->expirity() . ' days')
		);
		$options = $this->getLeafCertOptions();

		$x509 = openssl_csr_sign($csr, $rootCertificate, $rootPrivateKey, $this->expirity(), $options, $serialNumber);

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
				'basicConstraints' => 'critical, CA:TRUE',
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

	private function getCrlDistributionUrl(): string {
		return $this->urlGenerator->linkToRouteAbsolute('libresign.crl.getRevocationList');
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
		$configFile = $this->getConfigPath() . '/openssl.cnf';
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
			$distinguishedNames[$longName] = $value;
		}
		if ($this->getCommonName()) {
			$distinguishedNames['commonName'] = $this->getCommonName();
		}
		return $distinguishedNames;
	}

	#[\Override]
	public function isSetupOk(): bool {
		$ok = parent::isSetupOk();
		if (!$ok) {
			return false;
		}
		$configPath = $this->getConfigPath();
		$certificate = file_exists($configPath . DIRECTORY_SEPARATOR . 'ca.pem');
		$privateKey = file_exists($configPath . DIRECTORY_SEPARATOR . 'ca-key.pem');
		return $certificate && $privateKey;
	}

	#[\Override]
	protected function getConfigureCheckResourceName(): string {
		return 'openssl-configure';
	}

	#[\Override]
	protected function getCertificateRegenerationTip(): string {
		return 'Consider regenerating the root certificate with: occ libresign:configure:openssl --cn="Your CA Name"';
	}

	#[\Override]
	protected function getEngineSpecificChecks(): array {
		return [];
	}

	#[\Override]
	protected function getSetupSuccessMessage(): string {
		return 'Root certificate setup is working fine.';
	}

	#[\Override]
	protected function getSetupErrorMessage(): string {
		return 'OpenSSL (root certificate) not configured.';
	}

	#[\Override]
	protected function getSetupErrorTip(): string {
		return 'Run occ libresign:configure:openssl --help';
	}

	#[\Override]
	public function generateCrlDer(array $revokedCertificates): string {
		$configPath = $this->getConfigPath();
		$caCertPath = $configPath . DIRECTORY_SEPARATOR . 'ca.pem';
		$caKeyPath = $configPath . DIRECTORY_SEPARATOR . 'ca-key.pem';
		$crlDerPath = $configPath . DIRECTORY_SEPARATOR . 'crl.der';

		if (!file_exists($caCertPath) || !file_exists($caKeyPath)) {
			throw new \RuntimeException('CA certificate or private key not found. Run: occ libresign:configure:openssl');
		}

		if ($this->isCrlUpToDate($crlDerPath, $revokedCertificates)) {
			$content = file_get_contents($crlDerPath);
			if ($content === false) {
				throw new \RuntimeException('Failed to read existing CRL file');
			}
			return $content;
		}

		$crlConfigPath = $this->createCrlConfig($revokedCertificates);
		$crlPemPath = $configPath . DIRECTORY_SEPARATOR . 'crl.pem';

		try {
			$command = sprintf(
				'openssl ca -gencrl -out %s -config %s -cert %s -keyfile %s',
				escapeshellarg($crlPemPath),
				escapeshellarg($crlConfigPath),
				escapeshellarg($caCertPath),
				escapeshellarg($caKeyPath)
			);

			$output = [];
			$returnCode = 0;
			exec($command . ' 2>&1', $output, $returnCode);

			if ($returnCode !== 0) {
				throw new \RuntimeException('Failed to generate CRL: ' . implode("\n", $output));
			}

			$convertCommand = sprintf(
				'openssl crl -in %s -outform DER -out %s',
				escapeshellarg($crlPemPath),
				escapeshellarg($crlDerPath)
			);

			exec($convertCommand . ' 2>&1', $output, $returnCode);

			if ($returnCode !== 0) {
				throw new \RuntimeException('Failed to convert CRL to DER format: ' . implode("\n", $output));
			}

			$derContent = file_get_contents($crlDerPath);
			if ($derContent === false) {
				throw new \RuntimeException('Failed to read generated CRL DER file');
			}

			return $derContent;
		} catch (\Exception $e) {
			throw new \RuntimeException('Failed to generate CRL: ' . $e->getMessage(), 0, $e);
		}
	}

	private function isCrlUpToDate(string $crlDerPath, array $revokedCertificates): bool {
		if (!file_exists($crlDerPath)) {
			return false;
		}

		$crlAge = time() - filemtime($crlDerPath);
		if ($crlAge > 86400) { // 24 hours
			return false;
		}

		return true;
	}

	private function createCrlConfig(array $revokedCertificates): string {
		$configPath = $this->getConfigPath();
		$indexFile = $configPath . DIRECTORY_SEPARATOR . 'index.txt';
		$crlNumberFile = $configPath . DIRECTORY_SEPARATOR . 'crlnumber';
		$configFile = $configPath . DIRECTORY_SEPARATOR . 'crl.conf';

		$existingContent = file_exists($indexFile) ? file_get_contents($indexFile) : '';
		$existingSerials = [];

		if ($existingContent) {
			foreach (explode("\n", trim($existingContent)) as $line) {
				if (preg_match('/^R\t.*\t.*\t([A-F0-9]+)\t/', $line, $matches)) {
					$existingSerials[] = $matches[1];
				}
			}
		}

		$newContent = '';
		foreach ($revokedCertificates as $cert) {
			$serialHex = strtoupper(dechex($cert->getSerialNumber()));

			if (in_array($serialHex, $existingSerials)) {
				continue;
			}

			$revokedAt = new \DateTime($cert->getRevokedAt()->format('Y-m-d H:i:s'));
			$reasonCode = $cert->getReasonCode() ?? 0;
			$newContent .= sprintf(
				"R\t%s\t%s,%02d\t%s\tunknown\t/CN=%s\n",
				$cert->getValidTo() ? $cert->getValidTo()->format('ymdHis\Z') : '501231235959Z',
				$revokedAt->format('ymdHis\Z'),
				$reasonCode,
				$serialHex,
				$cert->getOwner()
			);
		}

		file_put_contents($indexFile, $existingContent . $newContent);

		if (!file_exists($crlNumberFile)) {
			file_put_contents($crlNumberFile, "01\n");
		}

		$crlConfig = $this->buildCaCertificateConfig();

		$crlConfig['CA_default']['dir'] = dirname($indexFile);
		$crlConfig['CA_default']['database'] = $indexFile;
		$crlConfig['CA_default']['crlnumber'] = $crlNumberFile;

		$configContent = CertificateHelper::arrayToIni($crlConfig);
		file_put_contents($configFile, $configContent);

		return $configFile;
	}


}
