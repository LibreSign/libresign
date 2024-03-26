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
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\Handler as CertificateEngineHandler;
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

	public function __construct(
		ICacheFactory $cacheFactory,
		private IClientService $clientService,
		private CertificateEngineHandler $certificateEngineHandler,
		private IConfig $config,
		private IAppConfig $appConfig,
		private IRootFolder $rootFolder,
		private LoggerInterface $logger,
		protected IAppDataFactory $appDataFactory,
	) {
		$this->cache = $cacheFactory->createDistributed('libresign-setup');
		$this->appData = $appDataFactory->get('libresign');
	}

	public function setOutput(OutputInterface $output): void {
		$this->output = $output;
	}

	private function getFolder(string $path = ''): ISimpleFolder {
		$folder = $this->appData->getFolder('/');
		if ($path) {
			try {
				$folder = $folder->getFolder($path);
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
			$path = $folder->getInternalPath() . DIRECTORY_SEPARATOR . $node->getName();
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

	public function getFullPath(): string {
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

	public function saveErrorMessage(string $message) {
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

	public function installJava(?bool $async = false): void {
		$signatureEngine = $this->appConfig->getAppValue('signature_engine', 'jsignpdf');
		if ($signatureEngine !== 'jsignpdf') {
			return [];
		}
		$this->setResource('java');
		if ($async) {
			$this->runAsync();
			return;
		}
		$extractDir = $this->getFullPath() . DIRECTORY_SEPARATOR . 'java';
		$javaFolder = $this->getFolder('java');

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
				$compressedFileName = 'OpenJDK21U-jre_x64_linux_hotspot_' . self::JAVA_PARTIAL_VERSION . '.tar.gz';
				$url = 'https://github.com/adoptium/temurin21-binaries/releases/download/jdk-21.0.2+13/' . $compressedFileName;
			} elseif ($architecture === 'aarch64') {
				$compressedFileName = 'OpenJDK21U-jre_aarch64_linux_hotspot_' . self::JAVA_PARTIAL_VERSION . '.tar.gz';
				$url = 'https://github.com/adoptium/temurin21-binaries/releases/download/jdk-21.0.2+13/' . $compressedFileName;
			}
			$class = TAR::class;
		} else {
			throw new RuntimeException(sprintf('OS_FAMILY %s is incompatible with LibreSign.', PHP_OS_FAMILY));
		}
		$folder = $this->getFolder();
		$checksumUrl = $url . '.sha256.txt';
		$hash = $this->getHash($folder, 'java', $compressedFileName, self::JAVA_PARTIAL_VERSION, $checksumUrl);
		try {
			$compressedFile = $javaFolder->getFile($compressedFileName);
		} catch (NotFoundException $th) {
			$compressedFile = $javaFolder->newFile($compressedFileName);
		}
		$comporessedInternalFileName = $this->getDataDir() . DIRECTORY_SEPARATOR . $this->getInternalPathOfFile($compressedFile);

		$this->download($url, 'java', $comporessedInternalFileName, $hash, 'sha256');

		$extractor = new $class($comporessedInternalFileName);
		$extractor->extract($extractDir);

		$this->appConfig->setAppValue('java_path', $extractDir . '/jdk-21.0.2+13-jre/bin/java');
		$this->removeDownloadProgress();
	}

	public function uninstallJava(): void {
		$javaPath = $this->appConfig->getAppValue('java_path');
		if (!$javaPath) {
			return;
		}
		$appFolder = $this->getFolder('/');
		$name = $appFolder->getName();
		if (!strpos($javaPath, $name)) {
			return;
		}
		if (PHP_OS_FAMILY !== 'Windows') {
			exec('rm -rf ' . $this->getDataDir() . '/' . $this->getInternalPathOfFolder($this->getFolder()) . '/java');
		}
		try {
			$javaFolder = $appFolder->getFolder('/libresign/java');
			$javaFolder->delete();
		} catch (NotFoundException $th) {
		}
		$this->appConfig->deleteAppValue('java_path');
	}

	public function installJSignPdf(?bool $async = false): void {
		$signatureEngine = $this->appConfig->getAppValue('signature_engine', 'jsignpdf');
		if ($signatureEngine !== 'jsignpdf') {
			return [];
		}
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
		try {
			$compressedFile = $this->getFolder()->getFile($compressedFileName);
		} catch (\Throwable $th) {
			$compressedFile = $this->getFolder()->newFile($compressedFileName);
		}
		$comporessedInternalFileName = $this->getDataDir() . DIRECTORY_SEPARATOR . $this->getInternalPathOfFile($compressedFile);
		$url = 'https://github.com/intoolswetrust/jsignpdf/releases/download/JSignPdf_' . str_replace('.', '_', JSignPdfHandler::VERSION) . '/jsignpdf-' . JSignPdfHandler::VERSION . '.zip';
		/** WHEN UPDATE version: generate this hash handmade and update here */
		$hash = '7c66f5a9f5e7e35b601725414491a867';

		$this->download($url, 'JSignPdf', $comporessedInternalFileName, $hash);

		$zip = new ZIP($extractDir . DIRECTORY_SEPARATOR . $compressedFileName);
		$zip->extract($extractDir);

		$fullPath = $extractDir . DIRECTORY_SEPARATOR . 'jsignpdf-' . JSignPdfHandler::VERSION . DIRECTORY_SEPARATOR . 'JSignPdf.jar';
		$this->appConfig->setAppValue('jsignpdf_jar_path', $fullPath);
		$this->removeDownloadProgress();
	}

	public function uninstallJSignPdf(): void {
		$jsignpdJarPath = $this->appConfig->getAppValue('jsignpdf_jar_path');
		if (!$jsignpdJarPath) {
			return;
		}
		$appFolder = $this->appData->getFolder('/');
		$name = $appFolder->getName();
		// Remove prefix
		$path = explode($name, $jsignpdJarPath)[1];
		// Remove sufix
		$path = trim($path, DIRECTORY_SEPARATOR . 'JSignPdf.jar');
		try {
			$folder = $appFolder->getFolder($path);
			$folder->delete();
		} catch (NotFoundException $e) {
		}
		$this->appConfig->deleteAppValue('jsignpdf_jar_path');
	}

	public function installPdftk(?bool $async = false): void {
		$this->setResource('pdftk');
		if ($async) {
			$this->runAsync();
			return;
		}

		try {
			$file = $this->getFolder()->getFile('pdftk.jar');
		} catch (\Throwable $th) {
			$file = $this->getFolder()->newFile('pdftk.jar');
		}
		$fullPath = $this->getDataDir() . DIRECTORY_SEPARATOR . $this->getInternalPathOfFile($file);
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
		$appFolder = $this->appData->getFolder('/');
		$name = $appFolder->getName();
		// Remove prefix
		$path = explode($name, $jsignpdJarPath)[1];
		try {
			$file = $appFolder->getFile($path);
			$file->delete();
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
			$fullPath = $this->getDataDir() . DIRECTORY_SEPARATOR . $this->getInternalPathOfFile($file);

			$this->download($baseUrl . $download['file'], $download['destination'], $fullPath, $hash, 'sha256');

			chmod($fullPath, 0700);
		}

		$cfsslBinPath = $this->getDataDir() . DIRECTORY_SEPARATOR .
			$this->getInternalPathOfFolder($this->getFolder()) . DIRECTORY_SEPARATOR .
			$downloads[0]['destination'];
		$this->appConfig->setAppValue('cfssl_bin', $cfsslBinPath);
	}

	private function installCfsslArm(): void {
		$appFolder = $this->getFolder();
		try {
			$cfsslFolder = $appFolder->getFolder('cfssl');
		} catch (NotFoundException $th) {
			$cfsslFolder = $appFolder->newFolder('cfssl');
		}
		$compressedFileName = 'cfssl-' . self::CFSSL_VERSION . '-1-aarch64.pkg.tar.xz';
		$url = 'http://mirror.archlinuxarm.org/aarch64/community/' . $compressedFileName;
		// Generated handmade with command sha256sum
		$hash = '944a6c54e53b0e2ef04c9b22477eb5f637715271c74ccea9bb91d7ac0473b855';
		try {
			$compressedFile = $cfsslFolder->getFile($compressedFileName);
		} catch (NotFoundException $th) {
			$compressedFile = $cfsslFolder->newFile($compressedFileName);
		}

		$comporessedInternalFileName = $this->getDataDir() . DIRECTORY_SEPARATOR . $this->getInternalPathOfFile($compressedFile);

		$this->download($url, 'cfssl', $comporessedInternalFileName, $hash, 'sha256');

		$this->appConfig->deleteAppValue('cfssl_bin');
		$extractor = new TAR($comporessedInternalFileName);

		$extractDir = $this->getFullPath() . DIRECTORY_SEPARATOR . 'cfssl';
		$result = $extractor->extract($extractDir);
		if (!$result) {
			throw new \RuntimeException('Error to extract xz file. Install xz. Read more: https://github.com/codemasher/php-ext-xz');
		}
		$cfsslBinPath = $this->getDataDir() . DIRECTORY_SEPARATOR .
			$this->getInternalPathOfFolder($this->getFolder()) . DIRECTORY_SEPARATOR .
			'cfssl/usr/bin/cfssl';
		$this->appConfig->setAppValue('cfssl_bin', $cfsslBinPath);
	}

	public function uninstallCfssl(): void {
		$cfsslPath = $this->appConfig->getAppValue('cfssl_bin');
		if (!$cfsslPath) {
			return;
		}
		$appFolder = $this->appData->getFolder('/');
		$name = $appFolder->getName();
		// Remove prefix
		$path = explode($name, $cfsslPath)[1];
		try {
			$folder = $appFolder->getFolder($path);
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

	private function getHash(ISimpleFolder $folder, string $type, string $file, string $version, string $checksumUrl): string {
		$hashFileName = 'checksums_' . $type . '_' . $version . '.txt';
		try {
			$fileObject = $folder->getFile($hashFileName);
		} catch (NotFoundException $th) {
			$hashes = file_get_contents($checksumUrl);
			if (!$hashes) {
				throw new LibresignException('Failute to download hash file. URL: ' . $checksumUrl);
			}
			$fileObject = $folder->newFile($hashFileName, $hashes);
		}
		try {
			$hashes = $fileObject->getContent();
		} catch (\Throwable $th) {
		}
		if (empty($hashes)) {
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
		$rootCert = [
			'commonName' => $commonName,
			'names' => $names
		];
		$engine = $this->certificateEngineHandler->getEngine($properties['engine'] ?? '', $rootCert);
		if ($engine->getEngine() === 'cfssl') {
			$engine->setCfsslUri($properties['cfsslUri']);
		}

		$engine->setConfigPath($properties['configPath'] ?? '');

		$privateKey = $engine->generateRootCert(
			$commonName,
			$names
		);

		$this->appConfig->setAppValue('root_cert', json_encode($rootCert));
		$this->appConfig->setAppValue('authkey', $privateKey);
		$this->appConfig->setAppValue('config_path', $engine->getConfigPath());
		$this->appConfig->setAppValue('notify_unsigned_user', '1');
	}
}
