<?php

namespace OCA\Libresign\Tests\Unit;

use OCP\IGroupManager;
use OCP\IUserManager;

trait UserTrait {
	/** @var IGroupManager */
	private $userTraitGroupManager;

	/** @var IUserManager */
	private $userTraitUserManager;

	private $userTraitTestGroup;

	/** @var \Test\Util\User\Dummy */
	private $userTraitBackendUser;

	/** @var \Test\Util\Group\Dummy */
	private $userTraitBackendGroup;

	/**
	 * @before
	 */
	public function userTraitSetUp(): void {
		$this->userTraitUserManager = \OC::$server->get(\OCP\IUserManager::class);
		$this->userTraitUserManager->clearBackends();
		$this->userTraitBackendUser = new \Test\Util\User\Dummy();
		$this->userTraitUserManager->registerBackend($this->userTraitBackendUser);

		$this->userTraitGroupManager = \OC::$server->get(\OCP\IGroupManager::class);
		$this->userTraitGroupManager->clearBackends();
		$this->userTraitBackendGroup = new \Test\Util\Group\Dummy();
		$this->userTraitGroupManager->addBackend($this->userTraitBackendGroup);

		$this->userTraitTestGroup = $this->userTraitGroupManager->createGroup('testGroup');
	}

	/**
	 * Create user
	 *
	 * @param string $username
	 * @param string $password
	 * @return \OC\User\User
	 */
	public function createUser($username, $password) {
		$this->mockConfig([
			'core' => [
				'newUser.sendEmail' => 'no'
			]
		]);
		$this->userTraitBackendUser->createUser($username, $password);
		$user = $this->userTraitUserManager->get($username);
		$this->userTraitTestGroup->addUser($user);
		return $user;
	}

	public function deleteUser($username) {
		$user = $this->userTraitUserManager->get($username);
		$this->userTraitTestGroup->removeUser($user);
	}

	/**
	 * @after
	 */
	public function userTraitTearDown(): void {
		foreach ($this->userTraitBackendUser->getUsers() as $username) {
			$user = $this->userTraitUserManager->get($username);
			$this->userTraitTestGroup->removeUser($user);
		}
		foreach ($this->userTraitBackendGroup->getGroups() as $group) {
			$this->userTraitGroupManager->get($group)->delete();
		}
	}
}
