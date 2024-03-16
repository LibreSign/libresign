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

/**
 * Class FileMapper
 *
 * @package OCA\Libresign\Handler
 *
 * @method CfsslHandler setClient(Client $client)
 */
class OpenSslHandler extends AEngineHandler implements IEngineHandler {
	public function generateRootCert(
		string $commonName,
		array $names = [],
	): string {

		$privkey = openssl_pkey_new([
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		]);

		$dn['commonName'] = $commonName;
		foreach ($names as $key => $value) {
			$dn[$key] = $value['value'];
		}

		$csr = openssl_csr_new($dn, $privkey, array('digest_alg' => 'sha256'));
		$x509 = openssl_csr_sign($csr, null, $privkey, $days = 365 * 5, array('digest_alg' => 'sha256'));

		openssl_csr_export($csr, $csrout);
		openssl_x509_export($x509, $certout);
		openssl_pkey_export($privkey, $pkeyout);

		$this->saveFile('ca.csr', $csrout);
		$this->saveFile('ca.pem', $certout);
		$this->saveFile('ca-key.pem', $pkeyout);

		return $pkeyout;
	}

	public function generateCertificate(string $certificate = '', string $privateKey = ''): string {
		$configPath = $this->getConfigPath();
		$certificate = file_get_contents($configPath . DIRECTORY_SEPARATOR . 'ca.pem');
		$privateKey = file_get_contents($configPath . DIRECTORY_SEPARATOR . 'ca-key.pem');
		if (empty($certificate) || empty($privateKey)) {
			throw new LibresignException('Invalid root certificate');
		}
		return parent::generateCertificate($certificate, $privateKey);
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
