<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Model;

final class PolicyLayer {
	private string $scope = '';
	private mixed $value = null;
	private bool $allowChildOverride = false;
	private bool $visibleToChild = true;
	/** @var list<mixed> */
	private array $allowedValues = [];
	private bool $createdBySystemAdmin = false;
	private bool $delegatedFromSystemCreatedSeed = false;

	public function setScope(string $scope): self {
		$this->scope = $scope;
		return $this;
	}

	public function getScope(): string {
		return $this->scope;
	}

	public function setValue(mixed $value): self {
		$this->value = $value;
		return $this;
	}

	public function getValue(): mixed {
		return $this->value;
	}

	public function setAllowChildOverride(bool $allowChildOverride): self {
		$this->allowChildOverride = $allowChildOverride;
		return $this;
	}

	public function isAllowChildOverride(): bool {
		return $this->allowChildOverride;
	}

	public function setVisibleToChild(bool $visibleToChild): self {
		$this->visibleToChild = $visibleToChild;
		return $this;
	}

	public function isVisibleToChild(): bool {
		return $this->visibleToChild;
	}

	/** @param list<mixed> $allowedValues */
	public function setAllowedValues(array $allowedValues): self {
		$this->allowedValues = $allowedValues;
		return $this;
	}

	/** @return list<mixed> */
	public function getAllowedValues(): array {
		return $this->allowedValues;
	}

	public function setCreatedBySystemAdmin(bool $value): self {
		$this->createdBySystemAdmin = $value;
		return $this;
	}

	public function isCreatedBySystemAdmin(): bool {
		return $this->createdBySystemAdmin;
	}

	public function setDelegatedFromSystemCreatedSeed(bool $value): self {
		$this->delegatedFromSystemCreatedSeed = $value;
		return $this;
	}

	public function isDelegatedFromSystemCreatedSeed(): bool {
		return $this->delegatedFromSystemCreatedSeed;
	}
}
