<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\UsageStatistics\Source;

use OCP\IDBConnection;
use OCP\ServerVersion;

/**
 * Raw facts about the server, the PHP runtime and the database. Callers reduce
 * them to coarse values before reporting.
 */
class RuntimeEnvironment {
	public function __construct(
		private IDBConnection $db,
		private ServerVersion $serverVersion,
	) {
	}

	/** major.minor.patch, without build or channel. */
	public function nextcloudVersion(): string {
		return sprintf(
			'%d.%d.%d',
			$this->serverVersion->getMajorVersion(),
			$this->serverVersion->getMinorVersion(),
			$this->serverVersion->getPatchVersion(),
		);
	}

	public function phpVersion(): string {
		return PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
	}

	public function osFamily(): string {
		return PHP_OS_FAMILY;
	}

	/** Machine hardware name only; never the hostname or the kernel string. */
	public function machine(): string {
		return php_uname('m');
	}

	/**
	 * @return IDBConnection::PLATFORM_*
	 */
	public function databasePlatform(): string {
		return $this->db->getDatabaseProvider(true);
	}

	/**
	 * The server version string as reported by the database, or null when the
	 * platform offers no portable way to read it.
	 */
	public function databaseServerVersion(): ?string {
		$query = match ($this->databasePlatform()) {
			IDBConnection::PLATFORM_MYSQL, IDBConnection::PLATFORM_MARIADB => 'SELECT VERSION()',
			IDBConnection::PLATFORM_POSTGRES => 'SHOW server_version',
			IDBConnection::PLATFORM_SQLITE => 'SELECT sqlite_version()',
			default => null,
		};
		if ($query === null) {
			return null;
		}
		$result = $this->db->executeQuery($query);
		$version = $result->fetchOne();
		$result->closeCursor();
		return is_string($version) ? $version : null;
	}
}
