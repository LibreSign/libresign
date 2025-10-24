<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\CertificateEngine;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\EmptyCertificateException;
use OCA\Libresign\Exception\InvalidPasswordException;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\ConfigureCheckHelper;
use OCA\Libresign\Helper\MagicGetterSetterTrait;
use OCA\Libresign\Service\CertificatePolicyService;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\ITempManager;
use OCP\IURLGenerator;
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
 * @method IEngineHandler setUID(string $UID)
 * @method string getName()
 */
abstract class AEngineHandler implements IEngineHandler {
	use MagicGetterSetterTrait;
	use OrderCertificatesTrait;

	protected string $commonName = '';
	protected array $hosts = [];
	protected string $friendlyName = '';
	protected string $country = '';
	protected string $state = '';
	protected string $locality = '';
	protected string $organization = '';
	protected string $organizationalUnit = '';
	protected string $UID = '';
	protected string $password = '';
	protected string $configPath = '';
	protected string $engine = '';
	protected string $certificate = '';
	protected IAppData $appData;

	public function __construct(
		protected IConfig $config,
		protected IAppConfig $appConfig,
		protected IAppDataFactory $appDataFactory,
		protected IDateTimeFormatter $dateTimeFormatter,
		protected ITempManager $tempManager,
		protected CertificatePolicyService $certificatePolicyService,
		protected IURLGenerator $urlGenerator,
	) {
		$this->appData = $appDataFactory->get('libresign');
	}

	protected function exportToPkcs12(
		OpenSSLCertificate|string $certificate,
		OpenSSLAsymmetricKey|OpenSSLCertificate|string $privateKey,
		array $options = [],
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
				$options,
			);
			if (!$certContent) {
				throw new \Exception();
			}
		} catch (\Throwable) {
			throw new LibresignException('Error while creating certificate file', 500);
		}

		return $certContent;
	}

	#[\Override]
	public function updatePassword(string $certificate, string $currentPrivateKey, string $newPrivateKey): string {
		if (empty($certificate) || empty($currentPrivateKey) || empty($newPrivateKey)) {
			throw new EmptyCertificateException();
		}
		$certContent = $this->opensslPkcs12Read($certificate, $currentPrivateKey);
		$this->setPassword($newPrivateKey);
		$certContent = self::exportToPkcs12($certContent['cert'], $certContent['pkey']);
		return $certContent;
	}

	#[\Override]
	public function readCertificate(string $certificate, string $privateKey): array {
		if (empty($certificate) || empty($privateKey)) {
			throw new EmptyCertificateException();
		}
		$certContent = $this->opensslPkcs12Read($certificate, $privateKey);

		$return = $this->parseX509($certContent['cert']);
		if (isset($certContent['extracerts'])) {
			foreach ($certContent['extracerts'] as $extraCert) {
				$return['extracerts'][] = $this->parseX509($extraCert);
			}
			$return['extracerts'] = $this->orderCertificates($return['extracerts']);
		}
		return $return;
	}

	private function parseX509(string $x509): array {
		$parsed = openssl_x509_parse(openssl_x509_read($x509));

		$return = self::convertArrayToUtf8($parsed);

		$return['valid_from'] = $this->dateTimeFormatter->formatDateTime($parsed['validFrom_time_t']);
		$return['valid_to'] = $this->dateTimeFormatter->formatDateTime($parsed['validTo_time_t']);
		return $return;
	}

	private static function convertArrayToUtf8($array) {
		foreach ($array as $key => $value) {
			if (is_array($value)) {
				$array[$key] = self::convertArrayToUtf8($value);
			} elseif (is_string($value)) {
				$array[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
			}
		}
		return $array;
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
			openssl_pkcs12_read($certificateRepacked, $certContent, $privateKey);
			if (!empty($certContent)) {
				$certificate = $certificateRepacked;
				return $certContent;
			}
		}
		throw new InvalidPasswordException();
	}

	/**
	 * @param (int|string) $name
	 *
	 * @psalm-param array-key $name
	 */
	public function translateToLong($name): string {
		return match ($name) {
			'CN' => 'CommonName',
			'C' => 'Country',
			'ST' => 'State',
			'L' => 'Locality',
			'O' => 'Organization',
			'OU' => 'OrganizationalUnit',
			'UID' => 'UserIdentifier',
			default => '',
		};
	}

	public function setEngine(string $engine): void {
		$this->appConfig->setValueString(Application::APP_ID, 'certificate_engine', $engine);
		$this->engine = $engine;
	}

	#[\Override]
	public function getEngine(): string {
		if ($this->engine) {
			return $this->engine;
		}
		$this->engine = $this->appConfig->getValueString(Application::APP_ID, 'certificate_engine', 'openssl');
		return $this->engine;
	}

	#[\Override]
	public function populateInstance(array $rootCert): IEngineHandler {
		if (empty($rootCert)) {
			$rootCert = $this->appConfig->getValueArray(Application::APP_ID, 'rootCert');
		}
		if (!$rootCert) {
			return $this;
		}
		if (!empty($rootCert['names'])) {
			foreach ($rootCert['names'] as $id => $customName) {
				$longCustomName = $this->translateToLong($id);
				// Prevent to save a property that don't exists
				if (!property_exists($this, lcfirst($longCustomName))) {
					continue;
				}
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
		$this->configPath = $this->appConfig->getValueString(Application::APP_ID, 'config_path');
		if ($this->configPath && str_ends_with($this->configPath, $this->getName() . '_config')) {
			return $this->configPath;
		}
		try {
			$folder = $this->appData->getFolder($this->getName() . '_config');
			if (!$folder->fileExists('/')) {
				throw new \Exception();
			}
		} catch (\Throwable) {
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
		$folder = $reflectionProperty->getValue($node);
		$path = $folder->getInternalPath();
		return $path;
	}

	#[\Override]
	public function setConfigPath(string $configPath): IEngineHandler {
		if (!$configPath) {
			$this->appConfig->deleteKey(Application::APP_ID, 'config_path');
		} else {
			if (!is_dir($configPath)) {
				mkdir(
					directory: $configPath,
					recursive: true,
				);
			}
			$this->appConfig->setValueString(Application::APP_ID, 'config_path', $configPath);
		}
		$this->configPath = $configPath;
		return $this;
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
		if ($uid = $this->getUID()) {
			$names['UID'] = $uid;
		}
		$names = array_filter($names, fn ($v) => !empty($v));
		return $names;
	}

	public function getUID(): string {
		return str_replace(' ', '+', $this->UID);
	}

	#[\Override]
	public function getLeafExpiryInDays(): int {
		$exp = $this->appConfig->getValueInt(Application::APP_ID, 'expiry_in_days', 365);
		return $exp > 0 ? $exp : 365;
	}

	#[\Override]
	public function getCaExpiryInDays(): int {
		$exp = $this->appConfig->getValueInt(Application::APP_ID, 'ca_expiry_in_days', 3650); // 10 years
		return $exp > 0 ? $exp : 3650;
	}

	private function getCertificatePolicy(): array {
		$return = ['policySection' => []];
		$oid = $this->certificatePolicyService->getOid();
		$cps = $this->certificatePolicyService->getCps();
		if ($oid && $cps) {
			$return['policySection'][] = [
				'OID' => $oid,
				'CPS' => $cps,
			];
		}
		return $return;
	}

	abstract protected function getConfigureCheckResourceName(): string;

	abstract protected function getCertificateRegenerationTip(): string;

	abstract protected function getEngineSpecificChecks(): array;

	abstract protected function getSetupSuccessMessage(): string;

	abstract protected function getSetupErrorMessage(): string;

	abstract protected function getSetupErrorTip(): string;

	#[\Override]
	public function configureCheck(): array {
		$checks = $this->getEngineSpecificChecks();

		if (!$this->isSetupOk()) {
			return array_merge($checks, [
				(new ConfigureCheckHelper())
					->setErrorMessage($this->getSetupErrorMessage())
					->setResource($this->getConfigureCheckResourceName())
					->setTip($this->getSetupErrorTip())
			]);
		}

		$checks[] = (new ConfigureCheckHelper())
			->setSuccessMessage($this->getSetupSuccessMessage())
			->setResource($this->getConfigureCheckResourceName());

		$modernFeaturesCheck = $this->checkRootCertificateModernFeatures();
		if ($modernFeaturesCheck) {
			$checks[] = $modernFeaturesCheck;
		}

		return $checks;
	}

	protected function checkRootCertificateModernFeatures(): ?ConfigureCheckHelper {
		$configPath = $this->getConfigPath();
		$caCertPath = $configPath . DIRECTORY_SEPARATOR . 'ca.pem';

		try {
			$certContent = file_get_contents($caCertPath);
			if (!$certContent) {
				return (new ConfigureCheckHelper())
					->setErrorMessage('Failed to read root certificate file')
					->setResource($this->getConfigureCheckResourceName())
					->setTip('Check file permissions and disk space');
			}

			$x509Resource = openssl_x509_read($certContent);
			if (!$x509Resource) {
				return (new ConfigureCheckHelper())
					->setErrorMessage('Failed to parse root certificate')
					->setResource($this->getConfigureCheckResourceName())
					->setTip('Root certificate file may be corrupted or invalid');
			}

			$parsed = openssl_x509_parse($x509Resource);
			if (!$parsed) {
				return (new ConfigureCheckHelper())
					->setErrorMessage('Failed to extract root certificate information')
					->setResource($this->getConfigureCheckResourceName())
					->setTip('Root certificate may be in an unsupported format');
			}

			$criticalIssues = [];
			$minorIssues = [];

			if (isset($parsed['serialNumber'])) {
				$serialNumber = $parsed['serialNumber'];
				$serialDecimal = hexdec($serialNumber);
				if ($serialDecimal <= 1) {
					$minorIssues[] = 'Serial number is simple (zero or one)';
				}
			} else {
				$criticalIssues[] = 'Serial number is missing';
			}

			$missingExtensions = [];
			if (!isset($parsed['extensions']['subjectKeyIdentifier'])) {
				$missingExtensions[] = 'Subject Key Identifier (SKI)';
			}

			$isSelfSigned = (isset($parsed['issuer']) && isset($parsed['subject'])
							&& $parsed['issuer'] === $parsed['subject']);

			/**
			 * @todo workarround for missing AKI at certificates generated by CFSSL.
			 *
			 * CFSSL does not add Authority Key Identifier (AKI) to self-signed root certificates.
			 */
			if (!$isSelfSigned && !isset($parsed['extensions']['authorityKeyIdentifier'])) {
				$missingExtensions[] = 'Authority Key Identifier (AKI)';
			}

			if (!isset($parsed['extensions']['crlDistributionPoints'])) {
				$missingExtensions[] = 'CRL Distribution Points';
			}

			if (!empty($missingExtensions)) {
				$extensionsList = implode(', ', $missingExtensions);
				$minorIssues[] = "Missing modern extensions: {$extensionsList}";
			}

			if (!empty($criticalIssues)) {
				$issuesList = implode(', ', $criticalIssues);
				return (new ConfigureCheckHelper())
					->setErrorMessage("Root certificate has critical issues: {$issuesList}")
					->setResource($this->getConfigureCheckResourceName())
					->setTip($this->getCertificateRegenerationTip());
			}

			if (!empty($minorIssues)) {
				$issuesList = implode(', ', $minorIssues);
				return (new ConfigureCheckHelper())
					->setInfoMessage("Root certificate could benefit from modern features: {$issuesList}")
					->setResource($this->getConfigureCheckResourceName())
					->setTip($this->getCertificateRegenerationTip() . ' (recommended but not required)');
			}

			return null;

		} catch (\Exception $e) {
			return (new ConfigureCheckHelper())
				->setErrorMessage('Failed to analyze root certificate: ' . $e->getMessage())
				->setResource($this->getConfigureCheckResourceName())
				->setTip('Check if the root certificate file is valid');
		}
	}

	#[\Override]
	public function toArray(): array {
		$return = [
			'configPath' => $this->getConfigPath(),
			'generated' => $this->isSetupOk(),
			'rootCert' => [
				'commonName' => $this->getCommonName(),
				'names' => [],
			],
		];
		$return = array_merge(
			$return,
			$this->getCertificatePolicy(),
		);
		$names = $this->getNames();
		foreach ($names as $name => $value) {
			$return['rootCert']['names'][] = [
				'id' => $name,
				'value' => $value,
			];
		}
		return $return;
	}

	protected function getCrlDistributionUrl(): string {
		return $this->urlGenerator->linkToRouteAbsolute('libresign.crl.getRevocationList');
	}
}
