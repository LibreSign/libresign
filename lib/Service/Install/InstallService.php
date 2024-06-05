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
use OC;
use OC\Archive\TAR;
use OC\Archive\ZIP;
use OC\Memcache\NullCache;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\InvalidSignatureException;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\AEngineHandler;
use OCA\Libresign\Handler\CertificateEngine\CfsslHandler;
use OCA\Libresign\Handler\CertificateEngine\Handler as CertificateEngineHandler;
use OCA\Libresign\Handler\CertificateEngine\IEngineHandler;
use OCA\Libresign\Handler\JSignPdfHandler;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
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
		'cfssl'
	];
	private string $architecture;

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

	public function setArchitecture(string $architecture): void {
		$this->architecture = $architecture;
	}

	private function getFolder(string $path = '', ?ISimpleFolder $folder = null): ISimpleFolder {
		if (!$folder) {
			$folder = $this->appData->getFolder('/');
			if (!$path) {
				$path = $this->architecture;
			} else {
				$path = $this->architecture . '/' . $path;
			}
			$path = explode('/', $path);
			foreach ($path as $snippet) {
				$folder = $this->getFolder($snippet, $folder);
			}
			return $folder;
		}
		try {
			$folder = $folder->getFolder($path, $folder);
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
		return (int) $pid;
	}

	public function setResource(string $resource): self {
		$this->resource = $resource;
		return $this;
	}

	public function isDownloadedFilesOk(): bool {
		try {
			return count($this->signSetupService->verify($this->architecture, $this->resource)) === 0;
		} catch (InvalidSignatureException $e) {
			return false;
		}
	}

	public function installJava(?bool $async = false): void {
		$this->setResource('java');
		if ($this->isDownloadedFilesOk()) {
			return;
		}
		if ($async) {
			$this->runAsync();
			return;
		}
		$extractDir = $this->getFullPath() . '/' . $this->resource;
		$javaFolder = $this->getFolder($this->resource);

		/**
		 * Steps to update:
		 *     Check the compatible version of Java to use JSignPdf
		 *     Update all the follow data
		 *     Update the constants with java version
		 * URL used to get the MD5 and URL to download:
		 * https://jdk.java.net/java-se-ri/8-MR3
		 */
		if (PHP_OS_FAMILY === 'Linux') {
			$linuxDistribution = $this->getLinuxDistributionToDownloadJava();
			if ($this->architecture === 'x86_64') {
				$compressedFileName = 'OpenJDK21U-jre_x64_' . $linuxDistribution . '_hotspot_' . self::JAVA_PARTIAL_VERSION . '.tar.gz';
				$url = 'https://github.com/adoptium/temurin21-binaries/releases/download/jdk-' . self::JAVA_URL_PATH_NAME  . '/' . $compressedFileName;
			} elseif ($this->architecture === 'aarch64') {
				$compressedFileName = 'OpenJDK21U-jre_aarch64_' . $linuxDistribution . '_hotspot_' . self::JAVA_PARTIAL_VERSION . '.tar.gz';
				$url = 'https://github.com/adoptium/temurin21-binaries/releases/download/jdk-' . self::JAVA_URL_PATH_NAME . '/' . $compressedFileName;
			}
			$class = TAR::class;
		} else {
			throw new RuntimeException(sprintf('OS_FAMILY %s is incompatible with LibreSign.', PHP_OS_FAMILY));
		}
		$checksumUrl = $url . '.sha256.txt';
		$hash = $this->getHash($compressedFileName, $checksumUrl);
		try {
			$compressedFile = $javaFolder->getFile($compressedFileName);
		} catch (NotFoundException $th) {
			$compressedFile = $javaFolder->newFile($compressedFileName);
		}
		$comporessedInternalFileName = $this->getDataDir() . '/' . $this->getInternalPathOfFile($compressedFile);

		$this->download($url, 'java', $comporessedInternalFileName, $hash, 'sha256');

		$extractor = new $class($comporessedInternalFileName);
		$extractor->extract($extractDir);
		unlink($comporessedInternalFileName);

		$this->appConfig->setAppValue('java_path', $extractDir . '/jdk-' . self::JAVA_URL_PATH_NAME . '-jre/bin/java');
		$this->removeDownloadProgress();
	}

	/**
	 * Return linux or alpine-linux
	 */
	private function getLinuxDistributionToDownloadJava(): string {
		$distribution = shell_exec('cat /etc/*-release');
		preg_match('/^ID=(?<version>.*)$/m', $distribution, $matches);
		if (isset($matches['version']) && strtolower($matches['version']) === 'alpine') {
			return 'alpine-linux';
		}
		return 'linux';
	}

	public function uninstallJava(): void {
		$javaPath = $this->appConfig->getAppValue('java_path');
		if (!$javaPath) {
			return;
		}
		$this->setResource('java');
		$folder = $this->getFolder($this->resource);
		try {
			$folder->delete();
		} catch (NotFoundException $th) {
		}
		$this->appConfig->deleteAppValue('java_path');
	}

	public function installJSignPdf(?bool $async = false): void {
		if (!extension_loaded('zip')) {
			throw new RuntimeException('Zip extension is not available');
		}
		$this->setResource('jsignpdf');
		if ($this->isDownloadedFilesOk()) {
			return;
		}
		if ($async) {
			$this->runAsync();
			return;
		}
		$extractDir = $this->getFullPath() . '/' . $this->resource;

		$compressedFileName = 'jsignpdf-' . JSignPdfHandler::VERSION . '.zip';
		try {
			$compressedFile = $this->getFolder($this->resource)->getFile($compressedFileName);
		} catch (\Throwable $th) {
			$compressedFile = $this->getFolder($this->resource)->newFile($compressedFileName);
		}
		$comporessedInternalFileName = $this->getDataDir() . '/' . $this->getInternalPathOfFile($compressedFile);
		$url = 'https://github.com/intoolswetrust/jsignpdf/releases/download/JSignPdf_' . str_replace('.', '_', JSignPdfHandler::VERSION) . '/jsignpdf-' . JSignPdfHandler::VERSION . '.zip';
		/** WHEN UPDATE version: generate this hash handmade and update here */
		$hash = '7c66f5a9f5e7e35b601725414491a867';

		$this->download($url, 'JSignPdf', $comporessedInternalFileName, $hash);

		$zip = new ZIP($extractDir . '/' . $compressedFileName);
		$zip->extract($extractDir);
		unlink($extractDir . '/' . $compressedFileName);

		$fullPath = $extractDir . '/jsignpdf-' . JSignPdfHandler::VERSION . '/JSignPdf.jar';
		$this->appConfig->setAppValue('jsignpdf_jar_path', $fullPath);
		$this->removeDownloadProgress();
	}

	public function uninstallJSignPdf(): void {
		$jsignpdJarPath = $this->appConfig->getAppValue('jsignpdf_jar_path');
		if (!$jsignpdJarPath) {
			return;
		}
		$this->setResource('jsignpdf');
		$folder = $this->getFolder($this->resource);
		try {
			$folder->delete();
		} catch (NotFoundException $e) {
		}
		$this->appConfig->deleteAppValue('jsignpdf_jar_path');
	}

	public function installPdftk(?bool $async = false): void {
		$this->setResource('pdftk');
		if ($this->isDownloadedFilesOk()) {
			return;
		}
		if ($async) {
			$this->runAsync();
			return;
		}

		try {
			$file = $this->getFolder($this->resource)->getFile('pdftk.jar');
		} catch (\Throwable $th) {
			$file = $this->getFolder($this->resource)->newFile('pdftk.jar');
		}
		$fullPath = $this->getDataDir() . '/' . $this->getInternalPathOfFile($file);
		$url = 'https://gitlab.com/api/v4/projects/5024297/packages/generic/pdftk-java/v' . self::PDFTK_VERSION . '/pdftk-all.jar';
		/** WHEN UPDATE version: generate this hash handmade and update here */
		$hash = '59a28bed53b428595d165d52988bf4cf';

		$this->download($url, 'pdftk', $fullPath, $hash);

		$this->appConfig->setAppValue('pdftk_path', $fullPath);
		$this->removeDownloadProgress();
	}

	public function uninstallPdftk(): void {
		$jsignpdJarPath = $this->appConfig->getAppValue('pdftk_path');
		if (!$jsignpdJarPath) {
			return;
		}
		$this->setResource('pdftk');
		$folder = $this->getFolder($this->resource);
		try {
			$folder->delete();
		} catch (NotFoundException $e) {
		}
		$this->appConfig->deleteAppValue('pdftk_path');
	}

	public function installCfssl(?bool $async = false): void {
		if ($this->certificateEngineHandler->getEngine()->getName() !== 'cfssl') {
			if (!$async) {
				throw new InvalidArgumentException('Set the engine to cfssl with: config:app:set libresign certificate_engine --value cfssl');
			}
			return;
		}
		$this->setResource('cfssl');
		if ($this->isDownloadedFilesOk()) {
			return;
		}
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

	private function installCfsslByArchitecture(string $arcitecture): void {
		$folder = $this->getFolder($this->resource);

		$downloads = [
			[
				'file' => 'cfssl_' . self::CFSSL_VERSION . '_linux_' . $arcitecture,
				'destination' => 'cfssl',
			],
			[
				'file' => 'cfssljson_' . self::CFSSL_VERSION . '_linux_' . $arcitecture,
				'destination' => 'cfssljson',
			],
		];
		$baseUrl = 'https://github.com/cloudflare/cfssl/releases/download/v' . self::CFSSL_VERSION . '/';
		$checksumUrl = 'https://github.com/cloudflare/cfssl/releases/download/v' . self::CFSSL_VERSION . '/cfssl_' . self::CFSSL_VERSION . '_checksums.txt';
		foreach ($downloads as $download) {
			$hash = $this->getHash($download['file'], $checksumUrl);

			$file = $folder->newFile($download['destination']);
			$fullPath = $this->getDataDir() . '/' . $this->getInternalPathOfFile($file);

			$this->download($baseUrl . $download['file'], $download['destination'], $fullPath, $hash, 'sha256');

			chmod($fullPath, 0700);
		}

		$cfsslBinPath = $this->getDataDir() . '/' .
			$this->getInternalPathOfFolder($folder) . '/' .
			$downloads[0]['destination'];
		$this->appConfig->setAppValue('cfssl_bin', $cfsslBinPath);
	}

	public function uninstallCfssl(): void {
		$cfsslPath = $this->appConfig->getAppValue('cfssl_bin');
		if (!$cfsslPath) {
			return;
		}
		$this->setResource('cfssl');
		$folder = $this->getFolder($this->resource);
		try {
			$folder->delete();
		} catch (NotFoundException $e) {
		}
		$this->appConfig->deleteAppValue('cfssl_bin');
	}

	public function isCfsslBinInstalled(): bool {
		if ($this->appConfig->getAppValue('cfssl_bin')) {
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

		$this->appConfig->setAppValue('root_cert', json_encode($rootCert));
		$this->appConfig->setAppValue('authkey', $privateKey);
		/** @var AEngineHandler $engine */
		if ($engine->getEngine() === 'cfssl') {
			$this->appConfig->setAppValue('config_path', $engine->getConfigPath());
		}
		$this->appConfig->setAppValue('notify_unsigned_user', '1');
	}
}
