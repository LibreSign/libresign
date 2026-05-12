<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Test;

/**
 * Overwrite opendir in the Test namespace.
 */

function testCaseWillIgnore(string $node): bool {
	$libresignPath = current(glob(\OC::$SERVERROOT . '/data/appdata_*/libresign'));
	$knownEntries = [
		$libresignPath . '/aarch',
		$libresignPath . '/arm64',
		$libresignPath . '/cfssl_config',
		$libresignPath . '/x86_64',
	];
	foreach ($knownEntries as $ignored) {
		if (str_starts_with($node, $ignored)) {
			return true;
		}
	}
	return false;
}

function rmdir($dir) {
	if (testCaseWillIgnore($dir)) {
		return false;
	}
	return \rmdir($dir);
}

function unlink($file) {
	if (testCaseWillIgnore($file)) {
		return false;
	}
	return \unlink($file);
}

namespace OCA\Libresign\Tests\Unit;

use donatj\MockWebServer\MockWebServer;
use donatj\MockWebServer\Response as MockWebServerResponse;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Service\RequestSignatureService;
use OCP\IAppConfig;
use OCP\IConfig;

class TestCase extends \Test\TestCase {
	private const TEST_DIR_MODE = 0750;
	private const TEST_FILE_MODE = 0640;

	protected static MockWebServer $server;
	private RequestSignatureService $requestSignatureService;
	private SignRequestMapper $signRequestMapper;
	private array $users = [];

	public static function getMockAppConfig(): IAppConfig {
		return \OCP\Server::get(IAppConfig::class);
	}

	public static function getMockAppConfigWithReset(): IAppConfig {
		$appConfig = self::getMockAppConfig();
		if (method_exists($appConfig, 'reset')) {
			$appConfig->reset();
		}
		return $appConfig;
	}

	public function mockConfig($config):void {
		$service = \OCP\Server::get(IConfig::class);
		foreach ($config as $app => $keys) {
			foreach ($keys as $key => $value) {
				if (is_array($value) || is_object($value)) {
					$value = json_encode($value);
				}
				$service->setAppValue($app, $key, (string)$value);
			}
		}
	}

	public function haveDependents(): bool {
		$reflector = new \ReflectionClass(static::class);

		$methods = $reflector->getMethods();
		foreach ($methods as $method) {
			$docblock = $reflector->getMethod($method->getName())->getDocComment();
			if (!$docblock) {
				return false;
			}
			if (preg_match('#@depends ' . $this->name() . '\n#s', $docblock)) {
				return true;
			}
		}
		return false;
	}

	public function iDependOnOthers(): bool {
		$reflector = new \ReflectionClass(static::class);
		$docblock = $reflector->getMethod($this->name())->getDocComment();
		if (!$docblock) {
			return false;
		}
		if (preg_match('#@depends #s', $docblock)) {
			return true;
		}
		return false;
	}

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		self::$server = new MockWebServer();
		self::$server->start();
	}

	public function setUp(): void {
		static::getMockAppConfig();
		$this->suppressMailDelivery();
		$this->mockConfig([
			'dav' => [
				'enableDefaultContact' => 'false',
			],
		]);
		$this->ensureDavDefaultContactFixture();
		$this->getBinariesFromCache();
		if ($this->iDependOnOthers() || !$this->IsDatabaseAccessAllowed()) {
			return;
		}
		$this->cleanDatabase();
	}

	private function suppressMailDelivery(): void {
		$mailService = $this->createMock(\OCA\Libresign\Service\MailService::class);
		$mailService->method('notifyUnsignedUser')->willReturnCallback(static function (): void {
		});
		$mailService->method('notifySignDataUpdated')->willReturnCallback(static function (): void {
		});
		$mailService->method('notifySignedUser')->willReturnCallback(static function (): void {
		});
		$mailService->method('notifyCanceledRequest')->willReturnCallback(static function (): void {
		});
		$mailService->method('sendCodeToSign')->willReturnCallback(static function (): void {
		});
		$this->overwriteService(\OCA\Libresign\Service\MailService::class, $mailService);
	}

	private function ensureDavDefaultContactFixture(): void {
		$instanceId = $this->getInstanceId();
		$dir = '../../data/appdata_' . $instanceId . '/dav/defaultContact';
		if (!is_dir($dir)) {
			mkdir($dir, self::TEST_DIR_MODE, true);
		}

		$file = $dir . '/defaultContact.vcf';
		if (!file_exists($file)) {
			file_put_contents($file, "BEGIN:VCARD\nVERSION:3.0\nFN:Default Contact\nEND:VCARD\n");
			@chmod($file, self::TEST_FILE_MODE);
		}
	}

	public function tearDown(): void {
		$this->backupBinaries();
		if ($this->haveDependents() || !$this->IsDatabaseAccessAllowed()) {
			return;
		}
		$this->cleanDatabase();
	}

	public static function tearDownAfterClass(): void {
		try {
			parent::tearDownAfterClass();
		} catch (\Throwable) {
		}
	}

	private function cleanDatabase(): void {
		$db = \OCP\Server::get(\OCP\IDBConnection::class);
		if (!$db) {
			return;
		}
		$this->deleteUsers();

		$delete = $db->getQueryBuilder();
		$delete->delete('libresign_file')->executeStatement();
		$delete->delete('libresign_identify_method')->executeStatement();
		$delete->delete('libresign_sign_request')->executeStatement();
		$delete->delete('libresign_user_element')->executeStatement();
		$delete->delete('libresign_file_element')->executeStatement();
		$delete->delete('libresign_id_docs')->executeStatement();
	}

	/**
	 * Create user
	 */
	public function createAccount(string $username, string $password, string $groupName = 'testGroup'):\OC\User\User {
		$this->users[] = $username;
		$this->mockConfig([
			'core' => [
				'newUser.sendEmail' => 'no'
			]
		]);

		$userManager = \OCP\Server::get(\OCP\IUserManager::class);
		$groupManager = \OCP\Server::get(\OCP\IGroupManager::class);

		$user = $userManager->get($username);
		if (!$user) {
			$user = @$userManager->createUser($username, $password);
		}
		$group = $groupManager->get($groupName);
		if (!$group) {
			$group = $groupManager->createGroup($groupName);
		}

		if ($group && $user) {
			$group->addUser($user);
		}
		return $user;
	}

	public function markUserExists($username): void {
		$this->users[] = $username;
	}

	public function deleteUsers():void {
		foreach ($this->users as $username) {
			$this->deleteUserIfExists($username);
		}
	}

	public function deleteUserIfExists($username): void {
		$user = \OCP\Server::get(\OCP\IUserManager::class)->get($username);
		if ($user) {
			try {
				$user->delete();
			} catch (\Throwable) {
			}
		}
	}

	private function getBinariesFromCache(): void {
		$appPath = $this->getFullLiresignAppFolder();
		if (!$appPath) {
			return;
		}
		$cachePath = preg_replace('/\/.*\/appdata_[a-z0-9]*/', (string)\OCP\Server::get(\OCP\ITempManager::class)->getTempBaseDir(), $appPath);
		if (!file_exists($cachePath)) {
			return;
		}
		if (!is_dir($appPath)) {
			mkdir($appPath, self::TEST_DIR_MODE, true);
		}
		$this->recursiveCopy($cachePath, $appPath);
	}

	private function getFullLiresignAppFolder(): string {
		$path = '../../data/appdata_' . $this->getInstanceId() . '/libresign';
		if (!is_dir($path)) {
			mkdir($path, self::TEST_DIR_MODE, true);
			$user = fileowner(__FILE__);
			chown($path, $user);
			@chgrp($path, $user);
		}
		return realpath($path);
	}

	private function getInstanceId(): string {
		$instanceId = \OCP\Server::get(IConfig::class)->getSystemValueString('instanceid', '');
		if ($instanceId === '') {
			throw new \RuntimeException('Missing Nextcloud instanceid from system config.');
		}
		return $instanceId;
	}

	private function backupBinaries(): void {
		$appPath = $this->getFullLiresignAppFolder();
		if (!is_readable($appPath)) {
			return;
		}
		$isEmpty = count(scandir($appPath)) == 2;
		if ($isEmpty) {
			return;
		}
		$cachePath = preg_replace('/\/.*\/appdata_[a-z0-9]*/', (string)\OCP\Server::get(\OCP\ITempManager::class)->getTempBaseDir(), $appPath);
		if (!file_exists($cachePath)) {
			mkdir($cachePath, self::TEST_DIR_MODE, true);
		}
		$this->recursiveCopy($appPath, $cachePath);
	}

	private function normalizeCopiedFileMode(int $sourcePerms): int {
		$execBits = $sourcePerms & 0111;
		return self::TEST_FILE_MODE | $execBits;
	}

	private function recursiveCopy(string $source, string $dest): void {
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
					if (!is_dir($newDest)) {
						@mkdir($newDest, self::TEST_DIR_MODE, true);
						if (!is_dir($newDest)) {
							continue;
						}
					}
				} elseif (is_file($sourcePath)) {
					$newDestFolder = dirname($newDest);
					if (!is_dir($newDestFolder)) {
						@mkdir($newDestFolder, self::TEST_DIR_MODE, true);
						if (!is_dir($newDestFolder)) {
							continue;
						}
					}
					if (!@copy($sourcePath, $newDest)) {
						continue;
					}
				}
			}
			$sourcePerms = @fileperms($sourcePath);
			$destPerms = @fileperms($newDest);
			if ($item->isDir()) {
				$expectedMode = self::TEST_DIR_MODE;
			} elseif (is_int($sourcePerms)) {
				$expectedMode = $this->normalizeCopiedFileMode($sourcePerms);
			} else {
				$expectedMode = self::TEST_FILE_MODE;
			}
			if (!is_int($destPerms) || (($destPerms & 0777) !== $expectedMode)) {
				@chmod($newDest, $expectedMode);
			}
		}
	}

	public function requestSignFile($data): File {
		self::$server->setResponseOfPath('/api/v1/cfssl/newcert', new MockWebServerResponse(
			file_get_contents(__DIR__ . '/../fixtures/cfssl/newcert-with-success.json')
		));

		$appConfig = static::getMockAppConfig();
		$appConfig->setValueBool(Application::APP_ID, 'notifyUnsignedUser', false);
		$appConfig->setValueString(Application::APP_ID, 'commonName', 'CommonName');
		$appConfig->setValueString(Application::APP_ID, 'country', 'Brazil');
		$appConfig->setValueString(Application::APP_ID, 'organization', 'Organization');
		$appConfig->setValueString(Application::APP_ID, 'organizationalUnit', 'organizationalUnit');
		$appConfig->setValueString(Application::APP_ID, 'cfsslUri', self::$server->getServerRoot() . '/api/v1/cfssl/');

		$mailService = $this->createMock(\OCA\Libresign\Service\MailService::class);
		$mailService->method('notifyUnsignedUser')->willReturnCallback(static function (): void {
		});
		$mailService->method('notifySignDataUpdated')->willReturnCallback(static function (): void {
		});
		$mailService->method('notifySignedUser')->willReturnCallback(static function (): void {
		});
		$mailService->method('notifyCanceledRequest')->willReturnCallback(static function (): void {
		});
		$mailService->method('sendCodeToSign')->willReturnCallback(static function (): void {
		});
		$this->overwriteService(\OCA\Libresign\Service\MailService::class, $mailService);

		if (!isset($data['settings'])) {
			$data['settings']['separator'] = '_';
			$data['settings']['folderPatterns'][] = [
				'name' => 'date',
				'setting' => 'Y-m-d\TH:i:s.u'
			];
			$data['settings']['folderPatterns'][] = [
				'name' => 'name'
			];
			$data['settings']['folderPatterns'][] = [
				'name' => 'userId'
			];
		}
		$file = $this->getRequestSignatureService()->save($data);
		return $file;
	}

	/**
	 * @return \OCA\Libresign\Service\RequestSignatureService
	 */
	private function getRequestSignatureService(): \OCA\Libresign\Service\RequestSignatureService {
		if (!isset($this->requestSignatureService)) {
			$this->requestSignatureService = \OCP\Server::get(\OCA\Libresign\Service\RequestSignatureService::class);
		}
		return $this->requestSignatureService;
	}

	public function getSignersFromFileId(int $fileId): array {
		return $this->getSignRequestMapper()->getByFileId($fileId);
	}

	/**
	 * @return \OCA\Libresign\Db\signRequestMapper
	 */
	private function getSignRequestMapper(): \OCA\Libresign\Db\SignRequestMapper {
		if (!isset($this->signRequestMapper)) {
			$this->signRequestMapper = \OCP\Server::get(\OCA\Libresign\Db\SignRequestMapper::class);
		}
		return $this->signRequestMapper;
	}
}
