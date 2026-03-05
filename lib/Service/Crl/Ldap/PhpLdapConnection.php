<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Crl\Ldap;

/**
 * Production implementation of ILdapConnection that delegates every call
 * to the corresponding PHP ldap_* function.
 *
 * If the PHP ldap extension is absent the class can still be instantiated;
 * callers are expected to check function_exists('ldap_connect') before use.
 */
class PhpLdapConnection implements ILdapConnection {

	#[\Override]
	public function connect(string $host, int $port): mixed {
		if (!function_exists('ldap_connect')) {
			throw new \RuntimeException('PHP ldap extension is not loaded');
		}
		$conn = @ldap_connect($host, $port);
		if (!$conn) {
			throw new \RuntimeException(sprintf('ldap_connect failed for %s:%d', $host, $port));
		}
		return $conn;
	}

	#[\Override]
	public function configure(mixed $ldap): void {
		ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($ldap, LDAP_OPT_NETWORK_TIMEOUT, 10);
		ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
	}

	public function setOption(mixed $ldap, int $option, mixed $value): void {
		ldap_set_option($ldap, $option, $value);
	}

	#[\Override]
	public function bind(mixed $ldap): bool {
		return (bool)@ldap_bind($ldap);
	}

	#[\Override]
	public function read(mixed $ldap, string $dn, string $filter, array $attributes): mixed {
		return @ldap_read($ldap, $dn, $filter, $attributes);
	}

	#[\Override]
	public function listEntries(mixed $ldap, string $dn, string $filter, array $attributes): mixed {
		return @ldap_list($ldap, $dn, $filter, $attributes);
	}

	#[\Override]
	public function search(mixed $ldap, string $dn, string $filter, array $attributes): mixed {
		return @ldap_search($ldap, $dn, $filter, $attributes);
	}

	#[\Override]
	public function getEntries(mixed $ldap, mixed $result): array {
		return ldap_get_entries($ldap, $result) ?: [];
	}

	#[\Override]
	public function unbind(mixed $ldap): void {
		@ldap_unbind($ldap);
	}
}
