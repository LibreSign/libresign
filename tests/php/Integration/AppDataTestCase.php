<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration;

use donatj\MockWebServer\MockWebServer;
use donatj\MockWebServer\Response as MockWebServerResponse;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Service\Install\InstallTarget;
use OCA\Libresign\Service\RequestSignatureService;
use OCP\IConfig;

class AppDataTestCase extends TestCase {
	private const TEST_DIR_MODE = 0750;
	private const TEST_FILE_MODE = 0640;

	protected static MockWebServer $server;
	private static bool $preservedOriginalAppData = false;
	private static bool $hadOriginalLibresignAppData = false;
	private static string $libresignAppDataPath = '';
	private static string $libresignAppDataCachePath = '';
	private static string $originalLibresignAppDataBackupPath = '';
	private RequestSignatureService $requestSignatureService;
	private SignRequestMapper $signRequestMapper;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		self::preserveOriginalLibresignAppData();
		self::$server = new MockWebServer();
		self::$server->start();
	}

	public function setUp(): void {
		parent::setUp();
		$this->mockConfig(['dav' => ['enableDefaultContact' => false]]);
		$this->ensureDavDefaultContactFixture();
		$this->getBinariesFromCache();
	}

	public function tearDown(): void {
		$this->backupBinaries();
		parent::tearDown();
	}

	public static function tearDownAfterClass(): void {
		try {
			parent::tearDownAfterClass();
		} catch (\Throwable) {
		} finally {
			self::restoreCachedLibresignAppData();
		}
	}

	private static function preserveOriginalLibresignAppData(): void {
		if (self::$preservedOriginalAppData) {
			return;
		}
		self::$preservedOriginalAppData = true;
		register_shutdown_function(static function (): void {
			self::restoreOriginalLibresignAppData();
		});
		self::$libresignAppDataPath = self::getFullLiresignAppFolder(false);
		if (self::$libresignAppDataPath === '') {
			self::$libresignAppDataPath = self::buildLibresignAppFolderPath();
		}
		self::$libresignAppDataCachePath = self::buildLibresignAppDataCachePath(self::$libresignAppDataPath);
		self::$originalLibresignAppDataBackupPath = self::$libresignAppDataCachePath !== ''
			? self::$libresignAppDataCachePath . '__original'
			: '';
		self::$hadOriginalLibresignAppData = self::directoryHasContents(self::$libresignAppDataPath);
		if (!self::$hadOriginalLibresignAppData || self::$originalLibresignAppDataBackupPath === '') {
			return;
		}
		self::removeDirectoryRecursively(self::$originalLibresignAppDataBackupPath);
		self::recursiveCopy(self::$libresignAppDataPath, self::$originalLibresignAppDataBackupPath);
	}

	private static function restoreOriginalLibresignAppData(): void {
		if (!self::$preservedOriginalAppData) {
			return;
		}
		$appPath = self::$libresignAppDataPath;
		if ($appPath !== '') {
			self::removeDirectoryRecursively($appPath);
		}
		if (!self::$hadOriginalLibresignAppData) {
			return;
		}
		$backupPath = self::$originalLibresignAppDataBackupPath;
		if ($backupPath === '' || !self::directoryHasContents($backupPath)) {
			return;
		}
		self::recursiveCopy($backupPath, $appPath);
	}

	private static function buildLibresignAppDataCachePath(string $appPath): string {
		$cachePath = preg_replace(
			'/\/.*\/appdata_[a-z0-9]*/',
			(string)\OCP\Server::get(\OCP\ITempManager::class)->getTempBaseDir(),
			$appPath,
		);
		return is_string($cachePath) ? $cachePath : '';
	}

	private static function restoreCachedLibresignAppData(): void {
		if (self::$libresignAppDataPath === '' || self::$libresignAppDataCachePath === '') {
			return;
		}
		if (!self::directoryHasContents(self::$libresignAppDataCachePath)) {
			return;
		}
		self::removeDirectoryRecursively(self::$libresignAppDataPath);
		self::recursiveCopy(self::$libresignAppDataCachePath, self::$libresignAppDataPath);
	}

	private static function directoryHasContents(string $path): bool {
		if ($path === '' || !is_dir($path)) {
			return false;
		}
		$entries = scandir($path);
		return is_array($entries) && count($entries) > 2;
	}

	private function ensureDavDefaultContactFixture(): void {
		$dir = self::getDataDirectoryPath() . '/appdata_' . self::getInstanceId() . '/dav/defaultContact';
		if (!is_dir($dir)) {
			mkdir($dir, self::TEST_DIR_MODE, true);
		}
		$file = $dir . '/defaultContact.vcf';
		if (!file_exists($file)) {
			file_put_contents($file, "BEGIN:VCARD\nVERSION:3.0\nFN:Default Contact\nEND:VCARD\n");
			@chmod($file, self::TEST_FILE_MODE);
		}
	}

	private function getBinariesFromCache(): void {
		$appPath = self::getFullLiresignAppFolder();
		$cachePath = self::$libresignAppDataCachePath !== ''
			? self::$libresignAppDataCachePath
			: self::buildLibresignAppDataCachePath($appPath);
		if (!is_dir($cachePath)) {
			return;
		}
		if (!is_dir($appPath)) {
			mkdir($appPath, self::TEST_DIR_MODE, true);
		}
		foreach (InstallTarget::SUPPORTED_ARCHITECTURES as $architecture) {
			self::recursiveCopy(
				$cachePath . DIRECTORY_SEPARATOR . $architecture,
				$appPath . DIRECTORY_SEPARATOR . $architecture,
			);
		}
	}

	private static function buildLibresignAppFolderPath(): string {
		return self::getDataDirectoryPath() . '/appdata_' . self::getInstanceId() . '/libresign';
	}

	private static function getDataDirectoryPath(): string {
		return rtrim(
			\OCP\Server::get(IConfig::class)->getSystemValueString('datadirectory', \OC::$SERVERROOT . '/data'),
			'/',
		);
	}

	private static function getFullLiresignAppFolder(bool $createIfMissing = true): string {
		$path = self::buildLibresignAppFolderPath();
		if ($createIfMissing && !is_dir($path)) {
			mkdir($path, self::TEST_DIR_MODE, true);
			$user = fileowner(__FILE__);
			chown($path, $user);
			@chgrp($path, $user);
		}
		if (is_dir($path)) {
			$resolvedPath = realpath($path);
			if (is_string($resolvedPath) && $resolvedPath !== '') {
				return $resolvedPath;
			}
		}
		return $createIfMissing ? $path : '';
	}

	private static function getInstanceId(): string {
		$instanceId = \OCP\Server::get(IConfig::class)->getSystemValueString('instanceid', '');
		if ($instanceId === '') {
			throw new \RuntimeException('Missing Nextcloud instanceid from system config.');
		}
		return $instanceId;
	}

	private function backupBinaries(): void {
		$appPath = self::getFullLiresignAppFolder();
		if (!is_readable($appPath)) {
			return;
		}
		$cachePath = self::$libresignAppDataCachePath !== ''
			? self::$libresignAppDataCachePath
			: self::buildLibresignAppDataCachePath($appPath);
		if (!is_dir($cachePath)) {
			mkdir($cachePath, self::TEST_DIR_MODE, true);
		}
		foreach (InstallTarget::SUPPORTED_ARCHITECTURES as $architecture) {
			self::recursiveCopy(
				$appPath . DIRECTORY_SEPARATOR . $architecture,
				$cachePath . DIRECTORY_SEPARATOR . $architecture,
			);
		}
	}

	private static function normalizeCopiedFileMode(int $sourcePerms): int {
		return self::TEST_FILE_MODE | ($sourcePerms & 0111);
	}

	private static function recursiveCopy(string $source, string $dest): void {
		if (!is_dir($source)) {
			return;
		}
		if (!is_dir($dest)) {
			@mkdir($dest, self::TEST_DIR_MODE, true);
			if (!is_dir($dest)) {
				return;
			}
		}
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::SELF_FIRST,
			\RecursiveIteratorIterator::CATCH_GET_CHILD,
		);
		foreach ($iterator as $item) {
			$sourcePath = $item->getPathname();
			if (!file_exists($sourcePath)) {
				continue;
			}
			$subIterator = $iterator->getSubIterator();
			if (!$subIterator instanceof \RecursiveDirectoryIterator) {
				continue;
			}
			$newDest = $dest . DIRECTORY_SEPARATOR . $subIterator->getSubPathname();
			if (!file_exists($newDest)) {
				if ($item->isDir()) {
					@mkdir($newDest, self::TEST_DIR_MODE, true);
				} elseif (is_file($sourcePath)) {
					$newDestFolder = dirname($newDest);
					if (!is_dir($newDestFolder)) {
						@mkdir($newDestFolder, self::TEST_DIR_MODE, true);
					}
					if (is_dir($newDestFolder)) {
						@copy($sourcePath, $newDest);
					}
				}
			}
			$sourcePerms = @fileperms($sourcePath);
			$destPerms = @fileperms($newDest);
			$expectedMode = $item->isDir()
				? self::TEST_DIR_MODE
				: (is_int($sourcePerms) ? self::normalizeCopiedFileMode($sourcePerms) : self::TEST_FILE_MODE);
			if (!is_int($destPerms) || (($destPerms & 0777) !== $expectedMode)) {
				@chmod($newDest, $expectedMode);
			}
		}
	}

	private static function removeDirectoryRecursively(string $path): void {
		if ($path === '' || !file_exists($path)) {
			return;
		}
		if (is_file($path) || is_link($path)) {
			@unlink($path);
			return;
		}
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST,
			\RecursiveIteratorIterator::CATCH_GET_CHILD,
		);
		foreach ($iterator as $item) {
			$pathname = $item->getPathname();
			if ($item->isDir() && !$item->isLink()) {
				@rmdir($pathname);
				continue;
			}
			@unlink($pathname);
		}
		@rmdir($path);
	}

	public function requestSignFile(array $data): File {
		self::$server->setResponseOfPath('/api/v1/cfssl/newcert', new MockWebServerResponse(
			file_get_contents(__DIR__ . '/../fixtures/cfssl/newcert-with-success.json'),
		));
		$appConfig = static::getMockAppConfig();
		$appConfig->setValueBool(Application::APP_ID, 'notifyUnsignedUser', false);
		$appConfig->setValueString(Application::APP_ID, 'commonName', 'CommonName');
		$appConfig->setValueString(Application::APP_ID, 'country', 'Brazil');
		$appConfig->setValueString(Application::APP_ID, 'organization', 'Organization');
		$appConfig->setValueString(Application::APP_ID, 'organizationalUnit', 'organizationalUnit');
		$appConfig->setValueString(Application::APP_ID, 'cfsslUri', self::$server->getServerRoot() . '/api/v1/cfssl/');
		if (!isset($data['settings'])) {
			$data['settings']['separator'] = '_';
			$data['settings']['folderPatterns'][] = ['name' => 'date', 'setting' => 'Y-m-d\\TH:i:s.u'];
			$data['settings']['folderPatterns'][] = ['name' => 'name'];
			$data['settings']['folderPatterns'][] = ['name' => 'userId'];
		}
		return $this->getRequestSignatureService()->save($data);
	}

	private function getRequestSignatureService(): RequestSignatureService {
		if (!isset($this->requestSignatureService)) {
			$this->requestSignatureService = \OCP\Server::get(RequestSignatureService::class);
		}
		return $this->requestSignatureService;
	}

	public function getSignersFromFileId(int $fileId): array {
		return $this->getSignRequestMapper()->getByFileId($fileId);
	}

	private function getSignRequestMapper(): SignRequestMapper {
		if (!isset($this->signRequestMapper)) {
			$this->signRequestMapper = \OCP\Server::get(SignRequestMapper::class);
		}
		return $this->signRequestMapper;
	}
}
