<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Install;

use OC\IntegrityCheck\Helpers\FileAccessHelper;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\EmptySignatureDataException;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Exception\SignatureDataNotFoundException;
use OCA\Libresign\Handler\CertificateEngine\CertificateHelper;
use OCA\Libresign\Vendor\phpseclib4\Crypt\PublicKeyLoader;
use OCA\Libresign\Vendor\phpseclib4\Crypt\RSA;
use OCA\Libresign\Vendor\phpseclib4\Crypt\RSA\PrivateKey;
use OCA\Libresign\Vendor\phpseclib4\File\X509;
use OCP\App\IAppManager;
use OCP\Files\NotFoundException;
use OCP\ITempManager;

class SignSetupService {
	private array $exclude = [
		'openssl_config',
		'cfssl_config',
		'unauthetnicated',
	];
	private InstallTarget $target;
	private string $resource;
	private array $signatureData = [];
	private ?X509 $signingCertificate = null;
	private ?PrivateKey $privateKey = null;
	public function __construct(
		private FileAccessHelper $fileAccessHelper,
		private SetupSignatureVerifier $setupSignatureVerifier,
		private SetupInstallPathResolver $installPathResolver,
		private IAppManager $appManager,
		protected ITempManager $tempManager,
	) {
		$this->target = InstallTarget::current();
	}

	public function setArchitecture(string $architecture): self {
		$this->target = $this->target->withArchitecture($architecture);
		return $this;
	}

	public function setResource(string $resource): self {
		$this->resource = $resource;
		return $this;
	}

	/**
	 * @return array<int|string, string>
	 */
	public function getArchitectures(): array {
		$appInfo = $this->appManager->getAppInfo(Application::APP_ID);
		if (!is_array($appInfo) || !isset($appInfo['dependencies'])) {
			throw new \Exception('dependencies>architecture not found at info.xml');
		}
		$architectures = $appInfo['dependencies']['architecture'] ?? null;
		if ($architectures === null || $architectures === '' || $architectures === []) {
			throw new \Exception('dependencies>architecture not found at info.xml');
		}

		if (is_array($architectures)) {
			/** @var list<string> $normalized */
			$normalized = array_values(array_map(static fn (mixed $value): string => (string)$value, $architectures));
			return $normalized;
		}

		return [(string)$architectures];
	}

	public function setPrivateKey(PrivateKey $privateKey): void {
		$this->privateKey = $privateKey;
	}

	public function setCertificate(X509 $x509): void {
		$this->signingCertificate = $x509;
	}

	private function getPrivateKey(): PrivateKey {
		if (!$this->privateKey instanceof PrivateKey) {
			if (file_exists(__DIR__ . '/../../../build/tools/certificates/local/libresign.key')) {
				$privateKey = file_get_contents(__DIR__ . '/../../../build/tools/certificates/local/libresign.key');
				$this->privateKey = PublicKeyLoader::loadPrivateKey($privateKey);
			} else {
				$this->getDevelopCert();
			}
		}
		if (!$this->privateKey instanceof PrivateKey) {
			throw new LibresignException('Private key not found');
		}
		return $this->privateKey;
	}

	private function getCertificate(): X509 {
		if (!$this->signingCertificate instanceof x509) {
			if (file_exists(__DIR__ . '/../../../build/tools/certificates/local/libresign.crt')) {
				$x509 = file_get_contents(__DIR__ . '/../../../build/tools/certificates/local/libresign.crt');
				$this->signingCertificate = X509::load($x509);
			} else {
				$this->getDevelopCert();
			}
		}
		if (!$this->signingCertificate instanceof x509) {
			throw new LibresignException('Certificate not found');
		}
		return $this->signingCertificate;
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
				$this->target->architecture(),
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
		return $this->installPathResolver->resolve($this->target, $this->resource);
	}

	private function getFileName(): string {
		$appInfoDir = $this->getAppInfoDirectory();
		return $appInfoDir . '/' . $this->getSignatureFileName();
	}

	public function getSignatureFileName(): string {
		$path[] = 'install-' . $this->target->architecture();
		if ($this->resource === 'java') {
			$path[] = $this->getLinuxDistributionToDownloadJava();
		}
		$path[] = $this->resource . '.json';
		return implode('-', $path);
	}

	public function setDistro(string $distro): self {
		$this->target = $this->target->withDistro($distro);
		return $this;
	}

	public function getLinuxDistributionToDownloadJava(): string {
		return $this->target->distro();
	}

	protected function getAppInfoDirectory(): string {
		$appInfoDir = (string)realpath(__DIR__ . '/../../../appinfo');
		$this->fileAccessHelper->assertDirectoryExists($appInfoDir);
		return $appInfoDir;
	}

	private function getSignatureData(SetupTrustMode $trustMode): array {
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
		$this->setupSignatureVerifier->verify($signatureData, $trustMode);

		return $this->signatureData;
	}

	private function getHashesOfResource(SetupTrustMode $trustMode): array {
		$signatureData = $this->getSignatureData($trustMode);
		if (count($signatureData['hashes']) === 0) {
			throw new EmptySignatureDataException('No signature files to ' . $this->resource);
		}
		return $signatureData;
	}

	public function verify(
		string $architecture,
		string $resource,
		SetupTrustMode $trustMode = SetupTrustMode::Production,
	): array {
		$this->signatureData = [];
		$this->target = $this->target->withArchitecture($architecture);
		$this->resource = $resource;

		try {
			$expectedHashes = $this->getHashesOfResource($trustMode);
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
		} catch (\Exception $e) {
			return [
				'HASH_FILE_ERROR' => $e->getMessage(),
			];
		}

		$differencesA = array_diff_assoc($expectedHashes['hashes'], $currentInstanceHashes);
		$differencesB = array_diff_assoc($currentInstanceHashes, $expectedHashes['hashes']);
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

		$privateKey = $this->getPrivateKey()
			->withPadding(RSA::SIGNATURE_PSS);
		$signature = $privateKey->sign(json_encode($hashes));

		return [
			'hashes' => $hashes,
			'signature' => base64_encode((string)$signature),
			'certificate' => $this->getCertificate()->toString(),
		];
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
			'config' => $this->arrayToConfigFile([
				'v3_user' => [
					'keyUsage' => 'digitalSignature',
					'extendedKeyUsage' => 'clientAuth',
					'authorityInfoAccess' => '@aia_section',
				],
				'aia_section' => [
					'caIssuers;URI.0' => 'https://apps.nextcloud.com/apps/libresign',
				],
			]),
		]);

		openssl_x509_export($x509, $rootCertificate);
		openssl_pkey_export($privateKey, $privateKeyCert);

		$this->privateKey = RSA::loadPrivateKey($privateKeyCert);
		$this->signingCertificate = X509::load($rootCertificate);

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

	private function arrayToConfigFile(array $config): string {
		$temporaryFile = $this->tempManager->getTemporaryFile('.cfg');
		if (!$temporaryFile) {
			throw new LibresignException('Failure to create temporary file to OpenSSL .cfg file');
		}
		$ini = CertificateHelper::arrayToIni($config);
		file_put_contents($temporaryFile, $ini);
		return $temporaryFile;
	}
}
