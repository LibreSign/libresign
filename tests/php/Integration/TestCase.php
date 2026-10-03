<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration;

use OC\Memcache\Factory as CacheFactory;
use OCA\Libresign\Tests\lib\AppConfigOverwrite;
use OCP\IAppConfig;
use OCP\IConfig;

class TestCase extends \Test\TestCase {
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

	public function mockConfig(array $config): void {
		$appConfig = self::getMockAppConfig();
		foreach ($config as $app => $keys) {
			foreach ($keys as $key => $value) {
				if (is_bool($value)) {
					$appConfig->setValueBool($app, $key, $value);
					continue;
				}
				if (is_int($value)) {
					$appConfig->setValueInt($app, $key, $value);
					continue;
				}
				if (is_float($value)) {
					$appConfig->setValueFloat($app, $key, $value);
					continue;
				}
				if (is_array($value) || is_object($value)) {
					$value = json_encode($value);
				}
				$appConfig->setValueString($app, $key, (string)$value);
			}
		}
	}

	public function haveDependents(): bool {
		$reflector = new \ReflectionClass(static::class);
		foreach ($reflector->getMethods() as $method) {
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
		return preg_match('#@depends #s', $docblock) === 1;
	}

	public function setUp(): void {
		$this->ensureAppConfigOverwrite();
		static::getMockAppConfig();
		$this->suppressMailDelivery();
		if ($this->iDependOnOthers() || !$this->IsDatabaseAccessAllowed()) {
			return;
		}
		$this->cleanDatabase();
	}

	public function tearDown(): void {
		if ($this->haveDependents() || !$this->IsDatabaseAccessAllowed()) {
			return;
		}
		$this->cleanDatabase();
	}

	private function suppressMailDelivery(): void {
		$mailService = $this->createMock(\OCA\Libresign\Service\MailService::class);
		$mailService->method('notifyUnsignedUser')->willReturnCallback(static function (): void {
		});
		$mailService->method('notifySignDataUpdated')->willReturnCallback(static function (): void {});
		$mailService->method('notifySignedUser')->willReturnCallback(static function (): void {});
		$mailService->method('notifyCanceledRequest')->willReturnCallback(static function (): void {});
		$mailService->method('sendCodeToSign')->willReturnCallback(static function (): void {});
		$this->overwriteService(\OCA\Libresign\Service\MailService::class, $mailService);
	}

	private function ensureAppConfigOverwrite(): void {
		$service = self::getMockAppConfig();
		if ($service instanceof AppConfigOverwrite) {
			return;
		}
		$connection = $this->getDbConnection();
		if (!$connection instanceof \OCP\IDBConnection) {
			return;
		}
		$this->overwriteService(IAppConfig::class, new AppConfigOverwrite(
			$connection,
			\OCP\Server::get(IConfig::class),
			\OCP\Server::get(\OC\Config\ConfigManager::class),
			\OCP\Server::get(\OC\Config\PresetManager::class),
			\OCP\Server::get(\Psr\Log\LoggerInterface::class),
			\OCP\Server::get(\OCP\Security\ICrypto::class),
			\OCP\Server::get(CacheFactory::class),
		));
	}

	private function getDbConnection(): ?\OCP\IDBConnection {
		$connection = \OCP\Server::get(\OCP\IDBConnection::class);
		if ($connection instanceof \OCP\IDBConnection) {
			return $connection;
		}
		if (!isset(\OC::$server)) {
			return null;
		}
		try {
			$connection = \OC::$server->get(\OCP\IDBConnection::class);
		} catch (\Throwable) {
			return null;
		}
		return $connection instanceof \OCP\IDBConnection ? $connection : null;
	}

	private function cleanDatabase(): void {
		$db = $this->getDbConnection();
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

	public function createAccount(string $username, string $password, string $groupName = 'testGroup'): \OC\User\User {
		$this->users[] = $username;
		$this->mockConfig(['core' => ['newUser.sendEmail' => 'no']]);
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

	public function markUserExists(string $username): void {
		$this->users[] = $username;
	}

	public function deleteUsers(): void {
		foreach ($this->users as $username) {
			$this->deleteUserIfExists($username);
		}
	}

	public function deleteUserIfExists(string $username): void {
		$user = \OCP\Server::get(\OCP\IUserManager::class)->get($username);
		if ($user) {
			try {
				$user->delete();
			} catch (\Throwable) {
			}
		}
	}
}
