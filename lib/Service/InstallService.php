<?php

declare(strict_types=1);

namespace OCA\Libresign\Service;

use OC;
use OC\Archive\TAR;
use OC\Archive\ZIP;
use OC\Files\Filesystem;
use OC\Memcache\NullCache;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\CfsslHandler;
use OCA\Libresign\Handler\CertificateEngine\OpenSslHandler;
use OCA\Libresign\Handler\JSignPdfHandler;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Http\Client\IClientService;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class InstallService {
	public const JAVA_VERSION = 'openjdk version "17.0.5" 2022-10-18';
	private const JAVA_PARTIAL_VERSION = '17.0.5_8';
	/**
	 * When update, verify the hash of all architectures
	 */
	public const CFSSL_VERSION = '1.6.3';
	/** @var ICache */
	private $cache;
	/** @var OutputInterface */
	private $output;
	/** @var bool */
	/** @var string */
	private $resource = '';

	public function __construct(
		ICacheFactory $cacheFactory,
		private IClientService $clientService,
		private CfsslHandler $cfsslHandler,
		private OpenSslHandler $openSslHandler,
		private IConfig $config,
		private IRootFolder $rootFolder,
		private LoggerInterface $logger
	) {
		$this->cache = $cacheFactory->createDistributed('libresign-setup');
	}

	public function setOutput(OutputInterface $output): void {
		$this->output = $output;
	}

	private function getFolder(string $path = ''): Folder {
		$rootFolder = $this->getAppRootFolder();
		if ($rootFolder->nodeExists(Application::APP_ID . DIRECTORY_SEPARATOR . $path)) {
			$folder = $rootFolder->get(Application::APP_ID . DIRECTORY_SEPARATOR . $path);
		} else {
			$folder = $rootFolder->newFolder(Application::APP_ID . DIRECTORY_SEPARATOR . $path);
		}
		return $folder;
	}

	private function getAppDataFolderName(): string {
		$instanceId = $this->config->getSystemValue('instanceid', null);
		if ($instanceId === null) {
			throw new \RuntimeException('no instance id!');
		}

		return 'appdata_' . $instanceId;
	}

	private function getDataDir(): string {
		$dataDir = $this->config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data/');
		return $dataDir;
	}

	private function getAppRootFolder(): Folder {
		$path = $this->getAppDataFolderName();
		$mount = Filesystem::getMountManager()->find($path);
		$storage = $mount->getStorage();
		$internalPath = $mount->getInternalPath($path);
		if ($storage->file_exists($internalPath)) {
			$folder = $this->rootFolder->get($path);
		} else {
			$folder = $this->rootFolder->newFolder($path);
		}
		return $folder;
	}

	public function getFullPath(): string {
		$folder = $this->getFolder();
		return $this->getDataDir() . '/' . $folder->getInternalPath();
	}

	/**
	 * Return the config path, create if not exist.
	 */
	public function getConfigPath(): string {
		$engine = $this->config->getAppValue(Application::APP_ID, 'certificate_engine', 'cfssl');
		$this->getFolder($engine . '_config');
		return $this->getFullPath() . DIRECTORY_SEPARATOR . $engine . '_config' . DIRECTORY_SEPARATOR;
	}

	private function runAsync(): void {
		$resource = $this->resource;
		$process = new Process([OC::$SERVERROOT . '/occ', 'libresign:install', '--' . $resource]);
		$process->start();
		$data['pid'] = $process->getPid();
		if ($data['pid']) {
			$this->setCache($resource, $data);
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
				/** @var File */
				$file = $appFolder->get('setup-cache.json');
			} catch (\Throwable $th) {
				/** @var File */
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
				/** @var File */
				$file = $appFolder->get('setup-cache.json');
				$json = $file->getContent() ? json_decode($file->getContent(), true) : [];
				return $json[$key] ?? null;
			} catch (\Throwable $th) {
			}
			return;
		}
		return $this->cache->get(Application::APP_ID . '-asyncDownloadProgress-' . $key);
	}

	private function removeCache(string $key): void {
		if ($this->cache instanceof NullCache) {
			$appFolder = $this->getFolder();
			try {
				$file = $appFolder->get('setup-cache.json');
				$file->delete();
			} catch (\Throwable $th) {
			}
			return;
		}
		$this->cache->remove(Application::APP_ID . '-asyncDownloadProgress-' . $key);
	}

	public function getTotalSize(): array {
		$resources = [
			'java',
			'jsignpdf',
			'cfssl'
		];
		$return = [];
		foreach ($resources as $resource) {
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

	public function setResource(string $resource): self {
		$this->resource = $resource;
		return $this;
	}

	public function installJava(?bool $async = false): void {
		$this->setResource('java');
		if ($async) {
			$this->runAsync();
			return;
		}
		$extractDir = $this->getFullPath() . DIRECTORY_SEPARATOR . 'java';
		$appFolder = $this->getFolder();
		if ($appFolder->nodeExists('java')) {
			/** @var Folder */
			$javaFolder = $appFolder->get('java');
		} else {
			$javaFolder = $appFolder->newFolder('java');
		}

		/**
		 * Steps to update:
		 *     Check the compatible version of Java to use JSignPdf
		 *     Update all the follow data
		 *     Update the constants with java version
		 * URL used to get the MD5 and URL to download:
		 * https://jdk.java.net/java-se-ri/8-MR3
		 */
		if (PHP_OS_FAMILY === 'Linux') {
			$architecture = php_uname('m');
			if ($architecture === 'x86_64') {
				$compressedFileName = 'OpenJDK17U-jre_x64_linux_hotspot_' . self::JAVA_PARTIAL_VERSION . '.tar.gz';
				$url = 'https://github.com/adoptium/temurin17-binaries/releases/download/jdk-17.0.5%2B8/' . $compressedFileName;
			} elseif ($architecture === 'aarch64') {
				$compressedFileName = 'OpenJDK17U-jre_aarch64_linux_hotspot_' . self::JAVA_PARTIAL_VERSION . '.tar.gz';
				$url = 'https://github.com/adoptium/temurin17-binaries/releases/download/jdk-17.0.5%2B8/' . $compressedFileName;
			}
			$class = TAR::class;
		} else {
			throw new RuntimeException(sprintf('OS_FAMILY %s is incompatible with LibreSign.', PHP_OS_FAMILY));
		}
		$folder = $this->getFolder();
		$checksumUrl = $url . '.sha256.txt';
		$hash = $this->getHash($folder, 'java', $compressedFileName, self::JAVA_PARTIAL_VERSION, $checksumUrl);
		if (!$javaFolder->nodeExists($compressedFileName)) {
			$compressedFile = $javaFolder->newFile($compressedFileName);
		} else {
			$compressedFile = $javaFolder->get($compressedFileName);
		}
		$comporessedInternalFileName = $this->getDataDir() . DIRECTORY_SEPARATOR . $compressedFile->getInternalPath();

		$this->download($url, 'java', $comporessedInternalFileName, $hash, 'sha256');

		$this->config->deleteAppValue(Application::APP_ID, 'java_path');
		$extractor = new $class($comporessedInternalFileName);
		$extractor->extract($extractDir);

		$this->config->setAppValue(Application::APP_ID, 'java_path', $extractDir . '/jdk-17.0.5+8-jre/bin/java');
		$this->removeDownloadProgress();
	}

	public function uninstallJava(): void {
		$javaPath = $this->config->getAppValue(Application::APP_ID, 'java_path');
		if (!$javaPath) {
			return;
		}
		$appFolder = $this->getAppRootFolder();
		$name = $appFolder->getName();
		if (!strpos($javaPath, $name)) {
			return;
		}
		if (PHP_OS_FAMILY !== 'Windows') {
			exec('rm -rf ' . $this->getDataDir() . '/' . $this->getFolder()->getInternalPath() . '/java');
		}
		if ($appFolder->nodeExists('/libresign/java')) {
			$javaFolder = $appFolder->get('/libresign/java');
			$javaFolder->delete();
		}
		$this->config->deleteAppValue(Application::APP_ID, 'java_path');
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
		$extractDir = $this->getFullPath();

		$compressedFileName = 'jsignpdf-' . JSignPdfHandler::VERSION . '.zip';
		if (!$this->getFolder()->nodeExists($compressedFileName)) {
			$compressedFile = $this->getFolder()->newFile($compressedFileName);
		} else {
			$compressedFile = $this->getFolder()->get($compressedFileName);
		}
		$comporessedInternalFileName = $this->getDataDir() . DIRECTORY_SEPARATOR . $compressedFile->getInternalPath();
		$url = 'https://sourceforge.net/projects/jsignpdf/files/stable/JSignPdf%20' . JSignPdfHandler::VERSION . '/jsignpdf-' . JSignPdfHandler::VERSION . '.zip';
		/** WHEN UPDATE version: generate this hash handmade and update here */
		$hash = '327182016506f57109270d4875851784';

		$this->download($url, 'JSignPdf', $comporessedInternalFileName, $hash);

		$zip = new ZIP($extractDir . DIRECTORY_SEPARATOR . $compressedFileName);
		$zip->extract($extractDir);

		$fullPath = $extractDir . DIRECTORY_SEPARATOR . 'jsignpdf-' . JSignPdfHandler::VERSION . DIRECTORY_SEPARATOR . 'JSignPdf.jar';
		$this->config->setAppValue(Application::APP_ID, 'jsignpdf_jar_path', $fullPath);
		$this->removeDownloadProgress();
	}

	public function uninstallJSignPdf(): void {
		$jsignpdJarPath = $this->config->getAppValue(Application::APP_ID, 'jsignpdf_jar_path');
		if (!$jsignpdJarPath) {
			return;
		}
		$appFolder = $this->getAppRootFolder();
		$name = $appFolder->getName();
		// Remove prefix
		$path = explode($name, $jsignpdJarPath)[1];
		// Remove sufix
		$path = trim($path, DIRECTORY_SEPARATOR . 'JSignPdf.jar');
		try {
			$folder = $appFolder->get($path);
			$folder->delete();
		} catch (NotFoundException $e) {
		}
		$this->config->deleteAppValue(Application::APP_ID, 'jsignpdf_jar_path');
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
		$architecture = php_uname('m');
		if ($architecture === 'x86_64') {
			$this->installCfssl64();
		} elseif ($architecture === 'aarch64') {
			$this->installCfsslArm();
		}
		$this->removeDownloadProgress();
	}

	private function installCfssl64(): void {
		$folder = $this->getFolder();

		$downloads = [
			[
				'file' => 'cfssl_' . self::CFSSL_VERSION . '_linux_amd64',
				'destination' => 'cfssl',
			],
			[
				'file' => 'cfssljson_' . self::CFSSL_VERSION . '_linux_amd64',
				'destination' => 'cfssljson',
			],
		];
		$baseUrl = 'https://github.com/cloudflare/cfssl/releases/download/v' . self::CFSSL_VERSION . '/';
		$checksumUrl = 'https://github.com/cloudflare/cfssl/releases/download/v' . self::CFSSL_VERSION . '/cfssl_' . self::CFSSL_VERSION . '_checksums.txt';
		foreach ($downloads as $download) {
			$hash = $this->getHash($folder, 'cfssl', $download['file'], self::CFSSL_VERSION, $checksumUrl);

			$file = $folder->newFile($download['destination']);
			$fullPath = $this->getDataDir() . DIRECTORY_SEPARATOR . $file->getInternalPath();

			$this->download($baseUrl . $download['file'], $download['destination'], $fullPath, $hash, 'sha256');

			chmod($fullPath, 0700);
		}

		$cfsslBinPath = $this->getDataDir() . DIRECTORY_SEPARATOR .
			$this->getFolder()->getInternalPath() . DIRECTORY_SEPARATOR .
			$downloads[0]['destination'];
		$this->config->setAppValue(Application::APP_ID, 'cfssl_bin', $cfsslBinPath);
	}

	private function installCfsslArm(): void {
		$appFolder = $this->getFolder();
		if ($appFolder->nodeExists('cfssl')) {
			/** @var Folder */
			$cfsslFolder = $appFolder->get('cfssl');
		} else {
			$cfsslFolder = $appFolder->newFolder('cfssl');
		}
		$compressedFileName = 'cfssl-' . self::CFSSL_VERSION . '-1-aarch64.pkg.tar.xz';
		$url = 'http://mirror.archlinuxarm.org/aarch64/community/' . $compressedFileName;
		// Generated handmade with command sha256sum
		$hash = '944a6c54e53b0e2ef04c9b22477eb5f637715271c74ccea9bb91d7ac0473b855';
		if (!$cfsslFolder->nodeExists($compressedFileName)) {
			$compressedFile = $cfsslFolder->newFile($compressedFileName);
		} else {
			$compressedFile = $cfsslFolder->get($compressedFileName);
		}

		$comporessedInternalFileName = $this->getDataDir() . DIRECTORY_SEPARATOR . $compressedFile->getInternalPath();

		$this->download($url, 'cfssl', $comporessedInternalFileName, $hash, 'sha256');

		$this->config->deleteAppValue(Application::APP_ID, 'cfssl_bin');
		$extractor = new TAR($comporessedInternalFileName);

		$extractDir = $this->getFullPath() . DIRECTORY_SEPARATOR . 'cfssl';
		$result = $extractor->extract($extractDir);
		if (!$result) {
			throw new \RuntimeException('Error to extract xz file. Install xz. Read more: https://github.com/codemasher/php-ext-xz');
		}
		$cfsslBinPath = $this->getDataDir() . DIRECTORY_SEPARATOR .
			$this->getFolder()->getInternalPath() . DIRECTORY_SEPARATOR .
			'cfssl/usr/bin/cfssl';
		$this->config->setAppValue(Application::APP_ID, 'cfssl_bin', $cfsslBinPath);
	}

	public function uninstallCfssl(): void {
		$cfsslPath = $this->config->getAppValue(Application::APP_ID, 'cfssl_bin');
		if (!$cfsslPath) {
			return;
		}
		$appFolder = $this->getAppRootFolder();
		$name = $appFolder->getName();
		// Remove prefix
		$path = explode($name, $cfsslPath)[1];
		try {
			$folder = $appFolder->get($path);
			$folder->delete();
		} catch (NotFoundException $e) {
		}
		$this->config->deleteAppValue(Application::APP_ID, 'cfssl_bin');
	}

	public function isCfsslBinInstalled(): bool {
		if ($this->config->getAppValue(Application::APP_ID, 'cfssl_bin')) {
			return true;
		}
		return false;
	}

	protected function download(string $url, string $filename, string $path, ?string $hash = '', ?string $hash_algo = 'md5'): void {
		if (file_exists($path)) {
			$this->progressToDatabase((int) filesize($path), 0);
			if (hash_file($hash_algo, $path) === $hash) {
				return;
			}
		}
		if (php_sapi_name() === 'cli' && $this->output instanceof OutputInterface) {
			$this->downloadCli($url, $filename, $path, $hash, $hash_algo);
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
			throw new LibresignException('Failure on download ' . $filename . " try again.\n" . $e->getMessage());
		}
		if ($hash && file_exists($path) && hash_file($hash_algo, $path) !== $hash) {
			throw new LibresignException('Failure on download ' . $filename . ' try again. Invalid ' . $hash_algo . '.');
		}
	}

	protected function downloadCli(string $url, string $filename, string $path, ?string $hash = '', ?string $hash_algo = 'md5'): void {
		$client = $this->clientService->newClient();
		$progressBar = new ProgressBar($this->output);
		$this->output->writeln('Downloading ' . $filename . '...');
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
			$this->output->writeln('<error>Failure on download ' . $filename . ' try again.</error>');
			$this->output->writeln('<error>' . $e->getMessage() . '</error>');
			$this->logger->error('Failure on download ' . $filename . '. ' . $e->getMessage());
		}
		$progressBar->finish();
		$this->output->writeln('');
		$progressBar->finish();
		if ($hash && file_exists($path) && hash_file($hash_algo, $path) !== $hash) {
			$this->output->writeln('<error>Failure on download ' . $filename . ' try again</error>');
			$this->output->writeln('<error>Invalid ' . $hash_algo . '</error>');
			$this->logger->error('Failure on download ' . $filename . '. Invalid ' . $hash_algo . '.');
		}
		if (!file_exists($path)) {
			$this->output->writeln('<error>Failure on download ' . $filename . ', empty file, try again</error>');
			$this->logger->error('Failure on download ' . $filename . ', empty file.');
		}
	}

	private function getHash(Folder $folder, string $type, string $file, string $version, string $checksumUrl): string {
		$hashFileName = 'checksums_' . $type . '_' . $version . '.txt';
		if (!$folder->nodeExists($hashFileName)) {
			$hashes = file_get_contents($checksumUrl);
			if (!$hashes) {
				throw new LibresignException('Failute to download hash file. URL: ' . $checksumUrl);
			}
			$folder->newFile($hashFileName, $hashes);
		}
		/** @var \OCP\Files\File */
		$fileObject = $folder->get($hashFileName);
		$hashes = $fileObject->getContent();
		if (!$hashes) {
			throw new LibresignException(
				'Failute to load content of hash file: ' . $hashFileName . '. ' .
				'File corrupted or not found. Run "occ files:scan-app-data libresign".'
			);
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
		if (empty($properties['configPath'])) {
			$properties['configPath'] = $this->getConfigPath();
		}
		if (!is_dir($properties['configPath']) || !is_writable($properties['configPath'])) {
			throw new LibresignException(sprintf(
				'The path %s need to be a writtable directory',
				$properties['configPath']
			));
		}

		$engine = $this->config->getAppValue(Application::APP_ID, 'certificate_engine', 'cfssl');

		switch ($engine) {
			case 'cfssl':
				if (!empty($properties['cfsslUri'])) {
					$this->cfsslHandler->setCfsslUri($properties['cfsslUri']);
				}

				$privateKey = $this->cfsslHandler->generateRootCert(
					$commonName,
					$names,
					$properties['configPath'],
				);
				break;

			case 'openssl':
				$privateKey = $this->openSslHandler->generateRootCert(
					$commonName,
					$names,
					$properties['configPath'],
				);
				break;

			default:
				throw new LibresignException('Certificate engine not found: ' . $engine);
		}

		$this->config->setAppValue(Application::APP_ID, 'rootCert', json_encode([
			'commonName' => $commonName,
			'names' => $names
		]));
		$this->config->setAppValue(Application::APP_ID, 'authkey', $privateKey);
		$this->config->setAppValue(Application::APP_ID, 'configPath', $properties['configPath']);
		$this->config->setAppValue(Application::APP_ID, 'notifyUnsignedUser', 1);
	}
}
