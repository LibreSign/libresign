<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Sabre\DAV {
	abstract class ServerPlugin {
		abstract public function initialize(\Sabre\DAV\Server $server): void;
	}
}
