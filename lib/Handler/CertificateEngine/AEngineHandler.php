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

use OCA\Libresign\Exception\EmptyRootCertificateException;
use OCA\Libresign\Exception\InvalidPasswordException;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\MagicGetterSetterTrait;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IConfig;
use ReflectionClass;

/**
 * @method IEngineHandler setPassword(string $password)
 * @method string getPassword()
 * @method IEngineHandler setCommonName(string $commonName)
 * @method string getCommonName()
 * @method IEngineHandler setHosts(array $hosts)
 * @method array getHosts()
 * @method IEngineHandler setFriendlyName(string $friendlyName)
 * @method string getFriendlyName()
 * @method IEngineHandler setCountry(string $country)
 * @method string getCountry()
 * @method IEngineHandler setState(string $state)
 * @method string getState()
 * @method IEngineHandler setLocality(string $locality)
 * @method string getLocality()
 * @method IEngineHandler setOrganization(string $organization)
 * @method string getOrganization()
 * @method IEngineHandler setOrganizationUnit(string $organizationUnit)
 * @method string getOrganizationUnit()
 * @method string getName()
 */
class AEngineHandler {
	use MagicGetterSetterTrait;

	protected string $commonName = '';
	protected array $hosts = [];
	protected string $friendlyName = '';
	protected string $country = '';
	protected string $state = '';
	protected string $locality = '';
	protected string $organization = '';
	protected string $organizationUnit = '';
	protected string $password = '';
	protected string $configPath = '';
	protected string $engine = '';
	protected IAppData $appData;

	public function __construct(
		protected IConfig $config,
		protected IAppConfig $appConfig,
		protected IAppDataFactory $appDataFactory,
	) {
		$this->appData = $appDataFactory->get('libresign');
	}

	public function generateCertificate(string $certificate, string $privateKey): string {
		if (empty($certificate) || empty($privateKey)) {
			throw new EmptyRootCertificateException();
		}
		$certContent = null;
		try {
			openssl_pkcs12_export(
				$certificate,
				$certContent,
				$privateKey,
				$this->getPassword(),
				['friendly_name' => $this->getFriendlyName()],
			);
			if (!$certContent) {
				throw new \Exception();
			}
		} catch (\Throwable $th) {
			throw new LibresignException('Error while creating certificate file', 500);
		}

		return $certContent;
	}

	public function updatePassword(string $certificate, string $currentPrivateKey, string $newPrivateKey): string {
		if (empty($certificate) || empty($currentPrivateKey) || empty($newPrivateKey)) {
			throw new EmptyRootCertificateException();
		}
		openssl_pkcs12_read($certificate, $certContent, $currentPrivateKey);
		if (empty($certContent)) {
			throw new InvalidPasswordException();
		}
		$this->setPassword($newPrivateKey);
		$certContent = self::generateCertificate($certContent['cert'], $certContent['pkey']);
		return $certContent;
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

	public function setEngine(string $engine): void {
		$this->appConfig->setAppValue('certificate_engine', $engine);
		$this->engine = $engine;
	}

	public function getEngine(): string {
		$this->engine = $this->appConfig->getAppValue('certificate_engine', 'openssl');
		return $this->engine;
	}

	public function populateInstance(array $rootCert): self {
		if (empty($rootCert)) {
			$rootCert = $this->appConfig->getAppValue('root_cert');
			$rootCert = json_decode($rootCert, true);
		}
		if (!$rootCert) {
			return $this;
		}
		if (!empty($rootCert['names'])) {
			foreach ($rootCert['names'] as $id => $customName) {
				$longCustomName = $this->translateToLong($id);
				$this->{'set' . ucfirst($longCustomName)}($customName['value']);
			}
		}
		if (!$this->getCommonName()) {
			$this->setCommonName($rootCert['commonName']);
		}
		return $this;
	}

	public function getConfigPath(): string {
		if ($this->configPath) {
			return $this->configPath;
		}
		$this->configPath = $this->appConfig->getAppValue('config_path');
		if ($this->configPath && str_ends_with($this->configPath, $this->getName() . '_config')) {
			return $this->configPath;
		}
		try {
			$folder = $this->appData->getFolder($this->getName() . '_config');
			if (!$folder->fileExists('/')) {
				throw new \Exception();
			}
		} catch (\Throwable $th) {
			$folder = $this->appData->newFolder($this->getName() . '_config');
		}
		$dataDir = $this->config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data/');
		$this->configPath = $dataDir . '/' . $this->getInternalPathOfFolder($folder);
		if (!is_dir($this->configPath)) {
			exec('mkdir -p "' . $this->configPath . '"');
		}
		return $this->configPath;
	}

	/**
	 * @todo check a best solution to don't use reflection
	 */
	private function getInternalPathOfFolder(ISimpleFolder $node): string {
		$reflection = new \ReflectionClass($node);
		$reflectionProperty = $reflection->getProperty('folder');
		$reflectionProperty->setAccessible(true);
		$folder = $reflectionProperty->getValue($node);
		$path = $folder->getInternalPath();
		return $path;
	}

	public function setConfigPath(string $configPath): void {
		if (!$configPath) {
			$this->appConfig->deleteAppValue('config_path');
		} else {
			$this->appConfig->setAppValue('config_path', $configPath);
		}
		$this->configPath = $configPath;
	}

	public function getName(): string {
		$reflect = new ReflectionClass($this);
		$className = $reflect->getShortName();
		$name = strtolower(substr($className, 0, -7));
		return $name;
	}

	private function getNames(): array {
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

	public function isSetupOk(): bool {
		return $this->appConfig->getAppValue('authkey') ? true : false;
	}

	public function toArray(): array {
		$return = [
			'configPath' => $this->getConfigPath(),
			'rootCert' => [
				'commonName' => $this->getCommonName(),
				'names' => [],
			],
		];
		$names = $this->getNames();
		foreach ($names as $name => $value) {
			$return['rootCert']['names'][] = [
				'id' => $name,
				'value' => $value,
			];
		}
		return $return;
	}
}
