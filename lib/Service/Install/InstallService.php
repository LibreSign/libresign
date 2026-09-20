<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Install;

use InvalidArgumentException;
use OC\Archive\TAR;
use OC\Archive\ZIP;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\AEngineHandler;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\CertificateEngine\CfsslHandler;
use OCA\Libresign\Handler\CertificateEngine\IEngineHandler;
use OCA\Libresign\Service\CaIdentifierService;
use OCP\Files\NotFoundException;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class InstallService {

	public const JAVA_VERSION = 'openjdk version "21.0.8" 2025-07-15 LTS';
	public const string JAVA_URL_PATH_NAME = '21.0.8+9';
	public const PDFTK_VERSION = '3.3.3'; /** @todo When update, verify the hash **/
	private const string PDFTK_HASH = '59a28bed53b428595d165d52988bf4cf';
	public const JSIGNPDF_VERSION = JSignPdfRelease::VERSION;
	public const CFSSL_VERSION = '1.6.5';

	private ?OutputInterface $output = null;
	private string $resource = '';
	private array $availableResources = [
		'java',
		'jsignpdf',
		'pdftk',
		'cfssl',
	];
	private InstallTarget $target;
	private SetupTrustMode $trustMode = SetupTrustMode::Production;

	public function __construct(
		private InstallProgressStore $progressStore,
		private DependencyDownloader $dependencyDownloader,
		private CertificateEngineFactory $certificateEngineFactory,
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
		private SignSetupService $signSetupService,
		private DependencyStorage $dependencyStorage,
		private CaIdentifierService $caIdentifierService,
		private InstallProcessManager $installProcessManager,
	) {
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


	private function runAsync(): void {
		$pid = $this->installProcessManager->start($this->resource, $this->target);
		if ($pid !== null) {
			$this->progressStore->set($this->target, $this->resource, ['pid' => $pid]);
			return;
		}

		$message = 'LibreSign could not start the background installer for ' . $this->resource . '. '
			. 'Check the Nextcloud server log and run the corresponding occ libresign:install command manually.';
		$this->logger->error('Unable to start background dependency installer', [
			'resource' => $this->resource,
			'architecture' => $this->target->architecture(),
			'distro' => $this->target->distro(),
		]);
		$this->saveErrorMessage($message);
	}

	private function progressToDatabase(string $resource, int $downloadSize, int $downloaded): void {
		$data = $this->progressStore->get($this->target, $resource);
		$data['download_size'] = $downloadSize;
		$data['downloaded'] = $downloaded;
		$this->progressStore->set($this->target, $resource, $data);
	}

	private function getProgressData(string $resource): array {
		return $this->progressStore->get($this->target, $resource);
	}

	private function removeDownloadProgress(string $resource): void {
		$this->progressStore->remove($this->target, $resource);
	}

	public function getAvailableResources(): array {
		return $this->availableResources;
	}

	public function getTotalSize(): array {
		$return = [];
		foreach ($this->availableResources as $resource) {
			$progressData = $this->getProgressData($resource);
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
		if ($this->resource === '') {
			return;
		}
		$data = $this->getProgressData($this->resource);
		$data['error'] = $message;
		$this->progressStore->set($this->target, $this->resource, $data);
	}

	public function getErrorMessages(): array {
		$return = [];
		foreach ($this->availableResources as $resource) {
			$progressData = $this->getProgressData($resource);
			if (array_key_exists('error', $progressData)) {
				$return[] = $progressData['error'];
				$this->removeDownloadProgress($resource);
			}
		}
		return $return;
	}

	public function isDownloadWip(): bool {
		foreach ($this->availableResources as $resource) {
			$progressData = $this->getProgressData($resource);
			if (empty($progressData)) {
				continue;
			}

			$pid = $progressData['pid'] ?? 0;
			if ($this->installProcessManager->findRunningPid($resource, $this->target, $pid) === 0) {
				if (!array_key_exists('error', $progressData)) {
					$this->removeDownloadProgress($resource);
				}
				continue;
			}
			return true;
		}
		return false;
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
		return count($this->signSetupService->verify(
			$this->target->architecture(),
			$this->resource,
			$this->trustMode,
		)) === 0;
	}

	public function useDevelopmentTrust(): void {
		$this->trustMode = SetupTrustMode::Development;
	}

	private function writeAppSignature(): void {
		if ($this->trustMode !== SetupTrustMode::Development) {
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
			throw new LibresignException(sprintf('LibreSign managed dependencies are supported on Linux. Detected operating system family: %s.', PHP_OS_FAMILY));
		}

		if ($this->isDownloadedFilesOk()) {
			// The binaries files could exists but not saved at database
			$javaPath = $this->appConfig->getValueString(Application::APP_ID, 'java_path');
			if (!$javaPath) {
				$linuxDistribution = $this->getLinuxDistributionToDownloadJava();
				$folder = $this->dependencyStorage->resourceFolder($this->target, $linuxDistribution . '/' . $this->resource);
				$extractDir = $this->dependencyStorage->pathOfFolder($folder);
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
		$folder = $this->dependencyStorage->resourceFolder($this->target, $this->resource, empty: true);
		try {
			$compressedFile = $folder->getFile($compressedFileName);
		} catch (NotFoundException) {
			$compressedFile = $folder->newFile($compressedFileName);
		}

		$compressedInternalFileName = $this->dependencyStorage->pathOfFile($compressedFile);
		$dependencyName = 'java ' . $this->target->architecture() . ' ' . $linuxDistribution;
		$checksumUrl = $url . '.sha256.txt';
		$hash = $this->dependencyDownloader->fetchChecksum($compressedFileName, $checksumUrl);
		$this->download($url, $dependencyName, $compressedInternalFileName, $hash, 'sha256');

		$extractor = new TAR($compressedInternalFileName);
		$extractDir = $this->dependencyStorage->pathOfFolder($folder);
		$extractor->extract($extractDir);
		unlink($compressedInternalFileName);
		$this->appConfig->setValueString(Application::APP_ID, 'java_path', $extractDir . '/jdk-' . self::JAVA_URL_PATH_NAME . '-jre/bin/java');
		$this->writeAppSignature();
		$this->removeDownloadProgress($this->resource);
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
		$folder = $this->dependencyStorage->resourceFolder($this->target, $this->resource);
		try {
			$folder->delete();
		} catch (NotFoundException) {
		}
		$this->appConfig->deleteKey(Application::APP_ID, 'java_path');
	}

	public function installJSignPdf(?bool $async = false): void {
		$this->setResource('jsignpdf');
		$signatureEngine = $this->appConfig->getValueString(Application::APP_ID, 'signature_engine', 'JSignPdf');
		if ($signatureEngine !== 'JSignPdf') {
			return;
		}

		if (!extension_loaded('zip')) {
			throw new LibresignException('The PHP ZIP extension is required to install JSignPdf. Enable it for the PHP runtime used by Nextcloud and retry.');
		}
		if ($async) {
			$this->runAsync();
			return;
		}

		if ($this->isDownloadedFilesOk()) {
			// The binaries files could exists but not saved at database
			$fullPath = $this->appConfig->getValueString(Application::APP_ID, 'jsignpdf_path');
			if (!$fullPath) {
				$folder = $this->dependencyStorage->resourceFolder($this->target, $this->resource);
				$fullPath = JSignPdfRelease::installPath($this->dependencyStorage->pathOfFolder($folder));
				$this->appConfig->setValueString(Application::APP_ID, 'jsignpdf_path', $fullPath);
			}
			$this->saveJsignPdfHome();
			if (str_contains($fullPath, InstallService::JSIGNPDF_VERSION) && is_dir($fullPath)) {
				return;
			}
		}
		$folder = $this->dependencyStorage->resourceFolder($this->target, $this->resource, empty: true);
		$compressedFileName = JSignPdfRelease::archiveName();
		try {
			$compressedFile = $folder->getFile($compressedFileName);
		} catch (NotFoundException) {
			$compressedFile = $folder->newFile($compressedFileName);
		}
		$compressedInternalFileName = $this->dependencyStorage->pathOfFile($compressedFile);
		$hash = $this->dependencyDownloader->fetchChecksum($compressedFileName, JSignPdfRelease::checksumUrl());
		$this->download(JSignPdfRelease::downloadUrl(), 'JSignPdf', $compressedInternalFileName, $hash, 'sha256');

		$extractDir = $this->dependencyStorage->pathOfFolder($folder);
		$zip = new ZIP($extractDir . '/' . $compressedFileName);
		$zip->extract($extractDir);
		unlink($extractDir . '/' . $compressedFileName);
		$this->appConfig->setValueString(Application::APP_ID, 'jsignpdf_path', JSignPdfRelease::installPath($extractDir));
		$this->appConfig->deleteKey(Application::APP_ID, 'jsignpdf_jar_path');
		$this->saveJsignPdfHome();
		$this->writeAppSignature();

		$this->removeDownloadProgress($this->resource);
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
		$libresignFolder = $this->dependencyStorage->rootFolder();
		$homeFolder = $libresignFolder->newFolder('jsignpdf_home');
		$homeFolder->newFile('.JSignPdf', '');
		$configFolder = $this->dependencyStorage->childFolder($homeFolder, 'conf');
		$configFolder->newFile('conf.properties', '');
		$this->appConfig->setValueString(Application::APP_ID, 'jsignpdf_home', $this->dependencyStorage->pathOfFolder($homeFolder));
	}

	public function uninstallJSignPdf(): void {
		$jsignpdfPath = $this->appConfig->getValueString(Application::APP_ID, 'jsignpdf_path')
			?: $this->appConfig->getValueString(Application::APP_ID, 'jsignpdf_jar_path');
		if (!$jsignpdfPath) {
			return;
		}
		$this->setResource('jsignpdf');
		$folder = $this->dependencyStorage->resourceFolder($this->target, $this->resource);
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
				$folder = $this->dependencyStorage->resourceFolder($this->target, $this->resource);
				$file = $folder->getFile('pdftk.jar');
				$fullPath = $this->dependencyStorage->pathOfFile($file);
				$this->appConfig->setValueString(Application::APP_ID, 'pdftk_path', $fullPath);
			}
			return;
		}
		$folder = $this->dependencyStorage->resourceFolder($this->target, $this->resource, empty: true);
		try {
			$file = $folder->getFile('pdftk.jar');
		} catch (NotFoundException) {
			$file = $folder->newFile('pdftk.jar');
		}
		$fullPath = $this->dependencyStorage->pathOfFile($file);
		$url = 'https://gitlab.com/api/v4/projects/5024297/packages/generic/pdftk-java/v' . self::PDFTK_VERSION . '/pdftk-all.jar';

		$this->download($url, 'pdftk', $fullPath, self::PDFTK_HASH);
		$this->appConfig->setValueString(Application::APP_ID, 'pdftk_path', $fullPath);
		$this->writeAppSignature();
		$this->removeDownloadProgress($this->resource);
	}

	public function uninstallPdftk(): void {
		$jsignpdJarPath = $this->appConfig->getValueString(Application::APP_ID, 'pdftk_path');
		if (!$jsignpdJarPath) {
			return;
		}
		$this->setResource('pdftk');
		$folder = $this->dependencyStorage->resourceFolder($this->target, $this->resource);
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
			throw new LibresignException(sprintf('LibreSign managed dependencies are supported on Linux. Detected operating system family: %s.', PHP_OS_FAMILY));
		}
		if ($this->target->architecture() === 'x86_64') {
			$this->installCfsslByArchitecture('amd64');
		} elseif ($this->target->architecture() === 'aarch64') {
			$this->installCfsslByArchitecture('arm64');
		} else {
			throw new InvalidArgumentException('CFSSL is available only for x86_64/amd64 and aarch64/arm64 architectures.');
		}
		$this->removeDownloadProgress($this->resource);
	}

	private function installCfsslByArchitecture(string $architecture): void {
		if ($this->isDownloadedFilesOk()) {
			// The binaries files could exists but not saved at database
			if (!$this->isCfsslBinInstalled()) {
				$folder = $this->dependencyStorage->resourceFolder($this->target, $this->resource);
				$cfsslBinPath = $this->dependencyStorage->pathOfFolder($folder) . '/cfssl';
				$this->appConfig->setValueString(Application::APP_ID, 'cfssl_bin', $cfsslBinPath);
			}
			return;
		}
		$folder = $this->dependencyStorage->resourceFolder($this->target, $this->resource, empty: true);
		$file = 'cfssl_' . self::CFSSL_VERSION . '_linux_' . $architecture;
		$baseUrl = 'https://github.com/cloudflare/cfssl/releases/download/v' . self::CFSSL_VERSION . '/';
		$checksumUrl = 'https://github.com/cloudflare/cfssl/releases/download/v' . self::CFSSL_VERSION . '/cfssl_' . self::CFSSL_VERSION . '_checksums.txt';
		$hash = $this->dependencyDownloader->fetchChecksum($file, $checksumUrl);

		$fullPath = $this->dependencyStorage->pathOfFile($folder->newFile('cfssl'));

		$dependencyName = 'cfssl ' . $architecture;
		$this->download($baseUrl . $file, $dependencyName, $fullPath, $hash, 'sha256');

		if (!@chmod($fullPath, 0700) && !is_executable($fullPath)) {
			throw new LibresignException('CFSSL was downloaded but LibreSign could not make it executable. Check filesystem permissions and mount options for the Nextcloud app data directory, then retry.');
		}
		$cfsslBinPath = $this->dependencyStorage->pathOfFolder($folder) . '/cfssl';
		$this->appConfig->setValueString(Application::APP_ID, 'cfssl_bin', $cfsslBinPath);
		$this->writeAppSignature();
	}

	public function uninstallCfssl(): void {
		$cfsslPath = $this->appConfig->getValueString(Application::APP_ID, 'cfssl_bin');
		if (!$cfsslPath) {
			return;
		}
		$this->setResource('cfssl');
		$folder = $this->dependencyStorage->resourceFolder($this->target, $this->resource);
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

	protected function download(
		string $url,
		string $dependencyName,
		string $path,
		?string $hash = '',
		?string $hash_algo = 'md5',
	): void {
		$hash ??= '';
		$hash_algo ??= 'md5';

		if (php_sapi_name() === 'cli' && $this->output instanceof OutputInterface) {
			$progressBar = new ProgressBar($this->output);
			$this->output->writeln('Downloading ' . $dependencyName . '...');
			$progressBar->start();
			try {
				$this->dependencyDownloader->download(
					$url,
					$dependencyName,
					$path,
					$hash,
					$hash_algo,
					function (int $downloadSize, int $downloaded) use ($progressBar): void {
						$progressBar->setMaxSteps($downloadSize);
						$progressBar->setProgress($downloaded);
						$this->progressToDatabase($this->resource, $downloadSize, $downloaded);
					},
				);
			} finally {
				$progressBar->finish();
				$this->output->writeln('');
			}
			return;
		}

		$this->dependencyDownloader->download(
			$url,
			$dependencyName,
			$path,
			$hash,
			$hash_algo,
			fn (int $downloadSize, int $downloaded): void
				=> $this->progressToDatabase($this->resource, $downloadSize, $downloaded),
		);
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
