<?php

namespace OCA\Libresign\Handler\CertificateEngine;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Helper\MagicGetterSetterTrait;
use OCP\IConfig;

/**
 * @method ICertificateEngineHandler setPassword(string $password)
 * @method string getPassword()
 * @method ICertificateEngineHandler setCommonName(string $commonName)
 * @method string getCommonName()
 * @method ICertificateEngineHandler setHosts(array $hosts)
 * @method array getHosts()
 * @method ICertificateEngineHandler setFriendlyName(string $friendlyName)
 * @method string getFriendlyName()
 * @method ICertificateEngineHandler setCountry(string $country)
 * @method string getCountry()
 * @method ICertificateEngineHandler setState(string $state)
 * @method string getState()
 * @method ICertificateEngineHandler setLocality(string $locality)
 * @method string getLocality()
 * @method ICertificateEngineHandler setOrganization(string $organization)
 * @method string getOrganization()
 * @method ICertificateEngineHandler setOrganizationUnit(string $organizationUnit)
 * @method string getOrganizationUnit()
 * @method string getConfigPath()
 */
class CertificateEngineHandler implements ICertificateEngineHandler {
	use MagicGetterSetterTrait;

	protected string $commonName;
	protected array $hosts = [];
	protected string $friendlyName;
	protected string $country;
	protected string $state;
	protected string $locality;
	protected string $organization;
	protected string $organizationUnit;
	protected string $password;
	protected string $configPath;
	protected string $engine;

	public function __construct(
		protected IConfig $config
	) {
	}

	public function generateRootCert(
		string $commonName,
		array $names = [],
		string $configPath = '',
	): string {
		return '';
	}

	public function isOk(): bool {
		return false;
	}

	public function generateCertificate(): string {
		return '';
	}

	public function getInstance(): ICertificateEngineHandler {
		$rootCert = $this->config->getAppValue(Application::APP_ID, 'rootCert');
		$rootCert = json_decode($rootCert, true);
		if (!empty($rootCert['names'])) {
			foreach ($rootCert['names'] as $id => $customName) {
				$longCustomName = $this->translateToLong($id);
				$this->{'set' . ucfirst($longCustomName)}($customName['value']);
			}
		}
		if (!$this->getCommonName()) {
			$this->setCommonName($rootCert['commonName']);
		}
		if (!$this->getConfigPath()) {
			$this->setConfigPath($this->config->getAppValue(Application::APP_ID, 'configPath'));
		}
		return $this;
	}

	protected function translateToLong($name): string {
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

	protected function setEngine(string $engine): void {
		$this->config->setAppValue(Application::APP_ID, 'certificate_engine', $engine);
		$this->engine = $engine;
	}

	protected function setConfigPath(string $configPath): void {
		$this->config->setAppValue(Application::APP_ID, 'config_path', $configPath);
	}
}
