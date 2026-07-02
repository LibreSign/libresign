<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Model;

final class ActorRole {
	public function __construct(
		public readonly bool $canManageSystemPolicies,
		public readonly bool $canManageGroupPolicies,
		public readonly int $manageableGroupCount,
	) {
	}

	public static function systemAdmin(): self {
		return new self(true, true, PHP_INT_MAX);
	}

	public static function groupAdmin(int $groupCount): self {
		return new self(false, true, $groupCount);
	}

	public static function regularUser(): self {
		return new self(false, false, 0);
	}
}
