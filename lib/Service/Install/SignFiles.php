<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Vitor Mattos <vitor@php.rio>
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

namespace OCA\Libresign\Service\Install;

use OC\IntegrityCheck\Helpers\EnvironmentHelper;
use OC\IntegrityCheck\Helpers\FileAccessHelper;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\InvalidSignatureException;
use OCP\App\IAppManager;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IConfig;
use phpseclib\Crypt\RSA;
use phpseclib\File\X509;

class SignFiles {
	private IAppData $appData;
	private array $exclude = [
		'openssl_config',
		'cfssl_config',
		'unauthetnicated',
	];
	private string $architecture;
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
		string $appInfoDir = '',
	) {
		$this->architecture = $architecture;
		$appInfoDir = $this->getAppInfoDirectory($appInfoDir);
		try {
			$this->fileAccessHelper->assertDirectoryExists($appInfoDir);

			$iterator = $this->getFolderIterator();
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

	private function getAppInfoDirectory(string $appInfoDir): string {
		if (is_dir($appInfoDir)) {
			return $appInfoDir;
		}
		return realpath(__DIR__ . '/../../../appinfo');
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

	public function verify(string $architecture, string $appInfoDir = '', string $certificateCN = Application::APP_ID): array {
		$this->architecture = $architecture;
		$appInfoDir = $this->getAppInfoDirectory($appInfoDir);

		$signaturePath = $appInfoDir . '/install-' . $this->architecture . '.json';
		$content = $this->fileAccessHelper->file_get_contents($signaturePath);
		$signatureData = null;

		if (\is_string($content)) {
			$signatureData = json_decode($content, true);
		}
		if (!\is_array($signatureData)) {
			throw new InvalidSignatureException('Signature data not found.');
		}

		$expectedHashes = $signatureData['hashes'];
		ksort($expectedHashes);
		$signature = base64_decode($signatureData['signature']);
		$certificate = $signatureData['certificate'];

		// Check if certificate is signed by Nextcloud Root Authority
		$x509 = new \phpseclib\File\X509();
		$rootCertificatePublicKey = $this->fileAccessHelper->file_get_contents($this->environmentHelper->getServerRoot().'/resources/codesigning/root.crt');

		$rootCerts = $this->splitCerts($rootCertificatePublicKey);
		foreach ($rootCerts as $rootCert) {
			$x509->loadCA($rootCert);
		}
		$x509->loadX509($certificate);
		if (!$x509->validateSignature()) {
			throw new InvalidSignatureException('Certificate is not valid.');
		}
		// Verify if certificate has proper CN. "core" CN is always trusted.
		if ($x509->getDN(X509::DN_OPENSSL)['CN'] !== $certificateCN && $x509->getDN(X509::DN_OPENSSL)['CN'] !== 'core') {
			throw new InvalidSignatureException(
				sprintf('Certificate is not valid for required scope. (Requested: %s, current: CN=%s)', $certificateCN, $x509->getDN(true)['CN'])
			);
		}

		// Check if the signature of the files is valid
		$rsa = new \phpseclib\Crypt\RSA();
		$rsa->loadKey($x509->currentCert['tbsCertificate']['subjectPublicKeyInfo']['subjectPublicKey']);
		$rsa->setSignatureMode(RSA::SIGNATURE_PSS);
		$rsa->setMGFHash('sha512');
		// See https://tools.ietf.org/html/rfc3447#page-38
		$rsa->setSaltLength(0);
		if (!$rsa->verify(json_encode($expectedHashes), $signature)) {
			throw new InvalidSignatureException('Signature could not get verified.');
		}

		// Compare the list of files which are not identical
		$installPath = $this->getInstallPath();
		$currentInstanceHashes = $this->generateHashes($this->getFolderIterator(), $installPath);
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
	private function getFolderIterator(): \RecursiveIteratorIterator {
		$dirItr = new \RecursiveDirectoryIterator(
			$this->getInstallPath(),
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
