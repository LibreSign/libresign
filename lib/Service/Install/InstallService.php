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
use OCA\Libresign\Service\CaIdentifierService;
use OCA\Libresign\Service\Process\ProcessManager;
use OCA\Libresign\Vendor\Symfony\Component\Process\Process;
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

class InstallService {
	use TSimpleFile {
		getInternalPathOfFile as getInternalPathOfFileTrait;
		getInternalPathOfFolder as getInternalPathOfFolderTrait;
	}

	public const JAVA_VERSION = 'openjdk version "21.0.8" 2025-07-15 LTS';
	public const string JAVA_URL_PATH_NAME = '21.0.8+9';
	public const PDFTK_VERSION = '3.3.3'; /** @todo When update, verify the hash **/
	private const string PDFTK_HASH = '59a28bed53b428595d165d52988bf4cf';
	public const JSIGNPDF_VERSION = JSignPdfRelease::VERSION;
	public const CFSSL_VERSION = '1.6.5';
	private const string PROCESS_SOURCE = 'install';

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
	private InstallTarget $target;
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
		private CaIdentifierService $caIdentifierService,
		private ProcessManager $processManager,
	) {
		$this->cache = $cacheFactory->createDistributed('libresign-setup');
		$this->appData = $appDataFactory->get('libresign');
		$this->target = InstallTarget::current();
	}

	public function setOutput(OutputInterface $output): void {
		$this->output = $output;
	}

	public function setArchitecture(string $architecture): self {
		$this->target = $this->target->withArchitecture($architecture);
		return $this;
	}

	public function getArchitecture(): string {
		return $this->target->architecture();
	}

	private function getFolder(string $path = '', ?ISimpleFolder $folder = null, bool $needToBeEmpty = false): ISimpleFolder {
		if (!$folder) {
			$folder = $this->appData->getFolder('/');
			if (!$path) {
				$path = $this->target->architecture();
			} elseif ($path === 'java') {
				$path = $this->target->architecture() . '/' . $this->getLinuxDistributionToDownloadJava() . '/java';
			} else {
				$path = $this->target->architecture() . '/' . $path;
			}
			$path = explode('/', $path);
			foreach ($path as $snippet) {
				$folder = $this->getFolder($snippet, $folder, $needToBeEmpty);
			}
			return $folder;
		}
		try {
			$folder = $folder->getFolder($path);
			if ($needToBeEmpty && $path !== $this->target->architecture()) {
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
		$command = [
			OC::$SERVERROOT . '/occ',
			'libresign:install',
			'--' . $resource,
			'--architecture=' . $this->target->architecture(),
		];
		if ($resource === 'java') {
			$command[] = '--distro=' . $this->target->distro();
		}
		$process = $this->createProcess($command);
		$process->setOptions(['create_new_console' => true]);
		$process->setTimeout(null);
		$process->start();
		$data['pid'] = $process->getPid();
		if ($data['pid']) {
			$this->processManager->register(self::PROCESS_SOURCE, (int)$data['pid'], [
				'resource' => $resource,
				'architecture' => $this->target->architecture(),
				'distro' => $this->target->distro(),
			]);
			$this->setCache($resource, $data);
		} else {
			$message = 'Error to get PID of background install process. Command: '
				. OC::$SERVERROOT . '/occ libresign:install --' . $resource;
			$this->logger->error($message);
			$this->saveErrorMessage($message);
		}
	}

	/**
	 * @param string[] $command
	 */
	protected function createProcess(array $command): Process {
		return new Process($command);
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
				continue;
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
		$matchesCurrentTarget = fn (array $entry): bool
			=> ($entry['context']['resource'] ?? '') === $this->resource
			&& ($entry['context']['architecture'] ?? $this->target->architecture()) === $this->target->architecture()
			&& ($entry['context']['distro'] ?? $this->target->distro()) === $this->target->distro();

		if ($pid > 0) {
			$registeredPid = $this->processManager->findRunningPid(
				self::PROCESS_SOURCE,
				fn (array $entry): bool => $entry['pid'] === $pid && $matchesCurrentTarget($entry),
			);

			if ($registeredPid > 0) {
				return $registeredPid;
			}

			$this->processManager->unregister(self::PROCESS_SOURCE, $pid);
			return 0;
		}

		return $this->processManager->findRunningPid(
			self::PROCESS_SOURCE,
			$matchesCurrentTarget,
		);
	}

	public function setResource(string $resource): self {
		if (!in_array($resource, $this->availableResources, true)) {
			throw new InvalidArgumentException(sprintf('Unsupported install resource "%s".', $resource));
		}
		$this->resource = $resource;
		return $this;
	}

	public function install(string $resource, bool $async = false): void {
		match ($resource) {
			'java' => $this->installJava($async),
			'jsignpdf' => $this->installJSignPdf($async),
			'pdftk' => $this->installPdftk($async),
			'cfssl' => $this->installCfssl($async),
			default => throw new InvalidArgumentException(sprintf('Unsupported install resource "%s".', $resource)),
		};
	}

	public function uninstall(string $resource): void {
		match ($resource) {
			'java' => $this->uninstallJava(),
			'jsignpdf' => $this->uninstallJSignPdf(),
			'pdftk' => $this->uninstallPdftk(),
			'cfssl' => $this->uninstallCfssl(),
			default => throw new InvalidArgumentException(sprintf('Unsupported install resource "%s".', $resource)),
		};
	}

	public function isDownloadedFilesOk(): bool {
		$this->signSetupService->setDistro($this->getLinuxDistributionToDownloadJava());
		$trustMode = $this->willUseLocalCert
			? SetupTrustMode::Development
			: SetupTrustMode::Production;
		return count($this->signSetupService->verify(
			$this->target->architecture(),
			$this->resource,
			$trustMode,
		)) === 0;
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
			->setArchitecture($this->target->architecture())
			->setResource($this->resource)
			->writeAppSignature();
	}

	public function installJava(?bool $async = false): void {
		$signatureEngine = $this->appConfig->getValueString(Application::APP_ID, 'signature_engine', 'JSignPdf');
		if ($signatureEngine !== 'JSignPdf') {
			return;
		}
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
		if ($this->target->architecture() === 'x86_64') {
			$compressedFileName = 'OpenJDK21U-jre_x64_' . $linuxDistribution . '_hotspot_' . $slugfyVersionNumber . '.tar.gz';
			$url = 'https://github.com/adoptium/temurin21-binaries/releases/download/jdk-' . self::JAVA_URL_PATH_NAME . '/' . $compressedFileName;
		} elseif ($this->target->architecture() === 'aarch64') {
			$compressedFileName = 'OpenJDK21U-jre_aarch64_' . $linuxDistribution . '_hotspot_' . $slugfyVersionNumber . '.tar.gz';
			$url = 'https://github.com/adoptium/temurin21-binaries/releases/download/jdk-' . self::JAVA_URL_PATH_NAME . '/' . $compressedFileName;
		}
		$folder = $this->getFolder($this->resource, needToBeEmpty: true);
		try {
			$compressedFile = $folder->getFile($compressedFileName);
		} catch (NotFoundException) {
			$compressedFile = $folder->newFile($compressedFileName);
		}

		$compressedInternalFileName = $this->getInternalPathOfFile($compressedFile);
		$dependencyName = 'java ' . $this->target->architecture() . ' ' . $linuxDistribution;
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
		$this->target = $this->target->withDistro($distro);
	}

	public function getLinuxDistributionToDownloadJava(): string {
		return $this->target->distro();
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
		$signatureEngine = $this->appConfig->getValueString(Application::APP_ID, 'signature_engine', 'JSignPdf');
		if ($signatureEngine !== 'JSignPdf') {
			return;
		}

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
			$fullPath = $this->appConfig->getValueString(Application::APP_ID, 'jsignpdf_path');
			if (!$fullPath) {
				$folder = $this->getFolder($this->resource);
				$fullPath = JSignPdfRelease::installPath($this->getInternalPathOfFolder($folder));
				$this->appConfig->setValueString(Application::APP_ID, 'jsignpdf_path', $fullPath);
			}
			$this->saveJsignPdfHome();
			if (str_contains($fullPath, InstallService::JSIGNPDF_VERSION) && is_dir($fullPath)) {
				return;
			}
		}
		$folder = $this->getFolder($this->resource, needToBeEmpty: true);
		$compressedFileName = JSignPdfRelease::archiveName();
		try {
			$compressedFile = $folder->getFile($compressedFileName);
		} catch (\Throwable) {
			$compressedFile = $folder->newFile($compressedFileName);
		}
		$compressedInternalFileName = $this->getInternalPathOfFile($compressedFile);
		$hash = $this->getHash($compressedFileName, JSignPdfRelease::checksumUrl());
		$this->download(JSignPdfRelease::downloadUrl(), 'JSignPdf', $compressedInternalFileName, $hash, 'sha256');

		$extractDir = $this->getInternalPathOfFolder($folder);
		$zip = new ZIP($extractDir . '/' . $compressedFileName);
		$zip->extract($extractDir);
		unlink($extractDir . '/' . $compressedFileName);
		$this->appConfig->setValueString(Application::APP_ID, 'jsignpdf_path', JSignPdfRelease::installPath($extractDir));
		$this->appConfig->deleteKey(Application::APP_ID, 'jsignpdf_jar_path');
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
		if ($home
			&& preg_match('/libresign\/jsignpdf_home/', $home)
			&& is_dir($home)
		) {
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
		$jsignpdfPath = $this->appConfig->getValueString(Application::APP_ID, 'jsignpdf_path')
			?: $this->appConfig->getValueString(Application::APP_ID, 'jsignpdf_jar_path');
		if (!$jsignpdfPath) {
			return;
		}
		$this->setResource('jsignpdf');
		$folder = $this->getFolder($this->resource);
		try {
			$folder->delete();
		} catch (NotFoundException) {
		}
		$this->appConfig->deleteKey(Application::APP_ID, 'jsignpdf_path');
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
		$folder = $this->getFolder($this->resource, needToBeEmpty: true);
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
		if ($this->target->architecture() === 'x86_64') {
			$this->installCfsslByArchitecture('amd64');
		} elseif ($this->target->architecture() === 'aarch64') {
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
		$folder = $this->getFolder($this->resource, needToBeEmpty: true);
		$file = 'cfssl_' . self::CFSSL_VERSION . '_linux_' . $architecture;
		$baseUrl = 'https://github.com/cloudflare/cfssl/releases/download/v' . self::CFSSL_VERSION . '/';
		$checksumUrl = 'https://github.com/cloudflare/cfssl/releases/download/v' . self::CFSSL_VERSION . '/cfssl_' . self::CFSSL_VERSION . '_checksums.txt';
		$hash = $this->getHash($file, $checksumUrl);

		$fullPath = $this->getInternalPathOfFile($folder->newFile('cfssl'));

		$dependencyName = 'cfssl ' . $architecture;
		$this->download($baseUrl . $file, $dependencyName, $fullPath, $hash, 'sha256');

		if (!@chmod($fullPath, 0700) && !is_executable($fullPath)) {
			throw new LibresignException('Unable to make CFSSL executable at ' . $fullPath);
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
		} catch (\Throwable $e) {
			throw new LibresignException(
				'Failure on download ' . $dependencyName . " try again.\n" . $e->getMessage(),
				previous: $e,
			);
		}
		if (!file_exists($path)) {
			throw new LibresignException('Failure on download ' . $dependencyName . ', empty file, try again.');
		}
		if ($hash !== '' && hash_file($hash_algo, $path) !== $hash) {
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
		} catch (\Throwable $e) {
			$this->logger->error('Failure on download ' . $dependencyName, [
				'exception' => $e,
				'url' => $url,
			]);
			throw new LibresignException(
				'Failure on download ' . $dependencyName . " try again.\n" . $e->getMessage(),
				previous: $e,
			);
		} finally {
			$progressBar->finish();
			$this->output->writeln('');
		}

		if (!file_exists($path)) {
			$message = 'Failure on download ' . $dependencyName . ', empty file, try again.';
			$this->logger->error($message);
			throw new LibresignException($message);
		}

		if ($hash !== '' && hash_file($hash_algo, $path) !== $hash) {
			$message = 'Failure on download ' . $dependencyName . ' try again. Invalid ' . $hash_algo . '.';
			$this->logger->error($message);
			throw new LibresignException($message);
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

	private function populateNamesWithInstanceId(array $names, string $engineName): array {
		$caId = $this->caIdentifierService->generateCaId($engineName);

		if (empty($names['OU'])) {
			$names['OU']['value'] = [$caId];
			return $names;
		}

		if (!isset($names['OU']['value'])) {
			$names['OU']['value'] = [$caId];
			return $names;
		}

		if (!is_array($names['OU']['value'])) {
			$names['OU']['value'] = [$names['OU']['value']];
		}

		$names['OU']['value'] = array_filter(
			$names['OU']['value'],
			fn ($value) => !str_starts_with((string)$value, 'libresign-ca-id:')
		);

		$names['OU']['value'][] = $caId;

		return $names;
	}

	/**
	 * @todo Use an custom array for engine options
	 */
	public function generate(
		string $commonName,
		string $engineName = '',
		array $names = [],
		array $properties = [],
	): void {
		$names = $this->populateNamesWithInstanceId($names, $engineName);
		$rootCert = [
			'commonName' => $commonName,
			'names' => $names
		];
		$engine = $this->certificateEngineFactory->getEngine($engineName, $rootCert);

		if ($engine instanceof CfsslHandler) {
			/** @var CfsslHandler $engine */
			$engine->setCfsslUri($properties['cfsslUri']);
		}

		$engine->setConfigPath($properties['configPath'] ?? '');

		/** @var IEngineHandler $engine */
		$engine->generateRootCert(
			$commonName,
			$names
		);

		$this->appConfig->setValueArray(Application::APP_ID, 'rootCert', $rootCert);
		/** @var AEngineHandler $engine */
		if ($engine instanceof CfsslHandler) {
			$this->appConfig->setValueString(Application::APP_ID, 'certificate_engine', 'cfssl');
		} else {
			$this->appConfig->setValueString(Application::APP_ID, 'certificate_engine', 'openssl');
		}
	}
}
