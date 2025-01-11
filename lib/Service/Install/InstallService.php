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

namespace OCA\Libresign\Service\Install;

use InvalidArgumentException;
use LibreSign\WhatOSAmI\OperatingSystem;
use OC;
use OC\Archive\TAR;
use OC\Archive\ZIP;
use OC\Memcache\NullCache;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\AEngineHandler;
use OCA\Libresign\Handler\CertificateEngine\CfsslHandler;
use OCA\Libresign\Handler\CertificateEngine\Handler as CertificateEngineHandler;
use OCA\Libresign\Handler\CertificateEngine\IEngineHandler;
use OCA\Libresign\Handler\JSignPdfHandler;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class InstallService {
	public const JAVA_VERSION = 'openjdk version "21.0.2" 2024-01-16 LTS';
	private const JAVA_PARTIAL_VERSION = '21.0.2_13';
	private const JAVA_URL_PATH_NAME = '21.0.2+13';
	public const PDFTK_VERSION = '3.3.3';
	/**
	 * When update, verify the hash of all architectures
	 */
	public const CFSSL_VERSION = '1.6.4';
	/** @var ICache */
	private $cache;
	/** @var OutputInterface */
	private $output;
	/** @var string */
	private $resource = '';
	protected IAppData $appData;
	private array $availableResources = [
		'java',
		'jsignpdf',
		'pdftk',
		'cfssl',
	];
	private string $distro = '';
	private string $architecture;
	private bool $willUseLocalCert = false;

	public function __construct(
		ICacheFactory $cacheFactory,
		private IClientService $clientService,
		private CertificateEngineHandler $certificateEngineHandler,
		private IConfig $config,
		private IAppConfig $appConfig,
		private IRootFolder $rootFolder,
		private LoggerInterface $logger,
		private SignSetupService $signSetupService,
		protected IAppDataFactory $appDataFactory,
	) {
		$this->cache = $cacheFactory->createDistributed('libresign-setup');
		$this->appData = $appDataFactory->get('libresign');
		$this->setArchitecture(php_uname('m'));
	}

	public function setOutput(OutputInterface $output): void {
		$this->output = $output;
	}

	public function setArchitecture(string $architecture): self {
		$this->architecture = $architecture;
		return $this;
	}

	private function getFolder(string $path = '', ?ISimpleFolder $folder = null, $needToBeEmpty = false): ISimpleFolder {
		if (!$folder) {
			$folder = $this->appData->getFolder('/');
			if (!$path) {
				$path = $this->architecture;
			} elseif ($path === 'java') {
				$path = $this->architecture . '/' . $this->getLinuxDistributionToDownloadJava() . '/' . $path;
			} else {
				$path = $this->architecture . '/' . $path;
			}
			$path = explode('/', $path);
			foreach ($path as $snippet) {
				$folder = $this->getFolder($snippet, $folder, $needToBeEmpty);
			}
			return $folder;
		}
		try {
			$folder = $folder->getFolder($path, $folder);
			if ($needToBeEmpty && $path !== $this->architecture) {
				$folder->delete();
				$path = '';
				throw new \Exception('Need to be empty');
			}
		} catch (\Throwable $th) {
			try {
				$folder = $folder->newFolder($path);
			} catch (NotPermittedException $e) {
				$user = posix_getpwuid(posix_getuid());
				throw new LibresignException(
					$e->getMessage() . '. ' .
					'Permission problems. ' .
					'Maybe this could fix: chown -R ' . $user['name'] . ' ' . $this->getDataDir()
				);
			}
		}
		return $folder;
	}

	private function getEmptyFolder(string $path): ISimpleFolder {
		return $this->getFolder($path, null, true);
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

	/**
	 * @todo check a best solution to don't use reflection
	 */
	private function getInternalPathOfFile(ISimpleFile $node): string {
		$reflection = new \ReflectionClass($node);
		if ($reflection->hasProperty('parentFolder')) {
			$reflectionProperty = $reflection->getProperty('parentFolder');
			$reflectionProperty->setAccessible(true);
			$folder = $reflectionProperty->getValue($node);
			$path = $folder->getInternalPath() . '/' . $node->getName();
		} elseif ($reflection->hasProperty('file')) {
			$reflectionProperty = $reflection->getProperty('file');
			$reflectionProperty->setAccessible(true);
			$file = $reflectionProperty->getValue($node);
			$path = $file->getPath();
		}
		return $path;
	}

	private function getDataDir(): string {
		$dataDir = $this->config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data/');
		return $dataDir;
	}

	private function getFullPath(): string {
		$folder = $this->getFolder();
		return $this->getDataDir() . '/' . $this->getInternalPathOfFolder($folder);
	}

	private function runAsync(): void {
		$resource = $this->resource;
		$process = new Process([OC::$SERVERROOT . '/occ', 'libresign:install', '--' . $resource]);
		$process->setOptions(['create_new_console' => true]);
		$process->setTimeout(null);
		$process->start();
		$data['pid'] = $process->getPid();
		if ($data['pid']) {
			$this->setCache($resource, $data);
		} else {
			$this->logger->error('Error to get PID of background install proccess. Command: ' . OC::$SERVERROOT . '/occ libresign:install --' . $resource);
		}
	}

	private function progressToDatabase(int $downloadSize, int $downloaded): void {
		$data = $this->getProressData();
		$data['download_size'] = $downloadSize;
		$data['downloaded'] = $downloaded;
		$this->setCache($this->resource, $data);
	}

	public function getProressData(): array {
		$data = $this->getCache($this->resource) ?? [];
		return $data;
	}

	private function removeDownloadProgress(): void {
		$this->removeCache($this->resource);
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 */
	private function setCache(string $key, $value): void {
		if ($this->cache instanceof NullCache) {
			$appFolder = $this->getFolder();
			try {
				$file = $appFolder->getFile('setup-cache.json');
			} catch (\Throwable $th) {
				$file = $appFolder->newFile('setup-cache.json', '[]');
			}
			$json = $file->getContent() ? json_decode($file->getContent(), true) : [];
			$json[$key] = $value;
			$file->putContent(json_encode($json));
			return;
		}
		$this->cache->set(Application::APP_ID . '-asyncDownloadProgress-' . $key, $value);
	}

	/**
	 * @return mixed
	 */
	private function getCache(string $key) {
		if ($this->cache instanceof NullCache) {
			$appFolder = $this->getFolder();
			try {
				$file = $appFolder->getFile('setup-cache.json');
				$json = $file->getContent() ? json_decode($file->getContent(), true) : [];
				return $json[$key] ?? null;
			} catch (NotFoundException $th) {
			} catch (\Throwable $th) {
				$this->logger->error('Unexpected error when get setup-cache.json file', [
					'app' => Application::APP_ID,
					'exception' => $th,
				]);
			}
			return;
		}
		return $this->cache->get(Application::APP_ID . '-asyncDownloadProgress-' . $key);
	}

	private function removeCache(string $key): void {
		if ($this->cache instanceof NullCache) {
			$appFolder = $this->getFolder();
			try {
				$file = $appFolder->getFile('setup-cache.json');
				$json = $file->getContent() ? json_decode($file->getContent(), true) : [];
				if (isset($json[$key])) {
					unset($json[$key]);
				}
				if (!$json) {
					$file->delete();
				} else {
					$file->putContent(json_encode($json));
				}
			} catch (\Throwable $th) {
			}
			return;
		}
		$this->cache->remove(Application::APP_ID . '-asyncDownloadProgress-' . $key);
	}

	public function getAvailableResources(): array {
		return $this->availableResources;
	}

	public function getTotalSize(): array {
		$return = [];
		foreach ($this->availableResources as $resource) {
			$this->setResource($resource);
			$progressData = $this->getProressData();
			if (array_key_exists('download_size', $progressData)) {
				if ($progressData['download_size']) {
					$return[$resource] = $progressData['downloaded'] * 100 / $progressData['download_size'];
				} else {
					$return[$resource] = 0;
				}
			}
		}
		return $return;
	}

	public function saveErrorMessage(string $message): void {
		$data = $this->getProressData();
		$data['error'] = $message;
		$this->setCache($this->resource, $data);
	}

	public function getErrorMessages(): array {
		$return = [];
		foreach ($this->availableResources as $resource) {
			$this->setResource($resource);
			$progressData = $this->getProressData();
			if (array_key_exists('error', $progressData)) {
				$return[] = $progressData['error'];
				$this->removeDownloadProgress();
			}
		}
		return $return;
	}

	public function isDownloadWip(): bool {
		foreach ($this->availableResources as $resource) {
			$this->setResource($resource);
			$progressData = $this->getProressData();
			if (empty($progressData)) {
				return false;
			}
			$pid = isset($progressData['pid']) ? $progressData['pid'] : 0;
			if ($this->getInstallPid($pid) === 0) {
				if (!array_key_exists('error', $progressData)) {
					$this->removeDownloadProgress();
				}
				continue;
			}
			return true;
		}
		return false;
	}

	private function getInstallPid(int $pid = 0): int {
		if ($pid > 0) {
			if (shell_exec('which ps') === null) {
				if (is_dir('/proc/' . $pid)) {
					return $pid;
				}
				return 0;
			}
			$cmd = 'ps -p ' . $pid . ' -o pid,command|';
		} else {
			$cmd = 'ps -eo pid,command|';
		}
		$cmd .= 'grep "libresign:install --' . $this->resource . '"|' .
			'grep -v grep|' .
			'grep -v defunct|' .
			'sed -e "s/^[[:space:]]*//"|cut -d" " -f1';
		$output = shell_exec($cmd);
		if (!is_string($output)) {
			return 0;
		}
		$pid = trim($output);
		return (int)$pid;
	}

	public function setResource(string $resource): self {
		$this->resource = $resource;
		return $this;
	}

	public function isDownloadedFilesOk(): bool {
		$this->signSetupService->willUseLocalCert($this->willUseLocalCert);
		$this->signSetupService->setDistro($this->getLinuxDistributionToDownloadJava());
		return count($this->signSetupService->verify($this->architecture, $this->resource)) === 0;
	}

	public function willUseLocalCert(): void {
		$this->willUseLocalCert = true;
	}

	private function writeAppSignature(): void {
		if (!$this->willUseLocalCert) {
			return;
		}

		$this->signSetupService
			->setDistro($this->getLinuxDistributionToDownloadJava())
			->setArchitecture($this->architecture)
			->setResource($this->resource)
			->writeAppSignature();
	}

	public function installJava(?bool $async = false): void {
		$this->setResource('java');
		if ($async) {
			$this->runAsync();
			return;
		}
		if (PHP_OS_FAMILY !== 'Linux') {
			throw new RuntimeException(sprintf('OS_FAMILY %s is incompatible with LibreSign.', PHP_OS_FAMILY));
		}
		$linuxDistribution = $this->getLinuxDistributionToDownloadJava();
		$extractDir = $this->getFullPath() . '/' . $linuxDistribution . '/' . $this->resource;

		$downloadOk = $this->isDownloadedFilesOk();
		if (!$downloadOk) {
			$folder = $this->getEmptyFolder($this->resource);
			/**
			 * Steps to update:
			 *     Check the compatible version of Java to use JSignPdf
			 *     Update all the follow data
			 *     Update the constants with java version
			 * URL used to get the MD5 and URL to download:
			 * https://jdk.java.net/java-se-ri/8-MR3
			 */
			if ($this->architecture === 'x86_64') {
				$compressedFileName = 'OpenJDK21U-jre_x64_' . $linuxDistribution . '_hotspot_' . self::JAVA_PARTIAL_VERSION . '.tar.gz';
				$url = 'https://github.com/adoptium/temurin21-binaries/releases/download/jdk-' . self::JAVA_URL_PATH_NAME . '/' . $compressedFileName;
			} elseif ($this->architecture === 'aarch64') {
				$compressedFileName = 'OpenJDK21U-jre_aarch64_' . $linuxDistribution . '_hotspot_' . self::JAVA_PARTIAL_VERSION . '.tar.gz';
				$url = 'https://github.com/adoptium/temurin21-binaries/releases/download/jdk-' . self::JAVA_URL_PATH_NAME . '/' . $compressedFileName;
			}
			$checksumUrl = $url . '.sha256.txt';
			$hash = $this->getHash($compressedFileName, $checksumUrl);
			try {
				$compressedFile = $folder->getFile($compressedFileName);
			} catch (NotFoundException $th) {
				$compressedFile = $folder->newFile($compressedFileName);
			}
			$comporessedInternalFileName = $this->getDataDir() . '/' . $this->getInternalPathOfFile($compressedFile);

			$dependencyName = 'java ' . $this->architecture . ' ' . $linuxDistribution;
			$this->download($url, $dependencyName, $comporessedInternalFileName, $hash, 'sha256');

			$extractor = new TAR($comporessedInternalFileName);
			$extractor->extract($extractDir);
			unlink($comporessedInternalFileName);
			$downloadOk = true;
		}

		$this->appConfig->setValueString(Application::APP_ID, 'java_path', $extractDir . '/jdk-' . self::JAVA_URL_PATH_NAME . '-jre/bin/java');
		if ($downloadOk) {
			$this->writeAppSignature();
		}
		$this->removeDownloadProgress();
	}

	public function setDistro(string $distro): void {
		$this->distro = $distro;
	}

	public function getInstallPath(): string {
		switch ($this->resource) {
			case 'java':
				$path = $this->appConfig->getValueString(Application::APP_ID, 'java_path');
				return substr($path, 0, -strlen('/bin/java'));
			case 'jsignpdf':
				$path = $this->appConfig->getValueString(Application::APP_ID, 'jsignpdf_jar_path');
				return substr($path, 0, -strlen('/JSignPdf.jar'));
			case 'pdftk':
				$path = $this->appConfig->getValueString(Application::APP_ID, 'pdftk_path');
				return substr($path, 0, -strlen('/pdftk.jar'));
			case 'cfssl':
				$path = $this->appConfig->getValueString(Application::APP_ID, 'cfssl_bin');
				return substr($path, 0, -strlen('/cfssl'));
		}
		return '';
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
			$this->setDistro('alpine-linux');
		} else {
			$this->setDistro('linux');
		}
		return $this->distro;
	}

	public function uninstallJava(): void {
		$javaPath = $this->appConfig->getValueString(Application::APP_ID, 'java_path');
		if (!$javaPath) {
			return;
		}
		$this->setResource('java');
		$folder = $this->getFolder($this->resource);
		try {
			$folder->delete();
		} catch (NotFoundException $th) {
		}
		$this->appConfig->deleteKey(Application::APP_ID, 'java_path');
	}

	public function installJSignPdf(?bool $async = false): void {
		if (!extension_loaded('zip')) {
			throw new RuntimeException('Zip extension is not available');
		}
		$this->setResource('jsignpdf');
		if ($async) {
			$this->runAsync();
			return;
		}
		$extractDir = $this->getFullPath() . '/' . $this->resource;

		$downloadOk = $this->isDownloadedFilesOk();
		if (!$downloadOk) {
			$folder = $this->getEmptyFolder($this->resource);
			$compressedFileName = 'jsignpdf-' . JSignPdfHandler::VERSION . '.zip';
			try {
				$compressedFile = $folder->getFile($compressedFileName);
			} catch (\Throwable $th) {
				$compressedFile = $folder->newFile($compressedFileName);
			}
			$comporessedInternalFileName = $this->getDataDir() . '/' . $this->getInternalPathOfFile($compressedFile);
			$url = 'https://github.com/intoolswetrust/jsignpdf/releases/download/JSignPdf_' . str_replace('.', '_', JSignPdfHandler::VERSION) . '/jsignpdf-' . JSignPdfHandler::VERSION . '.zip';
			/** WHEN UPDATE version: generate this hash handmade and update here */
			$hash = '7c66f5a9f5e7e35b601725414491a867';

			$this->download($url, 'JSignPdf', $comporessedInternalFileName, $hash);

			$zip = new ZIP($extractDir . '/' . $compressedFileName);
			$zip->extract($extractDir);
			unlink($extractDir . '/' . $compressedFileName);
			$downloadOk = true;
		}

		$fullPath = $extractDir . '/jsignpdf-' . JSignPdfHandler::VERSION . '/JSignPdf.jar';
		$this->appConfig->setValueString(Application::APP_ID, 'jsignpdf_jar_path', $fullPath);
		if ($downloadOk) {
			$this->writeAppSignature();
		}
		$this->removeDownloadProgress();
	}

	public function uninstallJSignPdf(): void {
		$jsignpdJarPath = $this->appConfig->getValueString(Application::APP_ID, 'jsignpdf_jar_path');
		if (!$jsignpdJarPath) {
			return;
		}
		$this->setResource('jsignpdf');
		$folder = $this->getFolder($this->resource);
		try {
			$folder->delete();
		} catch (NotFoundException $e) {
		}
		$this->appConfig->deleteKey(Application::APP_ID, 'jsignpdf_jar_path');
	}

	public function installPdftk(?bool $async = false): void {
		$this->setResource('pdftk');
		if ($async) {
			$this->runAsync();
			return;
		}

		$downloadOk = $this->isDownloadedFilesOk();
		if ($downloadOk) {
			$folder = $this->getFolder($this->resource);
			$file = $folder->getFile('pdftk.jar');
			$fullPath = $this->getDataDir() . '/' . $this->getInternalPathOfFile($file);
		} else {
			$folder = $this->getEmptyFolder($this->resource);
			try {
				$file = $folder->getFile('pdftk.jar');
			} catch (\Throwable $th) {
				$file = $folder->newFile('pdftk.jar');
			}
			$fullPath = $this->getDataDir() . '/' . $this->getInternalPathOfFile($file);
			$url = 'https://gitlab.com/api/v4/projects/5024297/packages/generic/pdftk-java/v' . self::PDFTK_VERSION . '/pdftk-all.jar';
			/** WHEN UPDATE version: generate this hash handmade and update here */
			$hash = '59a28bed53b428595d165d52988bf4cf';

			$this->download($url, 'pdftk', $fullPath, $hash);
			$downloadOk = true;
		}

		$this->appConfig->setValueString(Application::APP_ID, 'pdftk_path', $fullPath);
		if ($downloadOk) {
			$this->writeAppSignature();
		}
		$this->removeDownloadProgress();
	}

	public function uninstallPdftk(): void {
		$jsignpdJarPath = $this->appConfig->getValueString(Application::APP_ID, 'pdftk_path');
		if (!$jsignpdJarPath) {
			return;
		}
		$this->setResource('pdftk');
		$folder = $this->getFolder($this->resource);
		try {
			$folder->delete();
		} catch (NotFoundException $e) {
		}
		$this->appConfig->deleteKey(Application::APP_ID, 'pdftk_path');
	}

	public function installCfssl(?bool $async = false): void {
		$this->setResource('cfssl');
		if ($async) {
			$this->runAsync();
			return;
		}
		if (PHP_OS_FAMILY !== 'Linux') {
			throw new RuntimeException(sprintf('OS_FAMILY %s is incompatible with LibreSign.', PHP_OS_FAMILY));
		}
		if ($this->architecture === 'x86_64') {
			$this->installCfsslByArchitecture('amd64');
		} elseif ($this->architecture === 'aarch64') {
			$this->installCfsslByArchitecture('arm64');
		} else {
			throw new InvalidArgumentException('Invalid architecture to download cfssl');
		}
		$this->removeDownloadProgress();
	}

	private function installCfsslByArchitecture(string $architecture): void {
		$downloadOk = $this->isDownloadedFilesOk();
		if ($downloadOk) {
			$folder = $this->getFolder($this->resource);
		} else {
			$folder = $this->getEmptyFolder($this->resource);
			$downloads = [
				[
					'file' => 'cfssl_' . self::CFSSL_VERSION . '_linux_' . $architecture,
					'destination' => 'cfssl',
				],
				[
					'file' => 'cfssljson_' . self::CFSSL_VERSION . '_linux_' . $architecture,
					'destination' => 'cfssljson',
				],
			];
			$baseUrl = 'https://github.com/cloudflare/cfssl/releases/download/v' . self::CFSSL_VERSION . '/';
			$checksumUrl = 'https://github.com/cloudflare/cfssl/releases/download/v' . self::CFSSL_VERSION . '/cfssl_' . self::CFSSL_VERSION . '_checksums.txt';
			foreach ($downloads as $download) {
				$hash = $this->getHash($download['file'], $checksumUrl);

				$file = $folder->newFile($download['destination']);
				$fullPath = $this->getDataDir() . '/' . $this->getInternalPathOfFile($file);

				$dependencyName = $download['destination'] . ' ' . $architecture;
				$this->download($baseUrl . $download['file'], $dependencyName, $fullPath, $hash, 'sha256');

				chmod($fullPath, 0700);
			}
			$downloadOk = true;
		}

		$cfsslBinPath = $this->getDataDir() . '/' .
			$this->getInternalPathOfFolder($folder) . '/cfssl';
		$this->appConfig->setValueString(Application::APP_ID, 'cfssl_bin', $cfsslBinPath);
		if ($downloadOk) {
			$this->writeAppSignature();
		}
	}

	public function uninstallCfssl(): void {
		$cfsslPath = $this->appConfig->getValueString(Application::APP_ID, 'cfssl_bin');
		if (!$cfsslPath) {
			return;
		}
		$this->setResource('cfssl');
		$folder = $this->getFolder($this->resource);
		try {
			$folder->delete();
		} catch (NotFoundException $e) {
		}
		$this->appConfig->deleteKey(Application::APP_ID, 'cfssl_bin');
	}

	public function isCfsslBinInstalled(): bool {
		if ($this->appConfig->getValueString(Application::APP_ID, 'cfssl_bin')) {
			return true;
		}
		return false;
	}

	protected function download(string $url, string $dependencyName, string $path, ?string $hash = '', ?string $hash_algo = 'md5'): void {
		if (file_exists($path)) {
			$this->progressToDatabase((int)filesize($path), 0);
			if (hash_file($hash_algo, $path) === $hash) {
				return;
			}
		}
		if (php_sapi_name() === 'cli' && $this->output instanceof OutputInterface) {
			$this->downloadCli($url, $dependencyName, $path, $hash, $hash_algo);
			return;
		}
		$client = $this->clientService->newClient();
		try {
			$client->get($url, [
				'sink' => $path,
				'timeout' => 0,
				'progress' => function ($downloadSize, $downloaded) {
					$this->progressToDatabase($downloadSize, $downloaded);
				},
			]);
		} catch (\Exception $e) {
			throw new LibresignException('Failure on download ' . $dependencyName . " try again.\n" . $e->getMessage());
		}
		if ($hash && file_exists($path) && hash_file($hash_algo, $path) !== $hash) {
			throw new LibresignException('Failure on download ' . $dependencyName . ' try again. Invalid ' . $hash_algo . '.');
		}
	}

	protected function downloadCli(string $url, string $dependencyName, string $path, ?string $hash = '', ?string $hash_algo = 'md5'): void {
		$client = $this->clientService->newClient();
		$progressBar = new ProgressBar($this->output);
		$this->output->writeln('Downloading ' . $dependencyName . '...');
		$progressBar->start();
		try {
			$client->get($url, [
				'sink' => $path,
				'timeout' => 0,
				'progress' => function ($downloadSize, $downloaded) use ($progressBar) {
					$progressBar->setMaxSteps($downloadSize);
					$progressBar->setProgress($downloaded);
					$this->progressToDatabase($downloadSize, $downloaded);
				},
			]);
		} catch (\Exception $e) {
			$progressBar->finish();
			$this->output->writeln('');
			$this->output->writeln('<error>Failure on download ' . $dependencyName . ' try again.</error>');
			$this->output->writeln('<error>' . $e->getMessage() . '</error>');
			$this->logger->error('Failure on download ' . $dependencyName . '. ' . $e->getMessage());
		} finally {
			$progressBar->finish();
			$this->output->writeln('');
		}
		if ($hash && file_exists($path) && hash_file($hash_algo, $path) !== $hash) {
			$this->output->writeln('<error>Failure on download ' . $dependencyName . ' try again</error>');
			$this->output->writeln('<error>Invalid ' . $hash_algo . '</error>');
			$this->logger->error('Failure on download ' . $dependencyName . '. Invalid ' . $hash_algo . '.');
		}
		if (!file_exists($path)) {
			$this->output->writeln('<error>Failure on download ' . $dependencyName . ', empty file, try again</error>');
			$this->logger->error('Failure on download ' . $dependencyName . ', empty file.');
		}
	}

	private function getHash(string $file, string $checksumUrl): string {
		$hashes = file_get_contents($checksumUrl);
		if (!$hashes) {
			throw new LibresignException('Failute to download hash file. URL: ' . $checksumUrl);
		}
		preg_match('/(?<hash>\w*) +' . $file . '/', $hashes, $matches);
		return $matches['hash'];
	}

	/**
	 * @todo Use an custom array for engine options
	 */
	public function generate(
		string $commonName,
		array $names = [],
		array $properties = [],
	): void {
		$rootCert = [
			'commonName' => $commonName,
			'names' => $names
		];
		$engine = $this->certificateEngineHandler->getEngine($properties['engine'] ?? '', $rootCert);
		if ($engine->getEngine() === 'cfssl') {
			/** @var CfsslHandler $engine */
			$engine->setCfsslUri($properties['cfsslUri']);
		}

		$engine->setConfigPath($properties['configPath'] ?? '');

		/** @var IEngineHandler $engine */
		$privateKey = $engine->generateRootCert(
			$commonName,
			$names
		);

		$this->appConfig->setValueArray(Application::APP_ID, 'root_cert', $rootCert);
		$this->appConfig->setValueString(Application::APP_ID, 'authkey', $privateKey);
		/** @var AEngineHandler $engine */
		if ($engine->getEngine() === 'cfssl') {
			$this->appConfig->setValueString(Application::APP_ID, 'config_path', $engine->getConfigPath());
		}
		$this->appConfig->setValueBool(Application::APP_ID, 'notify_unsigned_user', true);
	}
}
