<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Runtime;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\Policy\Model\ActiveGroupScope;
use OCA\Libresign\Service\Policy\Model\ActorRole;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCP\AppFramework\Http;
use OCP\Group\ISubAdmin;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;

final class PolicyContextFactory {
	public function __construct(
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private ISubAdmin $subAdmin,
		private IUserSession $userSession,
	) {
	}

	/** @param array<string, mixed> $requestOverrides */
	public function forCurrentUser(array $requestOverrides = [], ?array $activeContext = null): PolicyContext {
		$user = $this->userSession->getUser();
		$actorGroupIds = $activeContext !== null ? $this->getUserGroupIds($user) : null;
		$scope = $this->parseActiveGroupScope($activeContext, $user, $actorGroupIds);
		return $this->build($user?->getUID(), $user, $requestOverrides, $scope, $user, $actorGroupIds);
	}

	public function isCurrentActorSystemAdmin(): bool {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return false;
		}

		return $this->groupManager->isAdmin($user->getUID());
	}

	/** @param array<string, mixed> $requestOverrides */
	public function forUser(?IUser $user, array $requestOverrides = [], ?array $activeContext = null): PolicyContext {
		$currentActor = $this->userSession->getUser();
		$actorGroupIds = $activeContext !== null ? $this->getUserGroupIds($currentActor) : null;
		$scope = $this->parseActiveGroupScope($activeContext, $currentActor, $actorGroupIds);
		return $this->build($user?->getUID(), $user, $requestOverrides, $scope, $currentActor, $actorGroupIds);
	}

	/** @param array<string, mixed> $requestOverrides */
	public function forUserId(?string $userId, array $requestOverrides = [], ?array $activeContext = null): PolicyContext {
		$user = null;
		if ($userId !== null && $userId !== '') {
			$loadedUser = $this->userManager->get($userId);
			if ($loadedUser instanceof IUser) {
				$user = $loadedUser;
			}
		}

		$currentActor = $this->userSession->getUser();
		$actorGroupIds = $activeContext !== null ? $this->getUserGroupIds($currentActor) : null;
		$scope = $this->parseActiveGroupScope($activeContext, $currentActor, $actorGroupIds);
		return $this->build($userId, $user, $requestOverrides, $scope, $currentActor, $actorGroupIds);
	}

	/** @param array<string, mixed> $requestOverrides */
	private function build(?string $userId, ?IUser $user, array $requestOverrides = [], ?ActiveGroupScope $activeGroupScope = null, ?IUser $currentActor = null, ?array $currentActorGroupIds = null): PolicyContext {
		$isCurrentActorContext = $user instanceof IUser
			&& $currentActor instanceof IUser
			&& $user->getUID() === $currentActor->getUID();
		$sharedGroupIds = [];
		if ($isCurrentActorContext) {
			$sharedGroupIds = $currentActorGroupIds ?? $this->getUserGroupIds($user);
		}

		$actorGroupIds = $currentActorGroupIds ?? ($isCurrentActorContext ? $sharedGroupIds : null);

		$context = (new PolicyContext())
			->setRequestOverrides($requestOverrides)
			->setActiveGroupScope($activeGroupScope)
			->setActorRole($this->resolveActorRole($currentActor, $actorGroupIds));

		if ($userId !== null && $userId !== '') {
			$context->setUserId($userId);
			if ($user instanceof IUser) {
				$context->setGroups($isCurrentActorContext ? $sharedGroupIds : $this->getUserGroupIds($user));
			}
		}

		return $context;
	}

	/**
	 * @param array<string, mixed>|null $activeContext
	 * @param list<string>|null $currentActorGroupIds
	 */
	private function parseActiveGroupScope(?array $activeContext, ?IUser $currentActor, ?array $currentActorGroupIds = null): ?ActiveGroupScope {
		if ($activeContext === null) {
			return null;
		}

		$type = $activeContext['type'] ?? null;
		$id = $activeContext['id'] ?? null;
		if ($type !== 'group' || !is_string($id) || trim($id) === '') {
			throw new LibresignException('Only group active context is supported for policy overrides.', Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$groupId = trim($id);
		if (!$currentActor instanceof IUser) {
			throw new LibresignException('You are not allowed to use this policy context.', Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$actorGroupIds = $currentActorGroupIds ?? $this->getUserGroupIds($currentActor);
		if (!in_array($groupId, $actorGroupIds, true)) {
			throw new LibresignException('You are not allowed to use this policy context.', Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		return new ActiveGroupScope($groupId);
	}

	private function resolveActorRole(?IUser $currentActor, ?array $currentActorGroupIds = null): ActorRole {
		if (!$currentActor instanceof IUser) {
			return ActorRole::regularUser();
		}

		$userId = $currentActor->getUID();
		if ($this->groupManager->isAdmin($userId) === true) {
			return ActorRole::systemAdmin();
		}

		$actorGroupIds = $currentActorGroupIds ?? $this->getUserGroupIds($currentActor);
		$manageableGroupIds = array_values(array_filter(
			$actorGroupIds,
			static fn (mixed $groupId): bool => is_string($groupId) && trim($groupId) !== '',
		));
		if ($manageableGroupIds === []) {
			return ActorRole::regularUser();
		}

		if ($this->subAdmin->isSubAdmin($currentActor)) {
			return ActorRole::groupAdmin(count($manageableGroupIds));
		}

		return ActorRole::regularUser();
	}

	/** @return list<string> */
	private function getUserGroupIds(?IUser $user): array {
		if (!$user instanceof IUser) {
			return [];
		}

		$groupIds = $this->groupManager->getUserGroupIds($user);
		return array_values(array_filter(
			$groupIds,
			static fn (mixed $groupId): bool => is_string($groupId) && trim($groupId) !== '',
		));
	}
}
