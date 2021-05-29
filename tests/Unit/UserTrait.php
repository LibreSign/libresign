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

	/** @var IUser */
	private $user;

	protected function userSetUp(): void {
		$this->groupManager = \OC::$server->get(\OCP\IGroupManager::class);
		$this->userManager = \OC::$server->get(\OCP\IUserManager::class);

		$backend = new \Test\Util\User\Dummy();
		\OC_User::useBackend($backend);
		$backend->createUser('username', 'password');
		$this->testGroup = $this->groupManager->createGroup('testGroup');
		$this->user = $this->userManager->get('username');
		// $this->user->setDisplayName()
		$this->testGroup->addUser($this->user);
	}

	public function tearDown(): void {
		parent::tearDown();
		$this->testGroup->removeUser($this->user);
	}
}