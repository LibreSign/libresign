<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Focused stub for the Nextcloud core command base used by LibreSign. Keeping
 * this local avoids pulling the whole upstream command implementation into the
 * analysis while preserving the command inheritance contract.
 */

namespace OC\Core\Command {
	abstract class Base extends \Symfony\Component\Console\Command\Command {
	}
}
