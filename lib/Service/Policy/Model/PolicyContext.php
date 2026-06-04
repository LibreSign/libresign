<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Model;

final class PolicyContext {
	private ActorRole $actorRole;
	private ?string $userId = null;
	/** @var list<string> */
	private array $groups = [];
	/** @var list<string> */
	private array $circles = [];
	private ?ActiveGroupScope $activeGroupScope = null;
	/** @var array<string, mixed> */
	private array $requestOverrides = [];
	public function __construct() {
		$this->actorRole = ActorRole::regularUser();
	}

	public static function fromUserId(string $userId): self {
		$context = new self();
		$context->setUserId($userId);
		return $context;
	}

	public function setUserId(?string $userId): self {
		$this->userId = $userId;
		return $this;
	}

	public function getUserId(): ?string {
		return $this->userId;
	}

	/** @param list<string> $groups */
	public function setGroups(array $groups): self {
		$this->groups = $groups;
		return $this;
	}

	/** @return list<string> */
	public function getGroups(): array {
		return $this->groups;
	}

	/** @param list<string> $circles */
	public function setCircles(array $circles): self {
		$this->circles = $circles;
		return $this;
	}

	/** @return list<string> */
	public function getCircles(): array {
		return $this->circles;
	}

	public function setActiveGroupScope(?ActiveGroupScope $activeGroupScope): self {
		$this->activeGroupScope = $activeGroupScope;
		return $this;
	}

	public function getActiveGroupScope(): ?ActiveGroupScope {
		return $this->activeGroupScope;
	}

	/** @param array<string, mixed> $requestOverrides */
	public function setRequestOverrides(array $requestOverrides): self {
		$this->requestOverrides = $requestOverrides;
		return $this;
	}

	/** @return array<string, mixed> */
	public function getRequestOverrides(): array {
		return $this->requestOverrides;
	}

	public function setActorRole(ActorRole $role): self {
		$this->actorRole = $role;
		return $this;
	}

	public function getActorRole(): ActorRole {
		return $this->actorRole;
	}
}
