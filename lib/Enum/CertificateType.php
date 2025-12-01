<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Enum;

enum CertificateType: string {
	case ROOT = 'root';
	case INTERMEDIATE = 'intermediate';
	case LEAF = 'leaf';

	public function isCA(): bool {
		return $this === self::ROOT || $this === self::INTERMEDIATE;
	}

	public function isRoot(): bool {
		return $this === self::ROOT;
	}

	public function isLeaf(): bool {
		return $this === self::LEAF;
	}

	public function getDescription(): string {
		return match($this) {
			self::ROOT => 'Root Certificate (CA)',
			self::INTERMEDIATE => 'Intermediate Certificate (CA)',
			self::LEAF => 'User Certificate',
		};
	}
}
