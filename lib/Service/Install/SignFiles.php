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

use OC\IntegrityCheck\Helpers\FileAccessHelper;
use OCA\Libresign\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
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
	public function __construct(
		private FileAccessHelper $fileAccessHelper,
		private IConfig $config,
		private IAppDataFactory $appDataFactory,
		private IAppManager $appManager,
	) {
		$this->appData = $appDataFactory->get('libresign');
	}

	public function getArchitectures(): array {
		$appInfo = $this->appManager->getAppInfo(Application::APP_ID);
		if (!isset($appInfo['dependencies']['architecture'])) {
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
		$appInfoDir = __DIR__ . '/../../../appinfo';
		try {
			$this->fileAccessHelper->assertDirectoryExists($appInfoDir);

			$iterator = $this->getFolderIterator();
			$hashes = $this->generateHashes($iterator);
			$signature = $this->createSignatureData($hashes, $certificate, $privateKey);
			$this->fileAccessHelper->file_put_contents(
				$appInfoDir . '/install-' . $architecture . '.json',
				json_encode($signature, JSON_PRETTY_PRINT)
			);
		} catch (\Exception $e) {
			if (!$this->fileAccessHelper->is_writable($appInfoDir)) {
				throw new \Exception($appInfoDir . ' is not writable');
			}
			throw $e;
		}
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

	private function getInstallPath(): string {
		$folder = $this->getDataDir() . '/' .
			$this->getInternalPathOfFolder($this->appData->getFolder('/'));
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
