<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use OC\DB\Connection;
use OCP\Files\IAppData;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class ResynchronizeDatabaseSequences implements IRepairStep {
	protected IAppData $appData;
	public function __construct(
		private IDBConnection $connection,
		protected IConfig $config,
	) {
	}

	public function getName(): string {
		return 'Resynchronize database sequences';
	}

	public function run(IOutput $output): void {
		if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
			$tools = new \OC\DB\PgSqlTools($this->config);
			try {
				$tools->resynchronizeDatabaseSequences(\OCP\Server::get(Connection::class));
			} catch (\Throwable) {
			}
		}
	}
}
