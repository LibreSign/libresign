<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Model;

final class ResolvedPolicy {
	private string $policyKey = '';
	private mixed $effectiveValue = null;
	private mixed $inheritedValue = null;
	private string $sourceScope = '';
	private bool $visible = false;
	private bool $editableByCurrentActor = false;
	/** @var list<mixed> */
	private array $allowedValues = [];
	private bool $canSaveAsUserDefault = false;
	private bool $canUseAsRequestOverride = false;
	private bool $preferenceWasCleared = false;
	private ?string $blockedBy = null;

	public function setPolicyKey(string $policyKey): self {
		$this->policyKey = $policyKey;
		return $this;
	}

	public function getPolicyKey(): string {
		return $this->policyKey;
	}

	public function setEffectiveValue(mixed $effectiveValue): self {
		$this->effectiveValue = $effectiveValue;
		return $this;
	}

	public function getEffectiveValue(): mixed {
		return $this->effectiveValue;
	}

	public function setInheritedValue(mixed $inheritedValue): self {
		$this->inheritedValue = $inheritedValue;
		return $this;
	}

	public function getInheritedValue(): mixed {
		return $this->inheritedValue;
	}

	public function setSourceScope(string $sourceScope): self {
		$this->sourceScope = $sourceScope;
		return $this;
	}

	public function getSourceScope(): string {
		return $this->sourceScope;
	}

	public function setVisible(bool $visible): self {
		$this->visible = $visible;
		return $this;
	}

	public function isVisible(): bool {
		return $this->visible;
	}

	public function setEditableByCurrentActor(bool $editableByCurrentActor): self {
		$this->editableByCurrentActor = $editableByCurrentActor;
		return $this;
	}

	public function isEditableByCurrentActor(): bool {
		return $this->editableByCurrentActor;
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

	public function setCanSaveAsUserDefault(bool $canSaveAsUserDefault): self {
		$this->canSaveAsUserDefault = $canSaveAsUserDefault;
		return $this;
	}

	public function canSaveAsUserDefault(): bool {
		return $this->canSaveAsUserDefault;
	}

	public function setCanUseAsRequestOverride(bool $canUseAsRequestOverride): self {
		$this->canUseAsRequestOverride = $canUseAsRequestOverride;
		return $this;
	}

	public function canUseAsRequestOverride(): bool {
		return $this->canUseAsRequestOverride;
	}

	public function setPreferenceWasCleared(bool $preferenceWasCleared): self {
		$this->preferenceWasCleared = $preferenceWasCleared;
		return $this;
	}

	public function wasPreferenceCleared(): bool {
		return $this->preferenceWasCleared;
	}

	public function setBlockedBy(?string $blockedBy): self {
		$this->blockedBy = $blockedBy;
		return $this;
	}

	public function getBlockedBy(): ?string {
		return $this->blockedBy;
	}

	/** @return array<string, mixed> */
	public function toArray(): array {
		return [
			'policyKey' => $this->getPolicyKey(),
			'effectiveValue' => $this->getEffectiveValue(),
			'inheritedValue' => $this->getInheritedValue(),
			'sourceScope' => $this->getSourceScope(),
			'visible' => $this->isVisible(),
			'editableByCurrentActor' => $this->isEditableByCurrentActor(),
			'allowedValues' => $this->getAllowedValues(),
			'canSaveAsUserDefault' => $this->canSaveAsUserDefault(),
			'canUseAsRequestOverride' => $this->canUseAsRequestOverride(),
			'preferenceWasCleared' => $this->wasPreferenceCleared(),
			'blockedBy' => $this->getBlockedBy(),
		];
	}
}
