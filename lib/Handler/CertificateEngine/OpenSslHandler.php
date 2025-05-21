<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\CertificateEngine;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\ConfigureCheckHelper;
use OCA\Libresign\Service\CertificatePolicyService;
use OCP\Files\AppData\IAppDataFactory;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\ITempManager;

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
	) {
		parent::__construct($config, $appConfig, $appDataFactory, $dateTimeFormatter, $tempManager, $certificatePolicyService);
	}

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
		$x509 = openssl_csr_sign($csr, null, $privateKey, $days = 365 * 5, $options);

		openssl_csr_export($csr, $csrout);
		openssl_x509_export($x509, $certout);
		openssl_pkey_export($privateKey, $pkeyout);

		$this->saveFile('ca.csr', $csrout);
		$this->saveFile('ca.pem', $certout);
		$this->saveFile('ca-key.pem', $pkeyout);

		return $pkeyout;
	}

	private function getRootCertOptions(): array {
		$this->generateRootCertConfig();
		$configPath = $this->getConfigPath();

		$options = [
			'digest_alg' => 'sha256',
			'config' => $configPath . DIRECTORY_SEPARATOR . 'openssl.cnf',
			'x509_extensions' => 'v3_ca',
		];
		return $options;
	}

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

		$csr = openssl_csr_new($this->getCsrNames(), $privateKey);

		$x509 = openssl_csr_sign($csr, $rootCertificate, $rootPrivateKey, $this->expirity(), [
			'config' => $this->getFilenameToLeafCert(),
			// This will set "basicConstraints" to CA:FALSE, the default is CA:TRUE
			// The signer certificate is not a Certificate Authority
			'x509_extensions' => 'v3_req',
		]);
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

	private function getFilenameToLeafCert(): string {
		$temporaryFile = $this->tempManager->getTemporaryFile('.cfg');
		if (!$temporaryFile) {
			throw new LibresignException('Failure to create temporary file to OpenSSL .cfg file');
		}
		// More information about x509v3: https://www.openssl.org/docs/manmaster/man5/x509v3_config.html
		$config = [
			'v3_req' => [
				'basicConstraints' => 'CA:FALSE',
				'keyUsage' => 'digitalSignature, keyEncipherment, keyCertSign',
				'extendedKeyUsage' => 'clientAuth, emailProtection',
				'subjectAltName' => $this->getSubjectAltNames(),
				'authorityKeyIdentifier' => 'keyid',
				'subjectKeyIdentifier' => 'hash',
			],
		];
		$oid = $this->certificatePolicyService->getOid();
		$cps = $this->certificatePolicyService->getCps();
		if ($oid && $cps) {
			$config['v3_req']['certificatePolicies'] = '@policy_section';
			$config['policy_section'] = [
				'policyIdentifier' => $oid,
				'CPS.1' => $cps,
			];
		}
		if (empty($config['v3_req']['subjectAltName'])) {
			unset($config['v3_req']['subjectAltName']);
		}
		$config = $this->arrayToIni($config);
		file_put_contents($temporaryFile, $config);
		return $temporaryFile;
	}

	private function generateRootCertConfig(): void {
		// More information about x509v3: https://www.openssl.org/docs/manmaster/man5/x509v3_config.html
		$config = [
			'v3_ca' => [
				'basicConstraints' => 'critical, CA:TRUE',
				'keyUsage' => 'critical, digitalSignature, keyCertSign',
				'extendedKeyUsage' => 'clientAuth, emailProtection',
				'subjectAltName' => $this->getSubjectAltNames(),
				'authorityKeyIdentifier' => 'keyid',
				'subjectKeyIdentifier' => 'hash',
			],
		];
		$oid = $this->certificatePolicyService->getOid();
		$cps = $this->certificatePolicyService->getCps();
		if ($oid && $cps) {
			$config['v3_ca']['certificatePolicies'] = '@policy_section';
			$config['policy_section'] = [
				'policyIdentifier' => $oid,
				'CPS.1' => $cps,
			];
		}
		if (empty($config['v3_ca']['subjectAltName'])) {
			unset($config['v3_ca']['subjectAltName']);
		}
		$iniContent = $this->arrayToIni($config);
		$this->saveFile('openssl.cnf', $iniContent);
	}

	private function arrayToIni(array $config) {
		$fileContent = '';
		foreach ($config as $i => $v) {
			if (is_array($v)) {
				$fileContent .= "\n[$i]\n" . $this->arrayToIni($v);
			} else {
				$fileContent .= "$i = " . (str_contains((string)$v, "\n") ? '"' . $v . '"' : $v) . "\n";
			}
		}
		return $fileContent;
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

	private function saveFile(string $filename, string $content): void {
		$configPath = $this->getConfigPath();
		$success = file_put_contents($configPath . DIRECTORY_SEPARATOR . $filename, $content);
		if ($success === false) {
			throw new LibresignException('Failure to save file. Check permission: ' . $configPath . DIRECTORY_SEPARATOR . $filename);
		}
	}

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

	public function configureCheck(): array {
		if ($this->isSetupOk()) {
			return [(new ConfigureCheckHelper())
				->setSuccessMessage('Root certificate setup is working fine.')
				->setResource('openssl-configure')];
		}
		return [(new ConfigureCheckHelper())
			->setErrorMessage('OpenSSL (root certificate) not configured.')
			->setResource('openssl-configure')
			->setTip('Run occ libresign:configure:openssl --help')];
	}
}
