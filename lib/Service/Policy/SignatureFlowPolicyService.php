<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy;

use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;

class SignatureFlowPolicyService {
	private DefaultPolicyResolver $resolver;

	public function __construct(
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private SignatureFlowPolicySource $source,
	) {
		$this->resolver = new DefaultPolicyResolver($this->source, [new SignatureFlowPolicyDefinition()]);
	}

	/** @param array<string, mixed> $requestOverrides */
	public function resolveForUserId(?string $userId, array $requestOverrides = [], ?array $activeContext = null): ResolvedPolicy {
		$context = new PolicyContext();
		$context->setRequestOverrides($requestOverrides);

		if ($activeContext !== null) {
			$context->setActiveContext($activeContext);
		}

		if ($userId !== null && $userId !== '') {
			$context->setUserId($userId);
			$user = $this->userManager->get($userId);
			if ($user instanceof IUser) {
				$context->setGroups($this->groupManager->getUserGroupIds($user));
			}
		}

		return $this->resolver->resolve('signature_flow', $context);
	}

	/** @param array<string, mixed> $requestOverrides */
	public function resolveForUser(?IUser $user, array $requestOverrides = [], ?array $activeContext = null): ResolvedPolicy {
		return $this->resolveForUserId($user?->getUID(), $requestOverrides, $activeContext);
	}
}
