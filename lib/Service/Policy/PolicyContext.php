<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy;

final class PolicyContext {
	private ?string $userId = null;
	/** @var list<string> */
	private array $groups = [];
	/** @var list<string> */
	private array $circles = [];
	/** @var array<string, mixed>|null */
	private ?array $activeContext = null;
	/** @var array<string, mixed> */
	private array $requestOverrides = [];
	/** @var array<string, mixed> */
	private array $actorCapabilities = [];

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

	/** @param array<string, mixed>|null $activeContext */
	public function setActiveContext(?array $activeContext): self {
		$this->activeContext = $activeContext;
		return $this;
	}

	/** @return array<string, mixed>|null */
	public function getActiveContext(): ?array {
		return $this->activeContext;
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

	/** @param array<string, mixed> $actorCapabilities */
	public function setActorCapabilities(array $actorCapabilities): self {
		$this->actorCapabilities = $actorCapabilities;
		return $this;
	}

	/** @return array<string, mixed> */
	public function getActorCapabilities(): array {
		return $this->actorCapabilities;
	}
}
