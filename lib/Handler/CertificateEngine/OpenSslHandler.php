<?php

namespace OCA\Libresign\Handler\CertificateEngine;

use OCA\Libresign\AppInfo\Application;

/**
 * Class FileMapper
 *
 * @package OCA\Libresign\Handler
 *
 * @method CfsslHandler setClient(Client $client)
 * @method string getConfigPath()
 */
class OpenSslHandler extends AEngineHandler implements IEngineHandler {
	protected string $engine = 'cfssl';
	public function generateRootCert(
		string $commonName,
		array $names = [],
		string $configPath = '',
	): string {
		$this->setConfigPath($configPath);

		$privkey = openssl_pkey_new([
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		]);

		$dn['commonName'] = $commonName;
		foreach ($names as $key => $value) {
			$dn[$this->translateToLong($key)] = $value;
		}

		$csr = openssl_csr_new($dn, $privkey, array('digest_alg' => 'sha256'));
		$x509 = openssl_csr_sign($csr, null, $privkey, $days = 365 * 5, array('digest_alg' => 'sha256'));

		openssl_csr_export($csr, $csrout);
		openssl_x509_export($x509, $certout);
		openssl_pkey_export($privkey, $pkeyout);

		file_put_contents($configPath . 'ca.csr', $csrout);
		file_put_contents($configPath . 'ca.pem', $certout);
		file_put_contents($configPath . 'ca-key.pem', $pkeyout);

		return $pkeyout;
	}

	public function isOk(): bool {
		return $this->config->getAppValue(Application::APP_ID, 'authkey') ? true : false;
	}
}
