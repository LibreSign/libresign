<?php

declare(strict_types=1);

namespace OCA\Libresign\Service;

use OC\Archive\TAR;
use OC\Archive\ZIP;
use OC\SystemConfig;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CfsslHandler;
use OCA\Libresign\Handler\CfsslServerHandler;
use OCA\Libresign\Handler\JSignPdfHandler;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Http\Client\IClientService;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\ITempManager;
use RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class InstallService {
	/** @var ICache */
	private $cache;
	/** @var IConfig */
	public $config;
	/** @var ITempManager */
	private $tempManager;
	/** @var IClientService */
	private $clientService;
	/** @var SystemConfig */
	private $systemConfig;
	/** @var IRootFolder */
	private $rootFolder;
	/** @var CfsslServerHandler */
	private $cfsslServerHandler;
	/** @var CfsslHandler */
	private $cfsslHandler;
	/** @var OutputInterface */
	private $output;
	/** @var bool */
	/** @var string */
	private $resource = '';

	public function __construct(
		ICacheFactory $cacheFactory,
		ITempManager $tempManager,
		IClientService $clientService,
		CfsslServerHandler $cfsslServerHandler,
		CfsslHandler $cfsslHandler,
		IConfig $config,
		SystemConfig $systemConfig,
		IRootFolder $rootFolder
	) {
		$this->cache = $cacheFactory->createDistributed('libresign-setup');
		$this->tempManager = $tempManager;
		$this->clientService = $clientService;
		$this->cfsslServerHandler = $cfsslServerHandler;
		$this->cfsslHandler = $cfsslHandler;
		$this->config = $config;
		$this->systemConfig = $systemConfig;
		$this->rootFolder = $rootFolder;
	}

	public function setOutput(OutputInterface $output): void {
		$this->output = $output;
	}

	public function getFolder($path = ''): Folder {
		$rootFolder = $this->getAppRootFolder();
		if ($rootFolder->nodeExists(Application::APP_ID . DIRECTORY_SEPARATOR . $path)) {
			$folder = $rootFolder->get(Application::APP_ID . DIRECTORY_SEPARATOR . $path);
		} else {
			$folder = $rootFolder->newFolder(Application::APP_ID . DIRECTORY_SEPARATOR . $path);
		}
		return $folder;
	}

	private function getAppDataFolderName(): string {
		$instanceId = $this->systemConfig->getValue('instanceid', null);
		if ($instanceId === null) {
			throw new \RuntimeException('no instance id!');
		}

		return 'appdata_' . $instanceId;
	}

	private function getDataDir(): string {
		$dataDir = $this->systemConfig->getValue('datadirectory', \OC::$SERVERROOT . '/data/');
		return $dataDir;
	}

	private function getAppRootFolder(): Folder {
		$path = $this->getAppDataFolderName();
		try {
			$folder = $this->rootFolder->get($path);
		} catch (\Throwable $th) {
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
	 *
	 * @return string Full config path
	 */
	public function getConfigPath(): string {
		$this->getFolder('cfssl_config');
		return $this->getFullPath() . DIRECTORY_SEPARATOR . 'cfssl_config' . DIRECTORY_SEPARATOR;
	}

	private function runAsync(): void {
		$resource = $this->resource;
		$process = new Process(['./occ', 'libresign:install', '--' . $resource]);
		$process->start();
		$data['pid'] = $process->getPid();
		if ($data['pid']) {
			$this->cache->set(Application::APP_ID . '-asyncDownloadProgress-' . $resource, $data);
		}
	}

	private function progressToDatabase($downloadSize, $downloaded) {
		$data = $this->getProressData();
		$data['download_size'] = $downloadSize;
		$data['downloaded'] = $downloaded;
		$this->cache->set(Application::APP_ID . '-asyncDownloadProgress-' . $this->resource, $data);
	}

	public function getProressData(): array {
		$data = $this->cache->get(Application::APP_ID . '-asyncDownloadProgress-' . $this->resource) ?? [];
		return $data;
	}

	private function removeDownloadCache() {
		$this->cache->remove(Application::APP_ID . '-asyncDownloadProgress-' . $this->resource);
	}

	public function getTotalSize(): array {
		$resources = [
			'java',
			'jsignpdf',
			'cli',
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
		 * To update:
		 * Check the compatible version of Java to use JSignPdf and update all the follow data
		 * URL used to get the MD5 and URL to download:
		 * https://jdk.java.net/java-se-ri/8-MR3
		 */
		if (PHP_OS_FAMILY === 'Windows') {
			$compressedFileName = 'openjdk-8u41-b04-windows-i586-14_jan_2020.zip';
			$url = 'https://download.java.net/openjdk/jdk8u41/ri/' . $compressedFileName;
			$executableExtension = '.exe';
			$class = ZIP::class;
			$hash = '48ac2152d1fb0ad1d343104be210d532';
		} else {
			$compressedFileName = 'openjdk-8u41-b04-linux-x64-14_jan_2020.tar.gz';
			$url = 'https://download.java.net/openjdk/jdk8u41/ri/' . $compressedFileName;
			$executableExtension = '';
			$class = TAR::class;
			$hash = '35f515e9436f4fefad091db2c1450c5f';
		}
		if (!$javaFolder->nodeExists($compressedFileName)) {
			$compressedFile = $javaFolder->newFile($compressedFileName);
		} else {
			$compressedFile = $javaFolder->get($compressedFileName);
		}
		$comporessedInternalFileName = $this->getDataDir() . DIRECTORY_SEPARATOR . $compressedFile->getInternalPath();

		$this->download($url, 'java', $comporessedInternalFileName, $hash);

		/**
		 * @todo Extrange behaviour, the directory won't full deleted,
		 * go-Horse to force delete in not Windows environment
		 */
		if (PHP_OS_FAMILY !== 'Windows') {
			exec('rm -rf ' . $this->getDataDir() . DIRECTORY_SEPARATOR . $javaFolder->getInternalPath() . DIRECTORY_SEPARATOR . 'java-se-8u41-ri');
		} elseif ($javaFolder->nodeExists('java-se-8u41-ri')) {
			$javaFolder->get('java-se-8u41-ri')->delete();
		}
		$extractor = new $class($comporessedInternalFileName);
		$extractor->extract($extractDir);

		$this->config->setAppValue(Application::APP_ID, 'java_path', $extractDir . '/java-se-8u41-ri/bin/java' . $executableExtension);
		$this->removeDownloadCache();
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
		try {
			$folder = $appFolder->get('/libresign/java');
			$folder->delete();
		} catch (NotFoundException $e) {
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
		$hash = 'be5a966be3a4a303f09a42c28b9b9a22';

		$this->download($url, 'JSignPdf', $comporessedInternalFileName, $hash);

		$zip = new ZIP($extractDir . DIRECTORY_SEPARATOR . $compressedFileName);
		$zip->extract($extractDir);

		$fullPath = $extractDir . DIRECTORY_SEPARATOR. 'jsignpdf-' . JSignPdfHandler::VERSION . DIRECTORY_SEPARATOR. 'JSignPdf.jar';
		$this->config->setAppValue(Application::APP_ID, 'jsignpdf_jar_path', $fullPath);
		$this->removeDownloadCache();
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

	public function installCli(?bool $async = false): void {
		if (PHP_OS_FAMILY === 'Windows') {
			throw new \RuntimeException('LibreSign CLI do not work in Windows!');
		}
		$this->setResource('cli');
		if ($async) {
			$this->runAsync();
			return;
		}
		$folder = $this->getFolder();
		$version = '0.0.4';
		$file = null;
		if (PHP_OS_FAMILY === 'Darwin') {
			$file = 'libresign_' . $version . '_Linux_arm64';
		} elseif (PHP_OS_FAMILY === 'Linux') {
			if (PHP_INT_SIZE === 4) {
				$file = 'libresign_' . $version . '_Linux_i386';
			} else {
				$file = 'libresign_' . $version . '_Linux_x86_64';
			}
		}

		$checksumUrl = 'https://github.com/LibreSign/libresign-cli/releases/download/v' . $version . '/checksums.txt';
		$hash = $this->getHash($folder, 'libresign-cli', $file, $version, $checksumUrl);

		$url = 'https://github.com/LibreSign/libresign-cli/releases/download/v' . $version . '/' . $file;
		$file = $folder->newFile('libresign-cli');
		$fullPath = $this->getDataDir() . DIRECTORY_SEPARATOR . $file->getInternalPath();

		$this->download($url, 'libresign-cli', $fullPath, $hash, 'sha256');

		if (PHP_OS_FAMILY !== 'Windows') {
			chmod($fullPath, 0700);
		}

		$this->config->setAppValue(Application::APP_ID, 'libresign_cli_path', $fullPath);
		$this->removeDownloadCache();
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
		$hashes = $folder->get($hashFileName)->getContent();
		preg_match('/(?<hash>\w*) +' . $file . '/', $hashes, $matches);
		return $matches['hash'];
	}

	public function uninstallCli(): void {
		$libresignCliPath = $this->config->getAppValue(Application::APP_ID, 'libresign_cli_path');
		if (!$libresignCliPath) {
			return;
		}
		$appFolder = $this->getAppRootFolder();
		$name = $appFolder->getName();
		// Remove prefix
		$path = explode($name, $libresignCliPath)[1];
		try {
			$folder = $appFolder->get($path);
			$folder->delete();
		} catch (NotFoundException $e) {
		}
		$this->config->deleteAppValue(Application::APP_ID, 'libresign_cli_path');
	}

	public function installCfssl(?bool $async = false): void {
		$this->setResource('cfssl');
		if ($async) {
			$this->runAsync();
			return;
		}
		$folder = $this->getFolder();
		$version = '1.6.1';

		if (PHP_OS_FAMILY === 'Windows') {
			$downloads = [
				[
					'file' => 'cfssl_' . $version . '_windows_amd64.exe',
					'destination' => 'cfssl.exe',
				],
				[
					'file' => 'cfssljson_' . $version . '_windows_amd64.exe',
					'destination' => 'cfssljson.exe',
				],
			];
		} elseif (PHP_OS_FAMILY === 'Darwin') {
			$downloads = [
				[
					'file' => 'cfssl_' . $version . '_darwin_amd64',
					'destination' => 'cfssl',
				],
				[
					'file' => 'cfssljson_' . $version . '_darwin_amd64',
					'destination' => 'cfssljson',
				],
			];
		} else {
			$downloads = [
				[
					'file' => 'cfssl_' . $version . '_linux_amd64',
					'destination' => 'cfssl',
				],
				[
					'file' => 'cfssljson_' . $version . '_linux_amd64',
					'destination' => 'cfssljson',
				],
			];
		}
		$baseUrl = 'https://github.com/cloudflare/cfssl/releases/download/v' . $version . '/';
		$checksumUrl = 'https://github.com/cloudflare/cfssl/releases/download/v' . $version . '/cfssl_' . $version . '_checksums.txt';
		foreach ($downloads as $download) {
			$hash = $this->getHash($folder, 'libresign-cli', $download['file'], $version, $checksumUrl);

			$file = $folder->newFile($download['destination']);
			$fullPath = $this->getDataDir() . DIRECTORY_SEPARATOR . $file->getInternalPath();

			$this->download($baseUrl . $download['file'], $download['destination'], $fullPath, $hash, 'sha256');

			if (PHP_OS_FAMILY !== 'Windows') {
				chmod($fullPath, 0700);
			}
		}

		$cfsslBinPath = $this->getDataDir() . DIRECTORY_SEPARATOR .
			$this->getFolder()->getInternalPath() . DIRECTORY_SEPARATOR .
			$downloads[0]['destination'];
		$this->config->setAppValue(Application::APP_ID, 'cfssl_bin', $cfsslBinPath);
		$this->removeDownloadCache();
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
			$this->progressToDatabase(filesize($path), 0);
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
		}
		$progressBar->finish();
		$this->output->writeln('');
		$progressBar->finish();
		if ($hash && file_exists($path) && hash_file($hash_algo, $path) !== $hash) {
			$this->output->writeln('<error>Failure on download ' . $filename . ' try again</error>');
			$this->output->writeln('<error>Invalid ' . $hash_algo . '</error>');
		}
	}

	public function generate(
		string $commonName,
		string $country,
		string $organization,
		string $organizationUnit,
		string $configPath = '',
		string $cfsslUri = '',
		string $binary = ''
	): void {
		$key = bin2hex(random_bytes(16));

		if (!$configPath) {
			$configPath = $this->getConfigPath();
		}
		$this->cfsslHandler->setConfigPath($configPath);
		$this->cfsslServerHandler->createConfigServer(
			$commonName,
			$country,
			$organization,
			$organizationUnit,
			$key,
			$configPath
		);
		$this->cfsslHandler->setCommonName($commonName);
		$this->cfsslHandler->setCountry($country);
		$this->cfsslHandler->setOrganization($organization);
		$this->cfsslHandler->setOrganizationUnit($organizationUnit);
		if ($cfsslUri) {
			$this->cfsslHandler->setCfsslUri($cfsslUri);
		} else {
			$this->cfsslHandler->setCfsslUri(CfsslHandler::CFSSL_URI);
			if (!$binary) {
				$binary = $this->config->getAppValue(Application::APP_ID, 'cfssl_bin');
				if ($binary && !file_exists($binary)) {
					$this->config->deleteAppValue(Application::APP_ID, 'cfssl_bin');
					$binary = '';
				}
				if (!$binary) {
					/**
					 * @todo Suggestion: run this in a background proccess
					 * to make more fast the setup and, maybe, implement a new endpoint
					 * to start downloading of all binaries files in a background process
					 * and return the status progress of download.
					 */
					$this->installCfssl();
					$binary = $this->config->getAppValue(Application::APP_ID, 'cfssl_bin');
				}
			}
		}
		if ($binary) {
			$this->cfsslHandler->setBinary($binary);
			$this->cfsslHandler->genkey();
		}
		for ($i = 1;$i <= 4;$i++) {
			if ($this->cfsslHandler->health($this->cfsslHandler->getCfsslUri())) {
				break;
			}
			// @codeCoverageIgnoreStart
			sleep(2);
			// @codeCoverageIgnoreEnd
		}

		$this->config->setAppValue(Application::APP_ID, 'authkey', $key);
		$this->config->setAppValue(Application::APP_ID, 'commonName', $commonName);
		$this->config->setAppValue(Application::APP_ID, 'country', $country);
		$this->config->setAppValue(Application::APP_ID, 'organization', $organization);
		$this->config->setAppValue(Application::APP_ID, 'organizationUnit', $organizationUnit);
		$this->config->setAppValue(Application::APP_ID, 'cfsslUri', $this->cfsslHandler->getCfsslUri());
		$this->config->setAppValue(Application::APP_ID, 'configPath', $configPath);
		$this->config->setAppValue(Application::APP_ID, 'notifyUnsignedUser', 1);
	}
}
