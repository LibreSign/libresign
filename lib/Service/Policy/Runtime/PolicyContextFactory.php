<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Runtime;

use OCA\Libresign\Service\Policy\Model\PolicyContext;
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
		return $this->build($user?->getUID(), $user, $requestOverrides, $activeContext, $user);
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
		return $this->build($user?->getUID(), $user, $requestOverrides, $activeContext, $this->userSession->getUser());
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

		return $this->build($userId, $user, $requestOverrides, $activeContext, $this->userSession->getUser());
	}

	/** @param array<string, mixed> $requestOverrides */
	private function build(?string $userId, ?IUser $user, array $requestOverrides = [], ?array $activeContext = null, ?IUser $currentActor = null): PolicyContext {
		$context = (new PolicyContext())
			->setRequestOverrides($requestOverrides)
			->setActiveContext($activeContext)
			->setActorCapabilities($this->resolveActorCapabilities($currentActor));

		if ($userId !== null && $userId !== '') {
			$context->setUserId($userId);
			if ($user instanceof IUser) {
				$context->setGroups($this->groupManager->getUserGroupIds($user));
			}
		}

		return $context;
	}

	/** @return array<string, bool> */
	private function resolveActorCapabilities(?IUser $currentActor): array {
		if (!$currentActor instanceof IUser) {
			return [
				'canManageSystemPolicies' => false,
				'canManageGroupPolicies' => false,
			];
		}

		$userId = $currentActor->getUID();
		$canManageSystemPolicies = $this->groupManager->isAdmin($userId) === true;

		return [
			'canManageSystemPolicies' => $canManageSystemPolicies,
			'canManageGroupPolicies' => $canManageSystemPolicies || $this->subAdmin->isSubAdmin($currentActor) === true,
		];
	}
}
