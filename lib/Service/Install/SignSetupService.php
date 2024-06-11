<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Install;

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
use OCP\IConfig;
use phpseclib\Crypt\RSA;
use phpseclib\File\X509;

class SignSetupService {
	private IAppData $appData;
	private array $exclude = [
		'openssl_config',
		'cfssl_config',
		'unauthetnicated',
	];
	private string $architecture;
	private string $resource;
	private array $signatureData = [];
	private ?x509 $x509 = null;
	public function __construct(
		private EnvironmentHelper $environmentHelper,
		private FileAccessHelper $fileAccessHelper,
		private IConfig $config,
		private IAppDataFactory $appDataFactory,
		private IAppManager $appManager,
	) {
		$this->appData = $appDataFactory->get('libresign');
	}

	public function getArchitectures(): array {
		$appInfo = $this->appManager->getAppInfo(Application::APP_ID);
		if (empty($appInfo['dependencies']['architecture'])) {
			throw new \Exception('dependencies>architecture not found at info.xml');
		}
		return $appInfo['dependencies']['architecture'];
	}

	/**
	 * Write the signature of the app in the specified folder
	 *
	 * @param string $path
	 * @param X509 $certificate
	 * @param RSA $privateKey
	 * @throws \Exception
	 */
	public function writeAppSignature(
		X509 $certificate,
		RSA $privateKey,
		string $architecture,
	) {
		$this->architecture = $architecture;
		$appInfoDir = $this->getAppInfoDirectory();
		try {
			$iterator = $this->getFolderIterator($this->getInstallPath());
			$hashes = $this->generateHashes($iterator);
			$signature = $this->createSignatureData($hashes, $certificate, $privateKey);
			$this->fileAccessHelper->file_put_contents(
				$appInfoDir . '/install-' . $this->architecture . '.json',
				json_encode($signature, JSON_PRETTY_PRINT)
			);
		} catch (NotFoundException $e) {
			throw new \Exception(sprintf("Folder %s not found.\nIs necessary to run this command first: occ libresign:install --all --architecture %s", $e->getMessage(), $this->architecture));
		} catch (\Exception $e) {
			if (!$this->fileAccessHelper->is_writable($appInfoDir)) {
				throw new \Exception($appInfoDir . ' is not writable');
			}
			throw $e;
		}
	}

	protected function getAppInfoDirectory(): string {
		$appInfoDir = realpath(__DIR__ . '/../../../appinfo');
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
		$appInfoDir = $this->getAppInfoDirectory();
		$signaturePath = $appInfoDir . '/install-' . $this->architecture . '.json';
		$content = $this->fileAccessHelper->file_get_contents($signaturePath);
		$signatureData = null;

		if (\is_string($content)) {
			$signatureData = json_decode($content, true);
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
		$expectedHashes = $signatureData['hashes'];
		$filtered = array_filter($expectedHashes, function (string $key) {
			return str_starts_with($key, $this->resource);
		}, ARRAY_FILTER_USE_KEY);
		if (!$filtered) {
			throw new EmptySignatureDataException('No signature files to ' . $this->resource);
		}
		return $filtered;
	}

	private function getLibresignAppCertificate(): X509 {
		if ($this->x509 instanceof X509) {
			return $this->x509;
		}
		$signatureData = $this->getSignatureData();
		$certificate = $signatureData['certificate'];

		// Check if certificate is signed by Nextcloud Root Authority
		$this->x509 = new X509();
		$rootCertificatePublicKey = $this->fileAccessHelper->file_get_contents($this->environmentHelper->getServerRoot().'/resources/codesigning/root.crt');

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
		$signature = base64_decode($signatureData['signature']);
		if (!$rsa->verify(json_encode($expectedHashes), $signature)) {
			throw new InvalidSignatureException('Signature could not get verified.');
		}
	}

	public function verify(string $architecture, $resource): array {
		$this->architecture = $architecture;
		$this->resource = $resource;

		try {
			$expectedHashes = $this->getHashesOfResource();
			// Compare the list of files which are not identical
			$installPath = $this->getInstallPath() . '/' . $this->resource;
			$currentInstanceHashes = $this->generateHashes($this->getFolderIterator($installPath), $installPath);
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

		$differencesA = array_diff($expectedHashes, $currentInstanceHashes);
		$differencesB = array_diff($currentInstanceHashes, $expectedHashes);
		$differences = array_merge($differencesA, $differencesB);
		$differenceArray = [];
		foreach ($differences as $filename => $hash) {
			// Check if file should not exist in the new signature table
			if (!array_key_exists($filename, $expectedHashes)) {
				$differenceArray['EXTRA_FILE'][$filename]['expected'] = '';
				$differenceArray['EXTRA_FILE'][$filename]['current'] = $hash;
				continue;
			}

			// Check if file is missing
			if (!array_key_exists($filename, $currentInstanceHashes)) {
				$differenceArray['FILE_MISSING'][$filename]['expected'] = $expectedHashes[$filename];
				$differenceArray['FILE_MISSING'][$filename]['current'] = '';
				continue;
			}

			// Check if hash does mismatch
			if ($expectedHashes[$filename] !== $currentInstanceHashes[$filename]) {
				$differenceArray['INVALID_HASH'][$filename]['expected'] = $expectedHashes[$filename];
				$differenceArray['INVALID_HASH'][$filename]['current'] = $currentInstanceHashes[$filename];
				continue;
			}

			// Should never happen.
			throw new \Exception('Invalid behaviour in file hash comparison experienced. Please report this error to the developers.');
		}

		return $differenceArray;
	}

	private function getDataDir(): string {
		$dataDir = $this->config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data/');
		return $dataDir;
	}

	/**
	 * @todo check a best solution to don't use reflection
	 */
	protected function getInternalPathOfFolder(ISimpleFolder $node): string {
		$reflection = new \ReflectionClass($node);
		$reflectionProperty = $reflection->getProperty('folder');
		$reflectionProperty->setAccessible(true);
		$folder = $reflectionProperty->getValue($node);
		$path = $folder->getInternalPath();
		return $path;
	}

	private function getInstallPath(): string {
		$folder = $this->getDataDir() . '/' .
			$this->getInternalPathOfFolder($this->appData->getFolder($this->architecture));
		return $folder;
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
			throw new NotFoundException('No such directory ' . $folderToIterate);
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

			$relativeFileName = substr($filename, $baseDirectoryLength);
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
			if (str_starts_with($filename, $prefix)) {
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
	private function createSignatureData(array $hashes,
		X509 $certificate,
		RSA $privateKey): array {
		ksort($hashes);

		$privateKey->setSignatureMode(RSA::SIGNATURE_PSS);
		$privateKey->setMGFHash('sha512');
		// See https://tools.ietf.org/html/rfc3447#page-38
		$privateKey->setSaltLength(0);
		$signature = $privateKey->sign(json_encode($hashes));

		return [
			'hashes' => $hashes,
			'signature' => base64_encode($signature),
			'certificate' => $certificate->saveX509($certificate->currentCert),
		];
	}
}
