<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Crl\Ldap;

/**
 * Thin abstraction over the PHP LDAP extension functions.
 *
 * Allows test doubles to replace real LDAP I/O without requiring a live
 * LDAP server or the PHP ldap extension to be loaded.
 */
interface ILdapConnection {
	/** @return \LDAP\Connection
	 * @throws \RuntimeException when the connection cannot be established
	 */
	public function connect(string $host, int $port): mixed;

	/**
	 * Apply the recommended default options (protocol version, timeout,
	 * referrals) to an open connection. Grouped into one method so that
	 * callers never need to reference LDAP_OPT_* constants.
	 */
	public function configure(mixed $ldap): void;

	/** @return bool true on success */
	public function bind(mixed $ldap): bool;

	/**
	 * Equivalent to ldap_read (base scope — single entry).
	 *
	 * @return \LDAP\Result|array<array-key, \LDAP\Result>|false
	 */
	public function read(mixed $ldap, string $dn, string $filter, array $attributes): mixed;

	/**
	 * Equivalent to ldap_list (one-level scope).
	 *
	 * @return \LDAP\Result|array<array-key, \LDAP\Result>|false
	 */
	public function listEntries(mixed $ldap, string $dn, string $filter, array $attributes): mixed;

	/**
	 * Equivalent to ldap_search (subtree scope).
	 *
	 * @return \LDAP\Result|array<array-key, \LDAP\Result>|false
	 */
	public function search(mixed $ldap, string $dn, string $filter, array $attributes): mixed;

	/** @return array<string, mixed> */
	public function getEntries(mixed $ldap, mixed $result): array;

	public function unbind(mixed $ldap): void;
}
