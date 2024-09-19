<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Command;

use OC\Core\Command\Base as CommandBase;
use OCA\Libresign\Service\Install\InstallService;
use Psr\Log\LoggerInterface;

class Base extends CommandBase {
	public function __construct(
		public InstallService $installService,
		protected LoggerInterface $logger,
	) {
		parent::__construct();
	}
}
