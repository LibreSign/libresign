<?php

declare(strict_types=1);

namespace OCA\Libresign\Service;

use OC\Archive\TAR;
use OC\Archive\ZIP;
use OC\SystemConfig;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\JSignPdfHandler;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\ITempManager;
use RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class InstallService {
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
	/** @var OutputInterface */
	private $output;

	public function __construct(
		ITempManager $tempManager,
		IClientService $clientService,
		IConfig $config,
		SystemConfig $systemConfig,
		IRootFolder $rootFolder
	) {
		$this->tempManager = $tempManager;
		$this->clientService = $clientService;
		$this->config = $config;
		$this->systemConfig = $systemConfig;
		$this->rootFolder = $rootFolder;
	}

	public function setOutput(OutputInterface $output): void {
		$this->output = $output;
	}

	public function getFolder($path = ''): Folder {
		$rootFolder = $this->getAppRootFolder();
		try {
			$folder = $rootFolder->newFolder(Application::APP_ID . DIRECTORY_SEPARATOR . $path);
		} catch (\Throwable $th) {
			$folder = $rootFolder->get(Application::APP_ID . DIRECTORY_SEPARATOR . $path);
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

	public function installJava(): void {
		$extractDir = $this->getFullPath();

		/**
		 * To update:
		 * Check the compatible version of Java to use JSignPdf and update all the follow data
		 * URL used to get the MD5 and URL to download:
		 * https://jdk.java.net/java-se-ri/8-MR3
		 */
		if (PHP_OS_FAMILY === 'Windows') {
			$url = 'https://download.java.net/openjdk/jdk8u41/ri/openjdk-8u41-b04-windows-i586-14_jan_2020.zip';
			$tempFile = $this->tempManager->getTemporaryFile('.zip');
			$executableExtension = '.exe';
			$class = ZIP::class;
			$md5 = '48ac2152d1fb0ad1d343104be210d532';
		} else {
			$url = 'https://download.java.net/openjdk/jdk8u41/ri/openjdk-8u41-b04-linux-x64-14_jan_2020.tar.gz';
			$tempFile = $this->tempManager->getTemporaryFile('.tar.gz');
			$executableExtension = '';
			$class = TAR::class;
			$md5 = '35f515e9436f4fefad091db2c1450c5f';
		}

		$this->download($url, 'java', $tempFile, $md5);

		$extractor = new $class($tempFile);
		$extractor->extract($extractDir);

		$this->config->setAppValue(Application::APP_ID, 'java_path', $extractDir . '/java-se-8u41-ri/bin/java' . $executableExtension);
	}

	public function uninstallJava(): void {
		$javaPath = $this->config->getAppValue(Application::APP_ID, 'java_path');
		if (!$javaPath) {
			return;
		}
		$appFolder = $this->getAppRootFolder();
		$name = $appFolder->getName();
		// Remove prefix
		$path = explode($name, $javaPath)[1];
		// Remove binary path
		$path = explode(DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR, $path)[0];
		try {
			$folder = $appFolder->get($path);
			$folder->delete();
		} catch (NotFoundException $e) {
		}
		$this->config->deleteAppValue(Application::APP_ID, 'java_path');
	}

	public function installJSignPdf(): void {
		if (!extension_loaded('zip')) {
			throw new RuntimeException('Zip extension is not available');
		}
		$extractDir = $this->getFullPath();

		$tempFile = $this->tempManager->getTemporaryFile('.zip');
		$url = 'https://sourceforge.net/projects/jsignpdf/files/stable/JSignPdf%20' . JSignPdfHandler::VERSION . '/jsignpdf-' . JSignPdfHandler::VERSION . '.zip';

		$this->download($url, 'JSignPdf', $tempFile);

		$zip = new ZIP($tempFile);
		$zip->extract($extractDir);

		$fullPath = $extractDir . DIRECTORY_SEPARATOR. 'jsignpdf-' . JSignPdfHandler::VERSION . DIRECTORY_SEPARATOR. 'JSignPdf.jar';
		$this->config->setAppValue(Application::APP_ID, 'jsignpdf_jar_path', $fullPath);
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

	public function installCli(): void {
		$folder = $this->getFolder();

		if (PHP_OS_FAMILY === 'Windows') {
			throw new \RuntimeException('LibreSign CLI do not work in Windows!');
		} elseif (PHP_OS_FAMILY === 'Darwin') {
			$url = 'https://github.com/LibreSign/libresign-cli/releases/download/v0.0.4/libresign_0.0.4_Linux_arm64';
		} elseif (PHP_OS_FAMILY === 'Linux') {
			if (PHP_INT_SIZE === 4) {
				$url = 'https://github.com/LibreSign/libresign-cli/releases/download/v0.0.4/libresign_0.0.4_Linux_i386';
			} else {
				$url = 'https://github.com/LibreSign/libresign-cli/releases/download/v0.0.4/libresign_0.0.4_Linux_x86_64';
			}
		}
		$file = $folder->newFile('libresign-cli');
		$fullPath = $this->getDataDir() . DIRECTORY_SEPARATOR . $file->getInternalPath();

		$this->download($url, 'libresign-cli', $fullPath);

		if (PHP_OS_FAMILY !== 'Windows') {
			chmod($fullPath, 0700);
		}

		$this->config->setAppValue(Application::APP_ID, 'libresign_cli_path', $fullPath);
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

	public function installCfssl(): void {
		$folder = $this->getFolder();

		if (PHP_OS_FAMILY === 'Windows') {
			$downloads = [
				[
					'url' => 'https://github.com/cloudflare/cfssl/releases/download/v1.6.1/cfssl_1.6.1_windows_amd64.exe',
					'destination' => 'cfssl.exe',
				],
				[
					'url' => 'https://github.com/cloudflare/cfssl/releases/download/v1.6.1/cfssljson_1.6.1_windows_amd64.exe',
					'destination' => 'cfssljson.exe',
				],
			];
		} elseif (PHP_OS_FAMILY === 'Darwin') {
			$downloads = [
				[
					'url' => 'https://github.com/cloudflare/cfssl/releases/download/v1.6.1/cfssl_1.6.1_darwin_amd64',
					'destination' => 'cfssl',
				],
				[
					'url' => 'https://github.com/cloudflare/cfssl/releases/download/v1.6.1/cfssljson_1.6.1_darwin_amd64',
					'destination' => 'cfssljson',
				],
			];
		} else {
			$downloads = [
				[
					'url' => 'https://github.com/cloudflare/cfssl/releases/download/v1.6.1/cfssl_1.6.1_linux_amd64',
					'destination' => 'cfssl',
				],
				[
					'url' => 'https://github.com/cloudflare/cfssl/releases/download/v1.6.1/cfssljson_1.6.1_linux_amd64',
					'destination' => 'cfssljson',
				],
			];
		}
		foreach ($downloads as $download) {
			$file = $folder->newFile($download['destination']);
			$fullPath = $this->getDataDir() . DIRECTORY_SEPARATOR . $file->getInternalPath();

			$this->download($download['url'], $download['destination'], $fullPath);

			if (PHP_OS_FAMILY !== 'Windows') {
				chmod($fullPath, 0700);
			}
		}

		$this->config->setAppValue(Application::APP_ID, 'cfssl_bin', 1);
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

	protected function download(string $url, string $filename, string $path, ?string $md5 = '') {
		if (php_sapi_name() === 'cli' && $this->output instanceof OutputInterface) {
			$this->downloadCli($url, $filename, $path, $md5);
			return;
		}
		$client = $this->clientService->newClient();
		try {
			$client->get($url, [
				'sink' => $path,
				'timeout' => 0
			]);
		} catch (\Exception $e) {
			throw new LibresignException('Failure on download ' . $filename . " try again.\n" . $e->getMessage());
		}
		if ($md5 && file_exists($path) && md5_file($path) !== $md5) {
			throw new LibresignException('Failure on download ' . $filename . ' try again. Invalid md5.');
		}
	}

	protected function downloadCli(string $url, string $filename, string $path, ?string $md5 = '') {
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
				},
			]);
		} catch (\Exception $e) {
			$this->output->writeln('<error>Failure on download ' . $filename . ' try again.</error>');
			$this->output->writeln('<error>' . $e->getMessage() . '</error>');
		}
		$progressBar->finish();
		$this->output->writeln('');
		$progressBar->finish();
		if ($md5 && file_exists($path) && md5_file($path) !== $md5) {
			$this->output->writeln('<error>Failure on download ' . $filename . ' try again</error>');
			$this->output->writeln('<error>Invalid MD5</error>');
		}
	}
}
