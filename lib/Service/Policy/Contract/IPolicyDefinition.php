<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Contract;

use OCA\Libresign\Service\Policy\Model\PolicyContext;

interface IPolicyDefinition {
	public function key(): string;

	public function resolutionMode(): string;

	public function getAppConfigKey(): string;

	public function getUserPreferenceKey(): string;

	public function normalizeValue(mixed $rawValue): mixed;

	public function validateValue(mixed $value, PolicyContext $context): void;

	/** @return list<mixed> */
	public function allowedValues(PolicyContext $context): array;

	public function defaultSystemValue(): mixed;

	/**
	 * Whether this policy supports being saved as a user personal preference.
	 * Returns false for administrative-only policies (e.g. groups_request_sign)
	 * that must never appear in the user preferences screen.
	 */
	public function supportsUserPreference(): bool;
}
