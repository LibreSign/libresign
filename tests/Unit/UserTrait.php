<?php

namespace OCA\Libresign\Tests\Unit;

use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;

trait UserTrait {
	/** @var IGroupManager */
	private $groupManager;

	/** @var IUserManager */
	private $userManager;

	private $testGroup;

	/** @var \Test\Util\User\Dummy */
	private $userBackend;

	public function userSetUp(): void {
		$this->groupManager = \OC::$server->get(\OCP\IGroupManager::class);
		$this->userManager = \OC::$server->get(\OCP\IUserManager::class);

		$this->backend = new \Test\Util\User\Dummy();
		\OC_User::useBackend($this->backend);
		$this->testGroup = $this->groupManager->createGroup('testGroup');
	}

	/**
	 * Create user
	 *
	 * @param string $username
	 * @param string $password
	 * @return \OC\User\User
	 */
	public function createUser($username, $password) {
		$this->backend->createUser($username, $password);
		$user = $this->userManager->get($username);
		$this->testGroup->addUser($user);
		return $user;
	}

	public function tearDown(): void {
		parent::tearDown();
		foreach ($this->backend->getUsers() as $username) {
			$user = $this->userManager->get($username);
			$this->testGroup->removeUser($user);
		}
	}
}
