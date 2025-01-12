<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Libresign\Handler\CertificateEngine;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\ConfigureCheckHelper;
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
	) {
		parent::__construct($config, $appConfig, $appDataFactory, $dateTimeFormatter, $tempManager);
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
		$x509 = openssl_csr_sign($csr, null, $privateKey, $days = 365 * 5, ['digest_alg' => 'sha256']);

		openssl_csr_export($csr, $csrout);
		openssl_x509_export($x509, $certout);
		openssl_pkey_export($privateKey, $pkeyout);

		$this->saveFile('ca.csr', $csrout);
		$this->saveFile('ca.pem', $certout);
		$this->saveFile('ca-key.pem', $pkeyout);

		return $pkeyout;
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
		$temporaryFile = $this->tempManager->getTemporaryFile('.cfg');
		// More information about x509v3: https://www.openssl.org/docs/manmaster/man5/x509v3_config.html
		file_put_contents($temporaryFile, <<<CONFIG
			[ v3_req ]
			basicConstraints = CA:FALSE
			keyUsage = digitalSignature, keyEncipherment, keyCertSign
			extendedKeyUsage = clientAuth, emailProtection
			subjectAltName = {$this->getSubjectAltNames()}
			authorityKeyIdentifier = keyid
			subjectKeyIdentifier = hash
			# certificatePolicies = <policyOID> CPS: http://url/with/policy/informations.pdf
			CONFIG);
		$csr = openssl_csr_new($this->getCsrNames(), $privateKey);
		$x509 = openssl_csr_sign($csr, $rootCertificate, $rootPrivateKey, $this->expirity(), [
			'config' => $temporaryFile,
			// This will set "basicConstraints" to CA:FALSE, the default is CA:TRUE
			// The signer certificate is not a Certificate Authority
			'x509_extensions' => 'v3_req',
		]);
		return parent::exportToPkcs12($x509, $privateKey);
	}

	private function getSubjectAltNames(): string {
		$hosts = $this->getHosts();
		$altNames = [];
		foreach ($hosts as $email) {
			$altNames[] = 'email:' . $email;
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
