<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAV\Connector\Sabre {
	class Directory implements \Sabre\DAV\INode {
		public function getId(): int {
			return 0;
		}
	}
}
