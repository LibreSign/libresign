<?php

namespace OCA\Libresign\Tests\Unit;

use OCP\IGroupManager;
use OCP\IUserManager;

trait UserTrait {
	/** @var IGroupManager */
	private $groupManager;

	/** @var IUserManager */
	private $userManager;

	private $testGroup;

	/** @var \Test\Util\User\Dummy */
	private $backendUser;

	/** @var \Test\Util\Group\Dummy */
	private $backendGroup;

	public function userSetUp(): void {
		$this->groupManager = \OC::$server->get(\OCP\IGroupManager::class);
		$this->userManager = \OC::$server->get(\OCP\IUserManager::class);

		$this->backendUser = new \Test\Util\User\Dummy();
		\OC_User::useBackend($this->backendUser);

		$this->backendGroup = new \Test\Util\Group\Dummy();
		$this->groupManager->addBackend($this->backendGroup);

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
		$this->backendUser->createUser($username, $password);
		$user = $this->userManager->get($username);
		$this->testGroup->addUser($user);
		return $user;
	}

	public function tearDown(): void {
		parent::tearDown();
		foreach ($this->backendUser->getUsers() as $username) {
			$user = $this->userManager->get($username);
			$this->testGroup->removeUser($user);
		}
		foreach ($this->backendGroup->getGroups() as $group) {
			$this->groupManager->get($group)->delete();
		}
	}
}
