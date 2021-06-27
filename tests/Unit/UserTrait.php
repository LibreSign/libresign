<?php

namespace OCA\Libresign\Tests\Unit;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IGroupManager;
use OCP\IUserManager;

trait UserTrait {
	use LibresignFileTrait;

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
	 * Clean data
	 *
	 * @after
	 */
	public function userTraitTearDown(): void {
		try {
			$userList = $this->userTraitDeleteAllUsers();
			if (!$userList) {
				return;
			}
			$this->userTraitDeleteAllAccountFiles($userList);
			$this->userTraitDeleteAllGroups();
			$this->uesrTraitDeleteAllFiles($userList);
		} catch (\Throwable $th) {
		}
	}

	protected function userTraitDeleteAllAccountFiles(array $userList) {
		$db = \OC::$server->get(\OCP\IDBConnection::class);
		if (!$db) {
			return;
		}
		$qb = $db->getQueryBuilder();
		$qb->delete('libresign_account_file')
			->where($qb->expr()->in('user_id', $qb->createNamedParameter($userList, IQueryBuilder::PARAM_STR_ARRAY)));
		$qb->execute();
	}

	protected function userTraitDeleteAllUsers(): array {
		$userList = [];
		foreach ($this->userTraitBackendUser->getUsers() as $username) {
			$userList[] = $username;
			$user = $this->userTraitUserManager->get($username);
			$this->userTraitTestGroup->removeUser($user);
		}
		return $userList;
	}

	protected function userTraitDeleteAllGroups() {
		foreach ($this->userTraitBackendGroup->getGroups() as $group) {
			$this->userTraitGroupManager->get($group)->delete();
		}
	}

	protected function uesrTraitDeleteAllFiles(array $userList) {
		$db = \OC::$server->get(\OCP\IDBConnection::class);
		if (!$db) {
			return;
		}
		$qb = $db->getQueryBuilder();
		$qb->select('*')
			->from('libresign_file', 'f')
			->where(
				$qb->expr()->in('f.user_id', $qb->createNamedParameter($userList, IQueryBuilder::PARAM_STR_ARRAY))
			);
		$cursor = $qb->execute();
		while ($row = $cursor->fetch()) {
			$row['users'] = $this->userTraitGetSigners($row['id']);
			$this->addFile($row);
		}
		$cursor->closeCursor();
		$this->libresignFileTearDown();
	}

	protected function userTraitGetSigners(int $fileId) {
		$db = \OC::$server->get(\OCP\IDBConnection::class);
		$qb = $db->getQueryBuilder();
		$qb->select('*')
			->from('libresign_file_user', 'fu')
			->where(
				$qb->expr()->eq('fu.file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT))
			);
		$cursor = $qb->execute();
		$return = [];
		while ($row = $cursor->fetch()) {
			$return[] = $row;
		}
		return $return;
	}
}
