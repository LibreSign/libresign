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

	/** @var array<IUser> */
	private $users;

	/** @var \Test\Util\User\Dummy */
	private $userBackend;

	protected function userSetUp(): void {
		$this->groupManager = \OC::$server->get(\OCP\IGroupManager::class);
		$this->userManager = \OC::$server->get(\OCP\IUserManager::class);

		$this->backend = new \Test\Util\User\Dummy();
		\OC_User::useBackend($this->backend);
		$this->testGroup = $this->groupManager->createGroup('testGroup');
	}

	private function createUser($username, $password) {
		$this->backend->createUser($username, $password);
		$this->users[$username] = $this->userManager->get($username);
		$this->testGroup->addUser($this->users[$username]);
	}

	public function tearDown(): void {
		parent::tearDown();
		foreach ($this->users as $user) {
			$this->testGroup->removeUser($user);
		}
	}
}
