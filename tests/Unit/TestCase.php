<?php

namespace OCA\Libresign\Tests\Unit;

use OCA\Libresign\Tests\lib\AppConfigOverwrite;
use OCA\Libresign\Tests\lib\AppConfigOverwrite20;

class TestCase extends \Test\TestCase {
	use LibresignFileTrait;

	private array $users = [];

	public function mockConfig($config) {
		$service = \OC::$server->get(\OC\AppConfig::class);
		if (is_subclass_of($service, \OC\AppConfig::class)) {
			foreach ($config as $app => $keys) {
				foreach ($keys as $key => $value) {
					$service->setValue($app, $key, $value);
				}
			}
			return;
		}
		\OC::$server->registerService(\OC\AppConfig::class, function () use ($config) {
			if (\OCP\Util::getVersion()[0] <= '20') {
				return new AppConfigOverwrite20(\OC::$server->get(\OCP\IDBConnection::class), $config);
			} else {
				return new AppConfigOverwrite(\OC::$server->get(\OC\DB\Connection::class), $config);
			}
		});
	}

	public function haveDependents(): bool {
		$reflector = new \ReflectionClass(\get_class($this));

		$methods = $reflector->getMethods();
		foreach ($methods as $method) {
			$docblock = $reflector->getMethod($method->getName())->getDocComment();
			if (preg_match('#@depends ' . $this->getName(false) . '\n#s', $docblock)) {
				return true;
			}
		}
		return false;
	}

	public function iDependOnOthers(): bool {
		$reflector = new \ReflectionClass(\get_class($this));
		$docblock = $reflector->getMethod($this->getName(false))->getDocComment();
		if (preg_match('#@depends #s', $docblock)) {
			return true;
		}
		return false;
	}

	public function setUp(): void {
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

	private function cleanDatabase(): void {
		$db = \OC::$server->get(\OCP\IDBConnection::class);
		if (!$db) {
			return;
		}
		$this->deleteUsers();

		$delete = $db->getQueryBuilder();
		$delete->delete('libresign_file')->executeStatement();
		$delete->delete('libresign_file_user')->executeStatement();
		$delete->delete('libresign_user_element')->executeStatement();
		$delete->delete('libresign_file_element')->executeStatement();
		$delete->delete('libresign_account_file')->executeStatement();
	}

	/**
	 * Create user
	 *
	 * @param string $username
	 * @param string $password
	 * @return \OC\User\User
	 */
	public function createUser($username, $password) {
		$this->users[] = $username;
		$this->mockConfig([
			'core' => [
				'newUser.sendEmail' => 'no'
			]
		]);

		$userManager = \OC::$server->getUserManager();
		$groupManager = \OC::$server->getGroupManager();

		$user = $userManager->get($username);
		if (!$user) {
			$user = $userManager->createUser($username, $password);
		}
		$group = $groupManager->get('testGroup');
		if (!$group) {
			$group = $groupManager->createGroup('testGroup');
		}

		if ($group && $user) {
			$group->addUser($user);
		}
		return $user;
	}

	public function markUserExists($username): void {
		$this->users[] = $username;
	}

	public function deleteUsers() {
		foreach ($this->users as $username) {
			$this->deleteUserIfExists($username);
		}
	}

	public function deleteUserIfExists($username): void {
		$user = \OC::$server->getUserManager()->get($username);
		if ($user) {
			try {
				$user->delete();
			} catch (\Throwable $th) {
			}
		}
	}

	private function getBinariesFromCache(): void {
		/** @var \OCA\Libresign\Service\InstallService */
		$install = \OC::$server->get(\OCA\Libresign\Service\InstallService::class);
		$appPath = $install->getFullPath();
		$cachePath = preg_replace('/\/.*\/appdata_[a-z0-9]*/', \OC::$server->getTempManager()->getTempBaseDir(), $appPath);
		if (!file_exists($cachePath)) {
			return;
		}
		$this->recursiveCopy($cachePath, $appPath);
	}

	private function backupBinaries(): void {
		/** @var \OCA\Libresign\Service\InstallService */
		$install = \OC::$server->get(\OCA\Libresign\Service\InstallService::class);
		$appPath = $install->getFullPath();
		if (!is_readable($appPath)) {
			return;
		}
		$isEmpty = count(scandir($appPath)) == 2;
		if ($isEmpty) {
			return;
		}
		$cachePath = preg_replace('/\/.*\/appdata_[a-z0-9]*/', \OC::$server->getTempManager()->getTempBaseDir(), $appPath);
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
					copy($item, $newDest);
				}
			}
			if (fileperms($item) !== fileperms($newDest)) {
				chmod($newDest, fileperms($item));
			}
		}
	}
}
