<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Crl\Ldap;

use OCP\ICache;
use OCP\ICacheFactory;
use Psr\Log\LoggerInterface;

/**
 * Downloads CRL content from LDAP-based CRL Distribution Points.
 *
 * Handles LDAP URLs as defined in RFC 2255 / RFC 4516, e.g.:
 * ldap://host/dn?attributes?scope?filter
 *
 * This enables validation of government-issued certificates that publish
 * their CRL via LDAP instead of HTTP. Results are cached for 24 h to avoid
 * repeated LDAP round-trips during the same day.
 *
 * The actual LDAP I/O is delegated to an {@see ILdapConnection} so that
 * tests can inject a mock without requiring a live LDAP server or the PHP
 * ldap extension.
 */
class LdapCrlDownloader {
	private ICache $cache;

	public function __construct(
		private LoggerInterface $logger,
		ICacheFactory $cacheFactory,
		private ILdapConnection $ldap = new PhpLdapConnection(),
	) {
		$this->cache = $cacheFactory->createDistributed('libresign_crl_ldap');
	}

	public function isLdapUrl(string $url): bool {
		$scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');
		return in_array($scheme, ['ldap', 'ldaps'], true);
	}

	public function download(string $url): ?string {
		$cacheKey = sha1($url);
		$cached = $this->cache->get($cacheKey);
		if ($cached !== null) {
			return $cached;
		}

		$content = $this->fetchFromLdap($url);
		if ($content !== null) {
			$this->cache->set($cacheKey, $content, 86400);
		}
		return $content;
	}

	private function fetchFromLdap(string $url): ?string {
		$parsed = parse_url($url);
		if (!$parsed) {
			return null;
		}

		$host = $parsed['host'] ?? null;
		$scheme = strtolower($parsed['scheme'] ?? 'ldap');
		$port = $parsed['port'] ?? ($scheme === 'ldaps' ? 636 : 389);
		$dn = isset($parsed['path']) ? urldecode(ltrim($parsed['path'], '/')) : '';

		if (!$host || !$dn) {
			$this->logger->warning('Invalid LDAP URL for CRL retrieval: missing host or DN', ['url' => $url]);
			return null;
		}

		// LDAP URL query components: attributes?scope?filter (RFC 4516)
		$queryParts = isset($parsed['query']) ? explode('?', $parsed['query']) : [];
		$attributeStr = $queryParts[0] ?? 'certificateRevocationList';
		$scope = strtolower($queryParts[1] ?? 'base');
		$filter = $queryParts[2] ?? '(objectClass=*)';

		// Strip option suffixes like ;binary from attribute names for the LDAP query
		$attributes = array_values(array_filter(
			array_map(fn (string $attr) => explode(';', trim($attr))[0], explode(',', $attributeStr))
		));
		if (empty($attributes)) {
			$attributes = ['certificateRevocationList'];
		}

		// Ensure filter is wrapped in parentheses as required by LDAP
		if (empty($filter) || $filter === '*') {
			$filter = '(objectClass=*)';
		} elseif (!str_starts_with($filter, '(')) {
			$filter = '(' . $filter . ')';
		}

		$ldapConn = null;
		try {
			$ldapConn = $this->ldap->connect($host, $port);

			$this->ldap->configure($ldapConn);

			// Anonymous bind (CRL endpoints are publicly readable)
			$this->ldap->bind($ldapConn);

			$result = match ($scope) {
				'one', 'onelevel' => $this->ldap->listEntries($ldapConn, $dn, $filter, $attributes),
				'sub', 'subtree' => $this->ldap->search($ldapConn, $dn, $filter, $attributes),
				default => $this->ldap->read($ldapConn, $dn, $filter, $attributes),
			};

			if (!$result) {
				$this->logger->warning('LDAP search returned no result for CRL retrieval', [
					'dn' => $dn,
					'filter' => $filter,
					'scope' => $scope,
				]);
				return null;
			}

			$entries = $this->ldap->getEntries($ldapConn, $result);

			if (empty($entries) || ($entries['count'] ?? 0) === 0) {
				return null;
			}

			foreach ($attributes as $attr) {
				$attrLower = strtolower($attr);
				/** @psalm-suppress InvalidArrayOffset */
				if (!empty($entries[0][$attrLower][0])) {
					return $entries[0][$attrLower][0];
				}
			}

			return null;
		} catch (\RuntimeException $e) {
			$this->logger->warning('Failed to connect to LDAP server for CRL retrieval: ' . $e->getMessage(), [
				'url' => $url,
			]);
			return null;
		} catch (\Exception $e) {
			$this->logger->warning('Failed to retrieve CRL via LDAP: ' . $e->getMessage(), ['url' => $url]);
			return null;
		} finally {
			if ($ldapConn !== null) {
				$this->ldap->unbind($ldapConn);
			}
		}
	}
}
