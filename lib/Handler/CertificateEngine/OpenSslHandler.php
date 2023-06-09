<?php

namespace OCA\Libresign\Handler\CertificateEngine;

use OCA\Libresign\Helper\ConfigureCheckHelper;

/**
 * Class FileMapper
 *
 * @package OCA\Libresign\Handler
 *
 * @method CfsslHandler setClient(Client $client)
 */
class OpenSslHandler extends AEngineHandler implements IEngineHandler {
	public function generateCertificate(string $certificate = '', string $privateKey = ''): string {
		$configPath = $this->getConfigPath();
		$certificate = file_get_contents($configPath . '/ca.pem');
		$privateKey = file_get_contents($configPath . '/ca-key.pem');
		return parent::generateCertificate($certificate, $privateKey);
	}

	public function generateRootCert(
		string $commonName,
		array $names = [],
	): string {
		$configPath = $this->getConfigPath();

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

		file_put_contents($configPath . '/ca.csr', $csrout);
		file_put_contents($configPath . '/ca.pem', $certout);
		file_put_contents($configPath . '/ca-key.pem', $pkeyout);

		return $pkeyout;
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
