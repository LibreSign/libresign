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
use OCA\Libresign\Service\CaIdentifierService;
use OCA\Libresign\Service\CertificatePolicyService;
use OCA\Libresign\Service\CrlService;
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
use Psr\Log\LoggerInterface;
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
 * @method IEngineHandler setOrganizationalUnit(array $organizationalUnit)
 * @method array getOrganizationalUnit()
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
	protected array $organizationalUnit = [];
	protected string $UID = '';
	protected string $password = '';
	protected string $configPath = '';
	protected string $engine = '';
	protected string $certificate = '';
	protected string $currentCaId = '';
	protected IAppData $appData;

	public function __construct(
		protected IConfig $config,
		protected IAppConfig $appConfig,
		protected IAppDataFactory $appDataFactory,
		protected IDateTimeFormatter $dateTimeFormatter,
		protected ITempManager $tempManager,
		protected CertificatePolicyService $certificatePolicyService,
		protected IURLGenerator $urlGenerator,
		protected CaIdentifierService $caIdentifierService,
		protected LoggerInterface $logger,
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

	public function getCaId(): string {
		$caId = $this->caIdentifierService->getCaId();
		if (empty($caId)) {
			$caId = $this->caIdentifierService->generateCaId($this->getName());
		}
		return $caId;
	}

	private function parseX509(string $x509): array {
		$parsed = openssl_x509_parse(openssl_x509_read($x509));

		$return = self::convertArrayToUtf8($parsed);

		foreach (['subject', 'issuer'] as $actor) {
			foreach ($return[$actor] as $part => $value) {
				if (is_string($value) && str_contains($value, ', ')) {
					$return[$actor][$part] = explode(', ', $value);
				} else {
					$return[$actor][$part] = $value;
				}
			}
		}

		$return['valid_from'] = $this->dateTimeFormatter->formatDateTime($parsed['validFrom_time_t']);
		$return['valid_to'] = $this->dateTimeFormatter->formatDateTime($parsed['validTo_time_t']);

		$this->addCrlValidationInfo($return, $x509);

		return $return;
	}

	private function addCrlValidationInfo(array &$certData, string $certPem): void {
		if (isset($certData['extensions']['crlDistributionPoints'])) {
			$crlDistributionPoints = $certData['extensions']['crlDistributionPoints'];

			preg_match_all('/URI:([^\s,\n]+)/', $crlDistributionPoints, $matches);
			$extractedUrls = $matches[1] ?? [];

			$certData['crl_urls'] = $extractedUrls;
			$certData['crl_validation'] = $this->validateCrlFromUrls($extractedUrls, $certPem);
		} else {
			$certData['crl_validation'] = 'missing';
			$certData['crl_urls'] = [];
		}
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

	#[\Override]
	public function getCurrentConfigPath(): string {
		if ($this->configPath) {
			return $this->configPath;
		}

		$customConfigPath = $this->appConfig->getValueString(Application::APP_ID, 'config_path');
		if ($customConfigPath && is_dir($customConfigPath)) {
			$this->configPath = $customConfigPath;
			return $this->configPath;
		}

		$this->configPath = $this->initializePkiConfigPath();
		if (!empty($this->configPath)) {
			$this->appConfig->setValueString(Application::APP_ID, 'config_path', $this->configPath);
		}
		return $this->configPath;
	}

	#[\Override]
	public function getConfigPathByParams(string $instanceId, int $generation): string {
		$engineName = $this->getName();

		$pkiDirName = $this->caIdentifierService->generatePkiDirectoryNameFromParams($instanceId, $generation, $engineName);
		$dataDir = $this->config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data/');
		$systemInstanceId = $this->config->getSystemValue('instanceid');
		$pkiPath = $dataDir . '/appdata_' . $systemInstanceId . '/libresign/' . $pkiDirName;

		if (!is_dir($pkiPath)) {
			throw new \RuntimeException("Config path does not exist for instanceId: {$instanceId}, generation: {$generation}");
		}

		return $pkiPath;
	}

	private function initializePkiConfigPath(): string {
		$caId = $this->getCaId();
		if (empty($caId)) {
			return '';
		}
		$pkiDirName = $this->caIdentifierService->generatePkiDirectoryName($caId);
		$dataDir = $this->config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data/');
		$instanceId = $this->config->getSystemValue('instanceid');
		$pkiPath = $dataDir . '/appdata_' . $instanceId . '/libresign/' . $pkiDirName;

		if (!is_dir($pkiPath)) {
			$this->createDirectoryWithCorrectOwnership($pkiPath);
		}

		return $pkiPath;
	}

	private function createDirectoryWithCorrectOwnership(string $path): void {
		$ownerInfo = $this->getFilesOwnerInfo();
		$fullCommand = 'mkdir -p ' . escapeshellarg($path);

		if (posix_getuid() !== $ownerInfo['uid']) {
			$fullCommand = 'runuser -u ' . $ownerInfo['name'] . ' -- ' . $fullCommand;
		}

		exec($fullCommand);
	}

	private function getFilesOwnerInfo(): array {
		$currentFile = realpath(__DIR__);
		$owner = fileowner($currentFile);
		if ($owner === false) {
			throw new \RuntimeException('Unable to get file information');
		}
		$ownerInfo = posix_getpwuid($owner);
		if ($ownerInfo === false) {
			throw new \RuntimeException('Unable to get file owner information');
		}

		return $ownerInfo;
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
		$configPath = $this->getCurrentConfigPath();
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

			$hasLibresignCaUuid = $this->validateLibresignCaUuidInCertificate($parsed);
			if (!$hasLibresignCaUuid) {
				$minorIssues[] = 'LibreSign CA UUID not found in Organizational Unit';
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

	private function validateLibresignCaUuidInCertificate(array $parsed): bool {
		if (!isset($parsed['subject']['OU'])) {
			return false;
		}

		$instanceId = $this->getLibreSignInstanceId();
		if (empty($instanceId)) {
			return false;
		}

		$organizationalUnits = $parsed['subject']['OU'];

		if (is_string($organizationalUnits)) {
			if (str_contains($organizationalUnits, ', ')) {
				$organizationalUnits = explode(', ', $organizationalUnits);
			} else {
				$organizationalUnits = [$organizationalUnits];
			}
		}

		foreach ($organizationalUnits as $ou) {
			$ou = trim($ou);
			if ($this->caIdentifierService->isValidCaId($ou, $instanceId)) {
				return true;
			}
		}

		return false;
	}

	private function getLibreSignInstanceId(): string {
		$instanceId = $this->appConfig->getValueString(Application::APP_ID, 'instance_id', '');
		if (strlen($instanceId) === 10) {
			return $instanceId;
		}
		return '';
	}

	#[\Override]
	public function toArray(): array {
		$return = [
			'configPath' => $this->getCurrentConfigPath(),
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
		$caIdParsed = $this->caIdentifierService->getCaIdParsed();
		return $this->urlGenerator->linkToRouteAbsolute('libresign.crl.getRevocationList', [
			'instanceId' => $caIdParsed['instanceId'],
			'generation' => $caIdParsed['generation'],
			'engineType' => $caIdParsed['engineType'],
		]);
	}

	private function validateCrlFromUrls(array $crlUrls, string $certPem): string {
		if (empty($crlUrls)) {
			return 'no_urls';
		}

		$accessibleUrls = 0;
		foreach ($crlUrls as $crlUrl) {
			try {
				$validationResult = $this->downloadAndValidateCrl($crlUrl, $certPem);
				if ($validationResult === 'valid') {
					return 'valid';
				}
				if ($validationResult === 'revoked') {
					return 'revoked';
				}
				$accessibleUrls++;
			} catch (\Exception $e) {
				continue;
			}
		}

		if ($accessibleUrls === 0) {
			return 'urls_inaccessible';
		}

		return 'validation_failed';
	}

	private function downloadAndValidateCrl(string $crlUrl, string $certPem): string {
		try {
			if ($this->isLocalCrlUrl($crlUrl)) {
				$crlContent = $this->generateLocalCrl($crlUrl);
			} else {
				$crlContent = $this->downloadCrlContent($crlUrl);
			}

			if (!$crlContent) {
				throw new \Exception('Failed to get CRL content');
			}

			return $this->checkCertificateInCrl($certPem, $crlContent);

		} catch (\Exception $e) {
			return 'validation_error';
		}
	}

	private function isLocalCrlUrl(string $url): bool {
		$host = parse_url($url, PHP_URL_HOST);
		if (!$host) {
			return false;
		}

		$trustedDomains = $this->config->getSystemValue('trusted_domains', []);

		return in_array($host, $trustedDomains, true);
	}

	private function generateLocalCrl(string $crlUrl): ?string {
		try {
			$templateUrl = $this->urlGenerator->linkToRouteAbsolute('libresign.crl.getRevocationList', [
				'instanceId' => 'INSTANCEID',
				'generation' => 999999,
				'engineType' => 'ENGINETYPE',
			]);

			$patternUrl = str_replace('INSTANCEID', '([^/_]+)', $templateUrl);
			$patternUrl = str_replace('999999', '(\d+)', $patternUrl);
			$patternUrl = str_replace('ENGINETYPE', '([^/_]+)', $patternUrl);

			$escapedPattern = str_replace([':', '/', '.'], ['\:', '\/', '\.'], $patternUrl);
			$pattern = '/^' . $escapedPattern . '$/';

			if (preg_match($pattern, $crlUrl, $matches)) {
				$instanceId = $matches[1];
				$generation = (int)$matches[2];
				$engineType = $matches[3];

				/** @var CrlService */
				$crlService = \OC::$server->get(CrlService::class);

				$crlData = $crlService->generateCrlDer($instanceId, $generation, $engineType);

				return $crlData;
			}

			$this->logger->debug('CRL URL does not match expected pattern', ['url' => $crlUrl, 'pattern' => $pattern]);
			return null;

		} catch (\Exception $e) {
			$this->logger->warning('Failed to generate local CRL: ' . $e->getMessage());
			return null;
		}
	}

	private function downloadCrlContent(string $url): ?string {
		if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'])) {
			return null;
		}

		$context = stream_context_create([
			'http' => [
				'timeout' => 30,
				'user_agent' => 'LibreSign/1.0 CRL Validator',
				'follow_location' => 1,
				'max_redirects' => 3,
			]
		]);

		$content = @file_get_contents($url, false, $context);
		return $content !== false ? $content : null;
	}

	private function isSerialNumberInCrl(string $crlText, string $serialNumber): bool {
		return strpos($crlText, 'Serial Number: ' . strtoupper($serialNumber)) !== false
			|| strpos($crlText, 'Serial Number: ' . $serialNumber) !== false
			|| strpos($crlText, $serialNumber) !== false;
	}

	private function checkCertificateInCrl(string $certPem, string $crlContent): string {
		try {
			$certResource = openssl_x509_read($certPem);
			if (!$certResource) {
				return 'validation_error';
			}

			$certData = openssl_x509_parse($certResource);
			if (!isset($certData['serialNumber'])) {
				return 'validation_error';
			}

			$tempCrlFile = $this->tempManager->getTemporaryFile('.crl');
			file_put_contents($tempCrlFile, $crlContent);

			try {
				$crlTextCmd = sprintf(
					'openssl crl -in %s -inform DER -text 2>/dev/null || openssl crl -in %s -inform PEM -text 2>/dev/null',
					escapeshellarg($tempCrlFile),
					escapeshellarg($tempCrlFile)
				);

				exec($crlTextCmd, $output, $exitCode);

				if ($exitCode === 0) {
					$crlText = implode("\n", $output);

					if ($this->isSerialNumberInCrl($crlText, $certData['serialNumber'])
						|| (!empty($certData['serialNumberHex']) && $this->isSerialNumberInCrl($crlText, $certData['serialNumberHex']))) {
						return 'revoked';
					}

					return 'valid';
				}

				return 'validation_error';

			} finally {
				if (file_exists($tempCrlFile)) {
					unlink($tempCrlFile);
				}
			}

		} catch (\Exception $e) {
			return 'validation_error';
		}
	}

	#[\Override]
	public function generateCrlDer(array $revokedCertificates, string $instanceId, int $generation, int $crlNumber): string {
		$configPath = $this->getConfigPathByParams($instanceId, $generation);
		$caCertPath = $configPath . DIRECTORY_SEPARATOR . 'ca.pem';
		$caKeyPath = $configPath . DIRECTORY_SEPARATOR . 'ca-key.pem';
		$crlDerPath = $configPath . DIRECTORY_SEPARATOR . 'crl.der';

		if (!file_exists($caCertPath) || !file_exists($caKeyPath)) {
			throw new \RuntimeException('CA certificate or private key not found. Run: occ libresign:configure:openssl');
		}

		try {
			$caCert = file_get_contents($caCertPath);
			$caKey = file_get_contents($caKeyPath);

			if (!$caCert || !$caKey) {
				throw new \RuntimeException('Failed to read CA certificate or private key');
			}

			$issuer = new \phpseclib\File\X509();
			$issuer->loadX509($caCert);
			$caPrivateKey = \phpseclib\Crypt\PublicKeyLoader::load($caKey);

			if (!$caPrivateKey instanceof \phpseclib\Crypt\Common\PrivateKey) {
				throw new \RuntimeException('Loaded key is not a private key');
			}

			$issuer->setPrivateKey($caPrivateKey);

			$utc = new \DateTimeZone('UTC');
			$now = (new \DateTime())->setTimezone($utc);
			$nextWeek = (new \DateTime('+7 days'))->setTimezone($utc);

			$revokedList = [];
			foreach ($revokedCertificates as $cert) {
				$revokedList[] = [
					'userCertificate' => new \phpseclib\Math\BigInteger($cert->getSerialNumber(), 16),
					'revocationDate' => ['utcTime' => $cert->getRevokedAt()->format('D, d M Y H:i:s O')],
				];
			}

			$crlStructure = [
				'tbsCertList' => [
					'version' => 'v2',
					'signature' => ['algorithm' => 'sha256WithRSAEncryption'],
					'issuer' => $issuer->getSubjectDN(\phpseclib\File\X509::DN_ARRAY),
					'thisUpdate' => ['utcTime' => $now],
					'nextUpdate' => ['utcTime' => $nextWeek],
					'revokedCertificates' => $revokedList,
				],
				'signatureAlgorithm' => ['algorithm' => 'sha256WithRSAEncryption'],
			];

			$crl = new \phpseclib\File\X509();
			$crl->loadCRL($crlStructure);
			$crl->setSerialNumber((string)$crlNumber);
			$crl->setStartDate(new \DateTime('-1 minute'));
			$crl->setEndDate(new \DateTime('+7 days'));

			$signedCrl = $crl->signCRL($issuer, $crl, 'sha256WithRSAEncryption');

			if ($signedCrl === false) {
				throw new \RuntimeException('Failed to sign CRL with phpseclib3');
			}

			if (!isset($signedCrl['signatureAlgorithm'])) {
				$signedCrl['signatureAlgorithm'] = ['algorithm' => 'sha256WithRSAEncryption'];
			}

			$crlDerData = $crl->saveCRL($signedCrl, \phpseclib\File\X509::FORMAT_DER);

			if ($crlDerData === false) {
				throw new \RuntimeException('Failed to save CRL in DER format');
			}

			if (file_put_contents($crlDerPath, $crlDerData) === false) {
				throw new \RuntimeException('Failed to write CRL DER file');
			}

			return $crlDerData;
		} catch (\Exception $e) {
			$this->logger->error('CRL generation failed: ' . $e->getMessage(), ['exception' => $e]);
			throw new \RuntimeException('Failed to generate CRL: ' . $e->getMessage(), 0, $e);
		}
	}
}
