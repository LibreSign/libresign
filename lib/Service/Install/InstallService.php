<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Install;

use InvalidArgumentException;
use OC;
use OC\Archive\TAR;
use OC\Archive\ZIP;
use OC\Memcache\NullCache;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Files\TSimpleFile;
use OCA\Libresign\Handler\CertificateEngine\AEngineHandler;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\CertificateEngine\CfsslHandler;
use OCA\Libresign\Handler\CertificateEngine\IEngineHandler;
use OCA\Libresign\Vendor\LibreSign\WhatOSAmI\OperatingSystem;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
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
	use TSimpleFile {
		getInternalPathOfFile as getInternalPathOfFileTrait;
		getInternalPathOfFolder as getInternalPathOfFolderTrait;
	}

	public const JAVA_VERSION = 'openjdk version "21.0.8" 2025-07-15 LTS';
	private const JAVA_URL_PATH_NAME = '21.0.8+9';
	public const PDFTK_VERSION = '3.3.3'; /** @todo When update, verify the hash **/
	private const PDFTK_HASH = '59a28bed53b428595d165d52988bf4cf';
	public const JSIGNPDF_VERSION = '2.3.0'; /** @todo When update, verify the hash **/
	private const JSIGNPDF_HASH = 'd239658ea50a39eb35169d8392feaffb';
	public const CFSSL_VERSION = '1.6.5';

	private ICache $cache;
	private ?OutputInterface $output = null;
	private string $resource = '';
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
		private CertificateEngineFactory $certificateEngineFactory,
		private IConfig $config,
		private IAppConfig $appConfig,
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
				$path = $this->architecture . '/' . $this->getLinuxDistributionToDownloadJava() . '/java';
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
		} catch (\Throwable) {
			try {
				$folder = $folder->newFolder($path);
			} catch (NotPermittedException $e) {
				$user = posix_getpwuid(posix_getuid());
				throw new LibresignException(
					$e->getMessage() . '. '
					. 'Permission problems. '
					. 'Maybe this could fix: chown -R ' . $user['name'] . ' ' . $this->getInternalPathOfFolder($folder)
				);
			}
		}
		return $folder;
	}

	private function getInternalPathOfFolder(ISimpleFolder $node): string {
		return $this->getDataDir() . '/' . $this->getInternalPathOfFolderTrait($node);
	}

	private function getInternalPathOfFile(ISimpleFile $node): string {
		return $this->getDataDir() . '/' . $this->getInternalPathOfFileTrait($node);
	}

	private function getDataDir(): string {
		$dataDir = $this->config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data/');
		return $dataDir;
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
			} catch (\Throwable) {
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
			} catch (NotFoundException) {
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
			} catch (\Throwable) {
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
			$pid = $progressData['pid'] ?? 0;
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
		$cmd .= 'grep "libresign:install --' . $this->resource . '"|'
			. 'grep -v grep|'
			. 'grep -v defunct|'
			. 'sed -e "s/^[[:space:]]*//"|cut -d" " -f1';
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

		if ($this->isDownloadedFilesOk()) {
			// The binaries files could exists but not saved at database
			$javaPath = $this->appConfig->getValueString(Application::APP_ID, 'java_path');
			if (!$javaPath) {
				$linuxDistribution = $this->getLinuxDistributionToDownloadJava();
				$folder = $this->getFolder('/' . $linuxDistribution . '/' . $this->resource);
				$extractDir = $this->getInternalPathOfFolder($folder);
				$javaPath = $extractDir . '/jdk-' . self::JAVA_URL_PATH_NAME . '-jre/bin/java';
				$this->appConfig->setValueString(Application::APP_ID, 'java_path', $javaPath);
			}
			if (str_contains($javaPath, self::JAVA_URL_PATH_NAME)) {
				return;
			}
		}
		/**
		 * Steps to update:
		 *     Check the compatible version of Java to use JSignPdf
		 *     Update all the follow data
		 *     Update the constants with java version
		 * URL used to get the MD5 and URL to download:
		 * https://jdk.java.net/java-se-ri/8-MR3
		 */
		$linuxDistribution = $this->getLinuxDistributionToDownloadJava();
		$slugfyVersionNumber = str_replace('+', '_', self::JAVA_URL_PATH_NAME);
		if ($this->architecture === 'x86_64') {
			$compressedFileName = 'OpenJDK21U-jre_x64_' . $linuxDistribution . '_hotspot_' . $slugfyVersionNumber . '.tar.gz';
			$url = 'https://github.com/adoptium/temurin21-binaries/releases/download/jdk-' . self::JAVA_URL_PATH_NAME . '/' . $compressedFileName;
		} elseif ($this->architecture === 'aarch64') {
			$compressedFileName = 'OpenJDK21U-jre_aarch64_' . $linuxDistribution . '_hotspot_' . $slugfyVersionNumber . '.tar.gz';
			$url = 'https://github.com/adoptium/temurin21-binaries/releases/download/jdk-' . self::JAVA_URL_PATH_NAME . '/' . $compressedFileName;
		}
		$folder = $this->getFolder('/' . $linuxDistribution . '/' . $this->resource);
		try {
			$compressedFile = $folder->getFile($compressedFileName);
		} catch (NotFoundException) {
			$compressedFile = $folder->newFile($compressedFileName);
		}

		$compressedInternalFileName = $this->getInternalPathOfFile($compressedFile);
		$dependencyName = 'java ' . $this->architecture . ' ' . $linuxDistribution;
		$checksumUrl = $url . '.sha256.txt';
		$hash = $this->getHash($compressedFileName, $checksumUrl);
		$this->download($url, $dependencyName, $compressedInternalFileName, $hash, 'sha256');

		$extractor = new TAR($compressedInternalFileName);
		$extractDir = $this->getInternalPathOfFolder($folder);
		$extractor->extract($extractDir);
		unlink($compressedInternalFileName);
		$this->appConfig->setValueString(Application::APP_ID, 'java_path', $extractDir . '/jdk-' . self::JAVA_URL_PATH_NAME . '-jre/bin/java');
		$this->writeAppSignature();
		$this->removeDownloadProgress();
	}

	public function setDistro(string $distro): void {
		$this->distro = $distro;
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
		} catch (NotFoundException) {
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

		if ($this->isDownloadedFilesOk()) {
			// The binaries files could exists but not saved at database
			$fullPath = $this->appConfig->getValueString(Application::APP_ID, 'jsignpdf_jar_path');
			if (!$fullPath) {
				$folder = $this->getFolder($this->resource);
				$extractDir = $this->getInternalPathOfFolder($folder);
				$fullPath = $extractDir . '/jsignpdf-' . InstallService::JSIGNPDF_VERSION . '/JSignPdf.jar';
				$this->appConfig->setValueString(Application::APP_ID, 'jsignpdf_jar_path', $fullPath);
			}
			$this->saveJsignPdfHome();
			if (str_contains($fullPath, InstallService::JSIGNPDF_VERSION)) {
				return;
			}
		}
		$folder = $this->getFolder($this->resource);
		$compressedFileName = 'jsignpdf-' . InstallService::JSIGNPDF_VERSION . '.zip';
		try {
			$compressedFile = $folder->getFile($compressedFileName);
		} catch (\Throwable) {
			$compressedFile = $folder->newFile($compressedFileName);
		}
		$compressedInternalFileName = $this->getInternalPathOfFile($compressedFile);
		$url = 'https://github.com/intoolswetrust/jsignpdf/releases/download/JSignPdf_' . str_replace('.', '_', InstallService::JSIGNPDF_VERSION) . '/jsignpdf-' . InstallService::JSIGNPDF_VERSION . '.zip';

		$this->download($url, 'JSignPdf', $compressedInternalFileName, self::JSIGNPDF_HASH);

		$extractDir = $this->getInternalPathOfFolder($folder);
		$zip = new ZIP($extractDir . '/' . $compressedFileName);
		$zip->extract($extractDir);
		unlink($extractDir . '/' . $compressedFileName);
		$fullPath = $extractDir . '/jsignpdf-' . InstallService::JSIGNPDF_VERSION . '/JSignPdf.jar';
		$this->appConfig->setValueString(Application::APP_ID, 'jsignpdf_jar_path', $fullPath);
		$this->saveJsignPdfHome();
		$this->writeAppSignature();

		$this->removeDownloadProgress();
	}

	/**
	 * It's a workaround to create the folder structure that JSignPdf needs. Without
	 * this, the JSignPdf will return the follow message to all commands:
	 * > FINE Config file conf/conf.properties doesn't exists.
	 * > FINE Default property file /root/.JSignPdf doesn't exists.
	 */
	private function saveJsignPdfHome(): void {
		$home = $this->appConfig->getValueString(Application::APP_ID, 'jsignpdf_home');
		if ($home && preg_match('/libresign\/jsignpdf_home/', $home)) {
			return;
		}
		$libresignFolder = $this->appData->getFolder('/');
		$homeFolder = $libresignFolder->newFolder('jsignpdf_home');
		$homeFolder->newFile('.JSignPdf', '');
		$configFolder = $this->getFolder('conf', $homeFolder);
		$configFolder->newFile('conf.properties', '');
		$this->appConfig->setValueString(Application::APP_ID, 'jsignpdf_home', $this->getInternalPathOfFolder($homeFolder));
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
		} catch (NotFoundException) {
		}
		$this->appConfig->deleteKey(Application::APP_ID, 'jsignpdf_jar_path');
		$this->appConfig->deleteKey(Application::APP_ID, 'jsignpdf_home');
	}

	public function installPdftk(?bool $async = false): void {
		$this->setResource('pdftk');
		if ($async) {
			$this->runAsync();
			return;
		}

		if ($this->isDownloadedFilesOk()) {
			// The binaries files could exists but not saved at database
			if (!$this->appConfig->getValueString(Application::APP_ID, 'pdftk_path')) {
				$folder = $this->getFolder($this->resource);
				$file = $folder->getFile('pdftk.jar');
				$fullPath = $this->getInternalPathOfFile($file);
				$this->appConfig->setValueString(Application::APP_ID, 'pdftk_path', $fullPath);
			}
			return;
		}
		$folder = $this->getFolder($this->resource);
		try {
			$file = $folder->getFile('pdftk.jar');
		} catch (\Throwable) {
			$file = $folder->newFile('pdftk.jar');
		}
		$fullPath = $this->getInternalPathOfFile($file);
		$url = 'https://gitlab.com/api/v4/projects/5024297/packages/generic/pdftk-java/v' . self::PDFTK_VERSION . '/pdftk-all.jar';

		$this->download($url, 'pdftk', $fullPath, self::PDFTK_HASH);
		$this->appConfig->setValueString(Application::APP_ID, 'pdftk_path', $fullPath);
		$this->writeAppSignature();
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
		} catch (NotFoundException) {
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
		if ($this->isDownloadedFilesOk()) {
			// The binaries files could exists but not saved at database
			if (!$this->isCfsslBinInstalled()) {
				$folder = $this->getFolder($this->resource);
				$cfsslBinPath = $this->getInternalPathOfFolder($folder) . '/cfssl';
				$this->appConfig->setValueString(Application::APP_ID, 'cfssl_bin', $cfsslBinPath);
			}
			return;
		}
		$folder = $this->getFolder($this->resource);
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
			$fullPath = $this->getInternalPathOfFile($file);

			$dependencyName = $download['destination'] . ' ' . $architecture;
			$this->download($baseUrl . $download['file'], $dependencyName, $fullPath, $hash, 'sha256');

			chmod($fullPath, 0700);
		}
		$cfsslBinPath = $this->getInternalPathOfFolder($folder) . '/cfssl';
		$this->appConfig->setValueString(Application::APP_ID, 'cfssl_bin', $cfsslBinPath);
		$this->writeAppSignature();
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
		} catch (NotFoundException) {
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
				'progress' => function ($downloadSize, $downloaded): void {
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
				'progress' => function ($downloadSize, $downloaded) use ($progressBar): void {
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
		$engine = $this->certificateEngineFactory->getEngine($properties['engine'] ?? '', $rootCert);
		if ($engine instanceof CfsslHandler) {
			/** @var CfsslHandler $engine */
			$engine->setCfsslUri($properties['cfsslUri']);
		}

		$engine->setConfigPath($properties['configPath'] ?? '');

		/** @var IEngineHandler $engine */
		$privateKey = $engine->generateRootCert(
			$commonName,
			$names
		);

		$this->appConfig->setValueArray(Application::APP_ID, 'rootCert', $rootCert);
		$this->appConfig->setValueString(Application::APP_ID, 'authkey', $privateKey);
		/** @var AEngineHandler $engine */
		if ($engine instanceof CfsslHandler) {
			$this->appConfig->setValueString(Application::APP_ID, 'config_path', $engine->getConfigPath());
			$this->appConfig->setValueString(Application::APP_ID, 'certificate_engine', 'cfssl');
		} else {
			$this->appConfig->setValueString(Application::APP_ID, 'certificate_engine', 'openssl');
		}
	}
}
