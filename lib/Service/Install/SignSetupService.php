<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Install;

use LibreSign\WhatOSAmI\OperatingSystem;
use OC\IntegrityCheck\Helpers\EnvironmentHelper;
use OC\IntegrityCheck\Helpers\FileAccessHelper;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\EmptySignatureDataException;
use OCA\Libresign\Exception\InvalidSignatureException;
use OCA\Libresign\Exception\SignatureDataNotFoundException;
use OCP\App\IAppManager;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IAppConfig;
use OCP\IConfig;
use phpseclib\Crypt\RSA;
use phpseclib\File\X509;

class SignSetupService {
	private array $exclude = [
		'openssl_config',
		'cfssl_config',
		'unauthetnicated',
	];
	private string $architecture;
	private string $resource;
	private array $signatureData = [];
	private bool $willUseLocalCert = false;
	private string $distro = '';
	private ?X509 $x509 = null;
	private ?RSA $rsa = null;
	private string $instanceId;
	private IAppData $appData;
	public function __construct(
		private EnvironmentHelper $environmentHelper,
		private FileAccessHelper $fileAccessHelper,
		private IConfig $config,
		private IAppConfig $appConfig,
		private IAppManager $appManager,
		private IAppDataFactory $appDataFactory,
	) {
		$this->instanceId = $this->config->getSystemValue('instanceid');
		$this->appData = $appDataFactory->get('libresign');
	}

	public function setArchitecture(string $architecture): self {
		$this->architecture = $architecture;
		return $this;
	}

	public function setResource(string $resource): self {
		$this->resource = $resource;
		return $this;
	}

	public function getArchitectures(): array {
		$appInfo = $this->appManager->getAppInfo(Application::APP_ID);
		if (empty($appInfo['dependencies']['architecture'])) {
			throw new \Exception('dependencies>architecture not found at info.xml');
		}
		return $appInfo['dependencies']['architecture'];
	}

	public function setPrivateKey(RSA $privateKey): void {
		$this->rsa = $privateKey;
	}

	public function setCertificate(x509 $x509): void {
		$this->x509 = $x509;
	}

	public function willUseLocalCert(bool $willUseLocalCert): void {
		$this->willUseLocalCert = $willUseLocalCert;
	}

	private function getPrivateKey(): RSA {
		if (!$this->rsa instanceof RSA) {
			if (file_exists(__DIR__ . '/../../../build/tools/certificates/local/libresign.key')) {
				$privateKey = file_get_contents(__DIR__ . '/../../../build/tools/certificates/local/libresign.key');
				$this->rsa = new RSA();
				$this->rsa->loadKey($privateKey);
			} else {
				$this->getDevelopCert();
			}
		}
		return $this->rsa;
	}

	private function getCertificate(): X509 {
		if (!$this->x509 instanceof x509) {
			if (file_exists(__DIR__ . '/../../../build/tools/certificates/local/libresign.crt')) {
				$x509 = file_get_contents(__DIR__ . '/../../../build/tools/certificates/local/libresign.crt');
				$this->x509 = new X509();
				$this->x509->loadX509($x509);
				$this->x509->setPrivateKey($this->getPrivateKey());
			} else {
				$this->getDevelopCert();
			}
		}
		return $this->x509;
	}

	/**
	 * Write the signature of the app in the specified folder
	 *
	 * @param string $path
	 * @param X509 $certificate
	 * @param RSA $privateKey
	 * @throws \Exception
	 */
	public function writeAppSignature() {
		try {
			$iterator = $this->getFolderIterator($this->getInstallPath());
			$hashes = $this->generateHashes($iterator);
			$signature = $this->createSignatureData($hashes);
			$this->fileAccessHelper->file_put_contents(
				$this->getFileName(),
				json_encode($signature, JSON_PRETTY_PRINT)
			);
		} catch (NotFoundException $e) {
			throw new \Exception(sprintf(
				"Folder %s not found.\nIs necessary to run this command first: occ libresign:install --%s --architecture=%s",
				$e->getMessage(),
				$this->resource,
				$this->architecture,
			));
		} catch (\Exception $e) {
			$appInfoDir = $this->getAppInfoDirectory();
			if (!$this->fileAccessHelper->is_writable($appInfoDir)) {
				throw new \Exception($appInfoDir . ' is not writable. Original error: ' . $e->getMessage());
			}
			throw $e;
		}
	}

	public function getInstallPath(): string {
		switch ($this->resource) {
			case 'java':
				$path = $this->appConfig->getValueString(Application::APP_ID, 'java_path');
				if (!$path) {
					// fallback
					try {
						$folder = $this->appData->getFolder('/');
						$path = $this->architecture . '/' . $this->getLinuxDistributionToDownloadJava() . '/java';
						$folder = $folder->getFolder($path, $folder);
						$path = $this->getDataDir() . '/' . $this->getInternalPathOfFolder($folder);
						if (is_dir($path)) {
							return $path;
						}
						throw new InvalidSignatureException('Java path not found at app config.');
					} catch (\Throwable) {
						throw new InvalidSignatureException('Java path not found at app config.');
					}
				}
				$installPath = substr($path, 0, -strlen('/bin/java'));
				$distro = $this->getLinuxDistributionToDownloadJava();
				$expected = "{$this->instanceId}/libresign/{$this->architecture}/{$distro}/java";
				if (!str_contains($installPath, $expected)) {
					$installPath = preg_replace(
						"/{$this->instanceId}\/libresign\/(\w+)\/(\w+)\/java/i",
						$expected,
						$installPath
					);
				}
				break;
			case 'jsignpdf':
				$path = $this->appConfig->getValueString(Application::APP_ID, 'jsignpdf_jar_path');
				if (!$path) {
					// fallback
					try {
						$folder = $this->appData->getFolder('/');
						$path = $this->architecture . '/jsignpdf';
						$folder = $folder->getFolder($path, $folder);
						$path = $this->getDataDir() . '/' . $this->getInternalPathOfFolder($folder);
						if (is_dir($path)) {
							return $path;
						}
						throw new InvalidSignatureException('JSignPdf path not found at app config.');
					} catch (\Throwable) {
						throw new InvalidSignatureException('JSignPdf path not found at app config.');
					}
				}
				$installPath = substr($path, 0, strrpos($path, '/', -strlen('_/JSignPdf.jar')));
				break;
			case 'pdftk':
				$path = $this->appConfig->getValueString(Application::APP_ID, 'pdftk_path');
				if (!$path) {
					// fallback
					try {
						$folder = $this->appData->getFolder('/');
						$path = $this->architecture . '/pdftk';
						$folder = $folder->getFolder($path, $folder);
						$path = $this->getDataDir() . '/' . $this->getInternalPathOfFolder($folder);
						if (is_dir($path)) {
							return $path;
						}
						throw new InvalidSignatureException('pdftk path not found at app config.');
					} catch (\Throwable) {
						throw new InvalidSignatureException('pdftk path not found at app config.');
					}
				}
				$installPath = substr($path, 0, -strlen('/pdftk.jar'));
				break;
			case 'cfssl':
				$path = $this->appConfig->getValueString(Application::APP_ID, 'cfssl_bin');
				if (!$path) {
					// fallback
					try {
						$folder = $this->appData->getFolder('/');
						$path = $this->architecture . '/cfssl';
						$folder = $folder->getFolder($path, $folder);
						$path = $this->getDataDir() . '/' . $this->getInternalPathOfFolder($folder);
						if (is_dir($path)) {
							return $path;
						}
						throw new InvalidSignatureException('cfssl path not found at app config.');
					} catch (\Throwable) {
						throw new InvalidSignatureException('cfssl path not found at app config.');
					}
				}
				$installPath = substr($path, 0, -strlen('/cfssl'));
				break;
			default:
				$installPath = '';
		}
		if (!str_contains((string)$installPath, $this->architecture)) {
			$installPath = preg_replace(
				"/{$this->instanceId}\/libresign\/(\w+)/i",
				"{$this->instanceId}/libresign/{$this->architecture}",
				(string)$installPath
			);
		}
		return (string)$installPath;
	}

	private function getDataDir(): string {
		$dataDir = $this->config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data/');
		return $dataDir;
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

	private function getFileName(): string {
		$appInfoDir = $this->getAppInfoDirectory();
		return $appInfoDir . '/' . $this->getSignatureFileName();
	}

	public function getSignatureFileName(): string {
		$path[] = 'install-' . $this->architecture;
		if ($this->resource === 'java') {
			$path[] = $this->getLinuxDistributionToDownloadJava();
		}
		$path[] = $this->resource . '.json';
		return implode('-', $path);
	}

	public function setDistro(string $distro): self {
		$this->distro = $distro;
		return $this;
	}

	/**
	 * Return linux or alpine-linux
	 */
	public function getLinuxDistributionToDownloadJava(): string {
		if ($this->distro) {
			return $this->distro;
		}
		$operatingSystem = new OperatingSystem();
		$distribution = $operatingSystem->getLinuxDistribution();
		if (strtolower($distribution) === 'alpine') {
			return 'alpine-linux';
		}
		return 'linux';
	}

	protected function getAppInfoDirectory(): string {
		$appInfoDir = (string)realpath(__DIR__ . '/../../../appinfo');
		$this->fileAccessHelper->assertDirectoryExists($appInfoDir);
		return $appInfoDir;
	}

	/**
	 * Split the certificate file in individual certs
	 *
	 * @param string $cert
	 * @return string[]
	 */
	private function splitCerts(string $cert): array {
		preg_match_all('([\-]{3,}[\S\ ]+?[\-]{3,}[\S\s]+?[\-]{3,}[\S\ ]+?[\-]{3,})', $cert, $matches);

		return $matches[0];
	}

	private function getSignatureData(): array {
		if (!empty($this->signatureData)) {
			return $this->signatureData;
		}
		$filename = $this->getFileName();
		if (!file_exists($filename)) {
			throw new SignatureDataNotFoundException('Signature data not found.');
		}
		$content = $this->fileAccessHelper->file_get_contents($filename);
		if (\is_string($content)) {
			$signatureData = json_decode($content, true);
		} else {
			$signatureData = null;
		}
		if (!\is_array($signatureData)) {
			throw new SignatureDataNotFoundException('Signature data not found.');
		}
		$this->signatureData = $signatureData;

		$this->validateIfIssignedByLibresignAppCertificate($signatureData['hashes']);

		return $this->signatureData;
	}

	private function getHashesOfResource(): array {
		$signatureData = $this->getSignatureData();
		if (count($signatureData['hashes']) === 0) {
			throw new EmptySignatureDataException('No signature files to ' . $this->resource);
		}
		return $signatureData;
	}

	private function getLibresignAppCertificate(): X509 {
		if ($this->x509 instanceof X509) {
			return $this->x509;
		}
		$signatureData = $this->getSignatureData();
		$certificate = $signatureData['certificate'];

		// Check if certificate is signed by Nextcloud Root Authority
		$rootCertificatePublicKey = $this->getRootCertificatePublicKey();
		$this->x509 = new X509();

		$rootCerts = $this->splitCerts($rootCertificatePublicKey);
		foreach ($rootCerts as $rootCert) {
			$this->x509->loadCA($rootCert);
		}
		$this->x509->loadX509($certificate);
		if (!$this->x509->validateSignature()) {
			throw new InvalidSignatureException('Certificate is not valid.');
		}

		// Verify if certificate has proper CN. "core" CN is always trusted.
		if ($this->x509->getDN(X509::DN_OPENSSL)['CN'] !== Application::APP_ID && $this->x509->getDN(X509::DN_OPENSSL)['CN'] !== 'core') {
			throw new InvalidSignatureException(
				sprintf('Certificate is not valid for required scope. (Requested: %s, current: CN=%s)', Application::APP_ID, $this->x509->getDN(true)['CN'])
			);
		}

		return $this->x509;
	}

	private function validateIfIssignedByLibresignAppCertificate(array $expectedHashes): void {
		$x509 = $this->getLibresignAppCertificate();

		// Check if the signature of the files is valid
		$rsa = new RSA();
		$rsa->loadKey($x509->currentCert['tbsCertificate']['subjectPublicKeyInfo']['subjectPublicKey']);
		$rsa->setSignatureMode(RSA::SIGNATURE_PSS);
		$rsa->setMGFHash('sha512');
		// See https://tools.ietf.org/html/rfc3447#page-38
		$rsa->setSaltLength(0);

		$signatureData = $this->getSignatureData();
		$signature = base64_decode((string)$signatureData['signature']);
		if (!$rsa->verify(json_encode($expectedHashes), $signature)) {
			throw new InvalidSignatureException('Signature could not get verified.');
		}
	}

	public function verify(string $architecture, $resource): array {
		$this->signatureData = [];
		$this->architecture = $architecture;
		$this->resource = $resource;

		try {
			$expectedHashes = $this->getHashesOfResource();
			// Compare the list of files which are not identical
			$currentInstanceHashes = $this->generateHashes($this->getFolderIterator($this->getInstallPath()));
		} catch (EmptySignatureDataException $th) {
			return [
				'EMPTY_SIGNATURE_DATA' => $th->getMessage(),
			];
		} catch (SignatureDataNotFoundException $th) {
			return [
				'SIGNATURE_DATA_NOT_FOUND' => $th->getMessage(),
			];
		} catch (\Throwable $th) {
			return [
				'HASH_FILE_ERROR' => $th->getMessage(),
			];
		}

		$differencesA = array_diff($expectedHashes['hashes'], $currentInstanceHashes);
		$differencesB = array_diff($currentInstanceHashes, $expectedHashes['hashes']);
		$differences = array_merge($differencesA, $differencesB);
		$differenceArray = [];
		foreach ($differences as $filename => $hash) {
			// Check if file should not exist in the new signature table
			if (!array_key_exists($filename, $expectedHashes['hashes'])) {
				$differenceArray['EXTRA_FILE'][$filename]['expected'] = '';
				$differenceArray['EXTRA_FILE'][$filename]['current'] = $hash;
				continue;
			}

			// Check if file is missing
			if (!array_key_exists($filename, $currentInstanceHashes)) {
				$differenceArray['FILE_MISSING'][$filename]['expected'] = $expectedHashes['hashes'][$filename];
				$differenceArray['FILE_MISSING'][$filename]['current'] = '';
				continue;
			}

			// Check if hash does mismatch
			if ($expectedHashes['hashes'][$filename] !== $currentInstanceHashes[$filename]) {
				$differenceArray['INVALID_HASH'][$filename]['expected'] = $expectedHashes['hashes'][$filename];
				$differenceArray['INVALID_HASH'][$filename]['current'] = $currentInstanceHashes[$filename];
				continue;
			}

			// Should never happen.
			throw new \Exception('Invalid behaviour in file hash comparison experienced. Please report this error to the developers.');
		}

		return $differenceArray;
	}

	/**
	 * Enumerates all files belonging to the folder. Sensible defaults are excluded.
	 *
	 * @param string $folderToIterate
	 * @param string $root
	 * @return \RecursiveIteratorIterator
	 * @throws \Exception
	 */
	private function getFolderIterator(string $folderToIterate): \RecursiveIteratorIterator {
		if (!is_dir($folderToIterate)) {
			throw new NotFoundException($folderToIterate);
		}
		$dirItr = new \RecursiveDirectoryIterator(
			$folderToIterate,
			\RecursiveDirectoryIterator::SKIP_DOTS
		);

		return new \RecursiveIteratorIterator(
			$dirItr,
			\RecursiveIteratorIterator::SELF_FIRST
		);
	}

	/**
	 * Returns an array of ['filename' => 'SHA512-hash-of-file'] for all files found
	 * in the iterator.
	 *
	 * @param \RecursiveIteratorIterator $iterator
	 * @param string $path
	 * @return array Array of hashes.
	 */
	private function generateHashes(\RecursiveIteratorIterator $iterator): array {
		$hashes = [];

		$baseDirectoryLength = \strlen($this->getInstallPath());
		foreach ($iterator as $filename => $data) {
			/** @var \DirectoryIterator $data */
			if ($data->isDir()) {
				continue;
			}

			$relativeFileName = substr((string)$filename, $baseDirectoryLength);
			$relativeFileName = ltrim($relativeFileName, '/');

			if ($this->isExcluded($relativeFileName)) {
				continue;
			}

			$hashes[$relativeFileName] = hash_file('sha512', $filename);
		}

		return $hashes;
	}

	private function isExcluded(string $filename): bool {
		foreach ($this->exclude as $prefix) {
			if (str_starts_with($filename, (string)$prefix)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Creates the signature data
	 *
	 * @param array $hashes
	 * @param X509 $certificate
	 * @param RSA $privateKey
	 * @return array
	 */
	private function createSignatureData(array $hashes): array {
		ksort($hashes);

		$this->getPrivateKey()->setSignatureMode(RSA::SIGNATURE_PSS);
		$this->getPrivateKey()->setMGFHash('sha512');
		// See https://tools.ietf.org/html/rfc3447#page-38
		$this->getPrivateKey()->setSaltLength(0);
		$signature = $this->getPrivateKey()->sign(json_encode($hashes));

		return [
			'hashes' => $hashes,
			'signature' => base64_encode($signature),
			'certificate' => $this->getCertificate()->saveX509($this->getCertificate()->currentCert),
		];
	}

	private function getRootCertificatePublicKey(): string {
		if ($this->willUseLocalCert) {
			$localCert = __DIR__ . '/../../../build/tools/certificates/local/root.crt';
			if (file_exists($localCert)) {
				return (string)file_get_contents($localCert);
			}
		}
		return $this->fileAccessHelper->file_get_contents($this->environmentHelper->getServerRoot() . '/resources/codesigning/root.crt');
	}

	public function getDevelopCert(): array {
		$privateKey = openssl_pkey_new([
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		]);

		$csrNames = ['commonName' => 'libresign'];

		$csr = openssl_csr_new($csrNames, $privateKey, ['digest_alg' => 'sha256']);
		$x509 = openssl_csr_sign($csr, null, $privateKey, $days = 365, [
			'digest_alg' => 'sha256',
			'x509_extensions' => [
				'authorityInfoAccess' => [
					[
						'accessMethod' => '1.3.6.1.5.5.7.1.1',
						'accessLocation' => [
							'uniformResourceIdentifier' => 'https://apps.nextcloud.com/apps/libresign',
						],
					],
				],
			]
		]);

		openssl_x509_export($x509, $rootCertificate);
		openssl_pkey_export($privateKey, $privateKeyCert);

		$this->rsa = new RSA();
		$this->rsa->loadKey($privateKeyCert);
		$this->x509 = new X509();
		$this->x509->loadX509($rootCertificate);
		$this->x509->setPrivateKey($this->rsa);

		$rootCertPath = __DIR__ . '/../../../build/tools/certificates/local/';
		if (!is_dir($rootCertPath)) {
			mkdir($rootCertPath, 0777, true);
		}
		file_put_contents($rootCertPath . '/root.crt', $rootCertificate);
		file_put_contents($rootCertPath . '/libresign.crt', $rootCertificate);
		file_put_contents($rootCertPath . '/libresign.key', $privateKeyCert);

		$privateKeyInstance = openssl_pkey_new([
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		]);
		return [
			'rootCertificate' => $rootCertificate,
			'privateKeyInstance' => $privateKeyInstance,
			'privateKeyCert' => $privateKeyCert,
		];
	}
}
