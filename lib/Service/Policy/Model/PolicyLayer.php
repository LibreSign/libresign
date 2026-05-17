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
	/** @var array<string, mixed> */
	private array $notes = [];

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

	/** @param array<string, mixed> $notes */
	public function setNotes(array $notes): self {
		$this->notes = $notes;
		return $this;
	}

	/** @return array<string, mixed> */
	public function getNotes(): array {
		return $this->notes;
	}
}
