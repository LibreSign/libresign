<?php

namespace OCA\Libresign\Handler\CertificateEngine;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\MagicGetterSetterTrait;

/**
 * Class AbstractHandler
 *
 * @package OCA\Libresign\Handler
 *
 * @method CfsslHandler setPassword(string $password)
 * @method string getPassword()
 * @method CfsslHandler setCommonName(string $commonName)
 * @method string getCommonName()
 * @method CfsslHandler setHosts(array $hosts)
 * @method array getHosts()
 * @method CfsslHandler setFriendlyName(string $friendlyName)
 * @method string getFriendlyName()
 * @method CfsslHandler setCountry(string $country)
 * @method string getCountry()
 * @method CfsslHandler setState(string $state)
 * @method string getState()
 * @method CfsslHandler setLocality(string $locality)
 * @method string getLocality()
 * @method CfsslHandler setOrganization(string $organization)
 * @method string getOrganization()
 * @method CfsslHandler setOrganizationUnit(string $organizationUnit)
 * @method string getOrganizationUnit()
 * @method string getConfigPath()
 * @method CfsslHandler setConfigPath()
 */
abstract class AbstractHandler {
	use MagicGetterSetterTrait;

	protected $commonName;
	protected $hosts = [];
	protected $friendlyName;
	protected $country;
	protected $state;
	protected $locality;
	protected $organization;
	protected $organizationUnit;
	protected $password;
	protected $configPath;

	public function generateCertificate(): string {
		$certKeys = $this->newCert();
		$certContent = null;
		try {
			openssl_pkcs12_export(
				$certKeys['certificate'],
				$certContent,
				$certKeys['private_key'],
				$this->getPassword(),
				['friendly_name' => $this->getFriendlyName()],
			);
		} catch (\Throwable $th) {
			throw new LibresignException('Error while creating certificate file', 500);
		}

		return $certContent;
	}

	public function getNames(): array {
		$names = [
			'C' => $this->getCountry(),
			'ST' => $this->getState(),
			'L' => $this->getLocality(),
			'O' => $this->getOrganization(),
			'OU' => $this->getOrganizationUnit(),
		];
		$names = array_filter($names, function ($v) {
			return !empty($v);
		});
		return $names;
	}

	public function translateToLong($name): string {
		switch ($name) {
			case 'CN':
				return 'CommonName';
			case 'C':
				return 'Country';
			case 'ST':
				return 'State';
			case 'L':
				return 'Locality';
			case 'O':
				return 'Organization';
			case 'OU':
				return 'OrganizationUnit';
		}
		return '';
	}
}
