<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\CertificateEngine;

use OCA\Libresign\Exception\EmptyCertificateException;
use OCA\Libresign\Exception\InvalidPasswordException;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\MagicGetterSetterTrait;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\ITempManager;
use OpenSSLAsymmetricKey;
use OpenSSLCertificate;
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
 * @method IEngineHandler setOrganizationalUnit(string $organizationalUnit)
 * @method string getOrganizationalUnit()
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
	protected string $organizationalUnit = '';
	protected string $password = '';
	protected string $configPath = '';
	protected string $engine = '';
	protected IAppData $appData;

	public function __construct(
		protected IConfig $config,
		protected IAppConfig $appConfig,
		protected IAppDataFactory $appDataFactory,
		protected IDateTimeFormatter $dateTimeFormatter,
		protected ITempManager $tempManager,
	) {
		$this->appData = $appDataFactory->get('libresign');
	}

	protected function exportToPkcs12(
		OpenSSLCertificate|string $certificate,
		OpenSSLAsymmetricKey|OpenSSLCertificate|string $privateKey
	): string {
		if (empty($certificate) || empty($privateKey)) {
			throw new EmptyCertificateException();
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
			throw new EmptyCertificateException();
		}
		$certContent = $this->opensslPkcs12Read($certificate, $currentPrivateKey);
		$this->setPassword($newPrivateKey);
		$certContent = self::exportToPkcs12($certContent['cert'], $certContent['pkey']);
		return $certContent;
	}

	public function readCertificate(string $certificate, string $privateKey): array {
		if (empty($certificate) || empty($privateKey)) {
			throw new EmptyCertificateException();
		}
		$certContent = $this->opensslPkcs12Read($certificate, $privateKey);
		$parsed = openssl_x509_parse(openssl_x509_read($certContent['cert']));

		$return['name'] = $parsed['name'];
		$return['subject'] = $parsed['subject'];
		$return['issuer'] = $parsed['issuer'];
		$return['extensions'] = $parsed['extensions'];
		$return['validate'] = [
			'from' => $this->dateTimeFormatter->formatDateTime($parsed['validFrom_time_t']),
			'to' => $this->dateTimeFormatter->formatDateTime($parsed['validTo_time_t']),
		];
		return $return;
	}

	public function opensslPkcs12Read(string &$certificate, string $privateKey): array {
		openssl_pkcs12_read($certificate, $certContent, $privateKey);
		if (!empty($certContent)) {
			return $certContent;
		}
		/**
		 * Reference:
		 *
		 * https://github.com/php/php-src/issues/12128
		 * https://www.php.net/manual/en/function.openssl-pkcs12-read.php#128992
		 */
		$msg = openssl_error_string();
		if ($msg === 'error:0308010C:digital envelope routines::unsupported') {
			$tempPassword = $this->tempManager->getTemporaryFile();
			$tempEncriptedOriginal = $this->tempManager->getTemporaryFile();
			$tempEncriptedRepacked = $this->tempManager->getTemporaryFile();
			$tempDecrypted = $this->tempManager->getTemporaryFile();
			file_put_contents($tempPassword, $privateKey);
			file_put_contents($tempEncriptedOriginal, $certificate);
			shell_exec(<<<REPACK_COMMAND
				cat $tempPassword | openssl pkcs12 -legacy -in $tempEncriptedOriginal -nodes -out $tempDecrypted -passin stdin &&
				cat $tempPassword | openssl pkcs12 -in $tempDecrypted -export -out $tempEncriptedRepacked -passout stdin
				REPACK_COMMAND
			);
			$certificateRepacked = file_get_contents($tempEncriptedRepacked);
			$this->tempManager->clean();
			openssl_pkcs12_read($certificateRepacked, $certContent, $privateKey);
			if (!empty($certContent)) {
				$certificate = $certificateRepacked;
				return $certContent;
			}
		}
		throw new InvalidPasswordException();
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
				return 'OrganizationalUnit';
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
			$currentFile = realpath(__DIR__);
			$owner = posix_getpwuid(fileowner($currentFile));
			$fullCommand = 'mkdir -p "' . $this->configPath . '"';
			if (posix_getuid() !== $owner['uid']) {
				$fullCommand = 'runuser -u ' . $owner['name'] . ' -- ' . $fullCommand;
			}
			exec($fullCommand);
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

	protected function getNames(): array {
		$names = [
			'C' => $this->getCountry(),
			'ST' => $this->getState(),
			'L' => $this->getLocality(),
			'O' => $this->getOrganization(),
			'OU' => $this->getOrganizationalUnit(),
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
