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
use OC\SystemConfig;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\signRequestMapper;
use OCA\Libresign\Service\RequestSignatureService;
use OCA\Libresign\Tests\lib\AllConfigOverwrite;
use OCA\Libresign\Tests\lib\AppConfigOverwrite;
use OCA\Libresign\Tests\lib\ConfigOverwrite;
use OCP\IAppConfig;
use OCP\IConfig;

class TestCase extends \Test\TestCase {
	protected static MockWebServer $server;
	private RequestSignatureService $requestSignatureService;
	private signRequestMapper $signRequestMapper;
	private array $users = [];

	public static function getMockAppConfig(): IAppConfig {
		\OC::$server->registerParameter('appName', 'libresign');
		$service = \OCP\Server::get(\OCP\IAppConfig::class);
		if (!$service instanceof AppConfigOverwrite) {
			\OC::$server->registerService(\OCP\IAppConfig::class, fn (): AppConfigOverwrite => new AppConfigOverwrite(
				\OCP\Server::get(\OCP\IDBConnection::class),
				\OCP\Server::get(\Psr\Log\LoggerInterface::class),
				\OCP\Server::get(\OCP\Security\ICrypto::class),
			));
			$service = \OCP\Server::get(\OCP\IAppConfig::class);
		}
		return $service;
	}

	public function mockConfig($config):void {
		$service = \OCP\Server::get(\OCP\IConfig::class);
		if (!$service instanceof AllConfigOverwrite) {
			\OC::$server->registerService(\OCP\IConfig::class, function ():AllConfigOverwrite {
				$configOverwrite = new ConfigOverwrite(\OC::$configDir);
				$systemConfig = new SystemConfig($configOverwrite);
				return new AllConfigOverwrite($systemConfig);
			});
			$service = \OCP\Server::get(\OCP\IConfig::class);
		}
		if (is_subclass_of($service, IConfig::class)) {
			foreach ($config as $app => $keys) {
				foreach ($keys as $key => $value) {
					if (is_array($value) || is_object($value)) {
						$value = json_encode($value);
					}
					$service->setAppValue($app, $key, $value);
				}
			}
			return;
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
		$this->getBinariesFromCache();
		if ($this->iDependOnOthers() || !$this->IsDatabaseAccessAllowed()) {
			return;
		}
		$this->cleanDatabase();
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
		$delete->delete('libresign_sign_request')->executeStatement();
		$delete->delete('libresign_user_element')->executeStatement();
		$delete->delete('libresign_file_element')->executeStatement();
		$delete->delete('libresign_account_file')->executeStatement();
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
			mkdir($appPath, 0777, true);
		}
		$this->recursiveCopy($cachePath, $appPath);
	}

	private function getFullLiresignAppFolder(): string {
		$path = '../../data/appdata_' . \OC_Util::getInstanceId() . '/libresign';
		if (!is_dir($path)) {
			mkdir($path, 0777, true);
			$user = fileowner(__FILE__);
			chown($path, $user);
			@chgrp($path, $user);
		}
		return realpath($path);
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
			mkdir($cachePath);
		}
		$this->recursiveCopy($appPath, $cachePath);
	}

	private function recursiveCopy(string $source, string $dest): void {
		foreach (
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::SELF_FIRST) as $item
		) {
			$newDest = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathname();
			if (!file_exists($newDest)) {
				if ($item->isDir()) {
					mkdir($newDest);
				} else {
					copy($item->getPathname(), $newDest);
				}
			}
			if (fileperms($item->getPathname()) !== fileperms($newDest)) {
				chmod($newDest, fileperms($item->getPathname()));
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
