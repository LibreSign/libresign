<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy;

interface PolicyDefinitionInterface {
	public function key(): string;

	public function normalizeValue(mixed $rawValue): mixed;

	public function validateValue(mixed $value): void;

	/** @return list<mixed> */
	public function allowedValues(PolicyContext $context): array;

	public function defaultSystemValue(): mixed;
}
