<?php

declare(strict_types=1);

namespace OCA\Libresign\Command;

use OC\Archive\TAR;
use OC\Archive\ZIP;
use OC\Core\Command\Base as CommandBase;
use OC\SystemConfig;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\JSignPdfHandler;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\ITempManager;
use RuntimeException;

class Base extends CommandBase {
	/** @var ITempManager */
	private $tempManager;
	/** @var IClientService */
	private $clientService;
	/** @var IConfig */
	protected $config;
	/** @var SystemConfig */
	private $systemConfig;
	/** @var IRootFolder */
	private $rootFolder;

	public function __construct(
		ITempManager $tempManager,
		IClientService $clientService,
		IConfig $config,
		SystemConfig $systemConfig,
		IRootFolder $rootFolder
	) {
		parent::__construct();
		$this->tempManager = $tempManager;
		$this->clientService = $clientService;
		$this->config = $config;
		$this->systemConfig = $systemConfig;
		$this->rootFolder = $rootFolder;
	}

	protected function getFolder($path = ''): Folder {
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

	protected function getFullPath(): string {
		$folder = $this->getFolder();
		return $this->getDataDir() . '/' . $folder->getInternalPath();
	}

	protected function installJava(): void {
		$extractDir = $this->getFullPath();

		if (PHP_OS_FAMILY === 'Windows') {
			$url = 'https://download.java.net/openjdk/jdk8u41/ri/openjdk-8u41-b04-windows-i586-14_jan_2020.zip';
			$tempFile = $this->tempManager->getTemporaryFile('.zip');
			$executableExtension = '.exe';
			$class = ZIP::class;
		} else {
			$url = 'https://download.java.net/openjdk/jdk8u41/ri/openjdk-8u41-b04-linux-x64-14_jan_2020.tar.gz';
			$tempFile = $this->tempManager->getTemporaryFile('.tar.gz');
			$executableExtension = '';
			$class = TAR::class;
		}


		$client = $this->clientService->newClient();
		$client->get($url, ['sink' => $tempFile, 'timeout' => 0]);

		$extractor = new $class($tempFile);
		$extractor->extract($extractDir);

		$this->config->setAppValue(Application::APP_ID, 'java_path', $extractDir . '/java-se-8u41-ri/bin/java' . $executableExtension);
	}

	protected function uninstallJava(): void {
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

	protected function installJSignPdf(): void {
		if (!extension_loaded('zip')) {
			throw new RuntimeException('Zip extension is not available');
		}
		$extractDir = $this->getFullPath();

		$tempFile = $this->tempManager->getTemporaryFile('.zip');
		$url = 'https://sourceforge.net/projects/jsignpdf/files/stable/JSignPdf%20' . JSignPdfHandler::VERSION . '/jsignpdf-' . JSignPdfHandler::VERSION . '.zip';

		$client = $this->clientService->newClient();
		$client->get($url, ['sink' => $tempFile, 'timeout' => 0]);

		$zip = new ZIP($tempFile);
		$zip->extract($extractDir);

		$fullPath = $extractDir . DIRECTORY_SEPARATOR. 'jsignpdf-' . JSignPdfHandler::VERSION . DIRECTORY_SEPARATOR. 'JSignPdf.jar';
		$this->config->setAppValue(Application::APP_ID, 'jsignpdf_jar_path', $fullPath);
	}

	protected function uninstallJSignPdf(): void {
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

	protected function installCfssl(): void {
		$folder = $this->getFolder();

		$binName = 'cfssl';
		if (PHP_OS_FAMILY === 'Windows') {
			$url = 'https://github.com/cloudflare/cfssl/releases/download/v1.6.1/cfssl_1.6.1_windows_amd64.exe';
			$binName = 'cfssl.exe';
		} elseif (PHP_OS_FAMILY === 'Darwin') {
			$url = 'https://github.com/cloudflare/cfssl/releases/download/v1.6.1/multirootca_1.6.1_darwin_amd64';
		} else {
			$url = 'https://github.com/cloudflare/cfssl/releases/download/v1.6.1/cfssl_1.6.1_linux_amd64';
		}
		$file = $folder->newFile($binName);

		$fullPath = $this->getDataDir() . DIRECTORY_SEPARATOR . $file->getInternalPath();

		$client = $this->clientService->newClient();
		$client->get($url, ['sink' => $fullPath, 'timeout' => 0]);

		if (PHP_OS_FAMILY !== 'Windows') {
			chmod($fullPath, 0700);
		}

		$this->config->setAppValue(Application::APP_ID, 'cfssl_bin', 1);
	}

	protected function uninstallCfssl(): void {
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
}
