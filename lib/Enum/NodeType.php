<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Enum;

enum NodeType: string {
	case FILE = 'file';
	case ENVELOPE = 'envelope';

	public function isEnvelope(): bool {
		return $this === self::ENVELOPE;
	}
}
