<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\SignEngine;

use OCA\Libresign\Vendor\phpseclib3\File\ASN1;
use OCA\Libresign\Vendor\phpseclib3\File\ASN1\Element;
use OCA\Libresign\Vendor\phpseclib3\Math\BigInteger;

class TSA {
	private static bool $areOidsInitialized = false;
	private static array $asn1DecodingCache = [];

	private const CERTIFICATE_ATTRIBUTE_OIDS = [
		'2.5.4.3' => 'commonName',
		'2.5.4.6' => 'countryName',
		'2.5.4.10' => 'organizationName',
		'1.2.840.113549.1.9.1' => 'emailAddress',
	];

	private const TIMESTAMP_OIDS = [
		'TIME_STAMP_TOKEN' => '1.2.840.113549.1.9.16.2.14',
		'SIGNING_TIME' => '1.2.840.113549.1.9.5',
		'TST_INFO' => '1.2.840.113549.1.9.16.1.4',
	];

	private static ?array $timestampInfoStructure = null;
	private const CACHE_SIZE_LIMIT = 50;

	public function __construct() {
		$this->ensureOidsAreLoaded();
	}

	private function processContentCandidate($content, ?string &$cmsDer): array {
		try {
			if ($content instanceof Element) {
				return $this->decodeWithCache($cmsDer = $content->element);
			} elseif (is_string($content)) {
				return $this->decodeWithCache($cmsDer = $content);
			} elseif (is_array($content)) {
				return $content;
			}
		} catch (\Throwable $e) {
			error_log('TSA content processing failed: ' . $e->getMessage());
		}
		return [];
	}

	private function ensureOidsAreLoaded(): void {
		if (self::$areOidsInitialized) {
			return;
		}

		ASN1::loadOIDs([
			'md2' => '1.2.840.113549.2.2',
			'md5' => '1.2.840.113549.2.5',
			'id-sha1' => '1.3.14.3.2.26',
			'id-sha256' => '2.16.840.1.101.3.4.2.1',
			'id-sha384' => '2.16.840.1.101.3.4.2.2',
			'id-sha512' => '2.16.840.1.101.3.4.2.3',
			'timestampToken' => self::TIMESTAMP_OIDS['TIME_STAMP_TOKEN'],
			'signingTime' => self::TIMESTAMP_OIDS['SIGNING_TIME'],
			'tstInfo' => self::TIMESTAMP_OIDS['TST_INFO'],
		]);

		self::$areOidsInitialized = true;
	}

	private function convertDerToPkcs7Pem(string $derData): string {
		return "-----BEGIN PKCS7-----\n" . chunk_split(base64_encode($derData), 64, "\n") . "-----END PKCS7-----\n";
	}

	private function extractTimestampAuthorityName($timestampElement): array {
		if (!$timestampElement instanceof Element || !is_string($timestampElement->element)) {
			return [];
		}

		try {
			$decoded = $this->decodeWithCache($timestampElement->element);
			return $decoded[0] ? $this->extractCertificateHints([$decoded[0]]) : [];
		} catch (\Throwable) {
			return [];
		}
	}

	private function buildTimestampInfoStructure(): array {
		return [
			'type' => ASN1::TYPE_SEQUENCE,
			'children' => [
				'version' => ['type' => ASN1::TYPE_INTEGER],
				'policy' => ['type' => ASN1::TYPE_OBJECT_IDENTIFIER],
				'messageImprint' => [
					'type' => ASN1::TYPE_SEQUENCE,
					'children' => [
						'hashAlgorithm' => [
							'type' => ASN1::TYPE_SEQUENCE,
							'children' => [
								'algorithm' => ['type' => ASN1::TYPE_OBJECT_IDENTIFIER],
								'parameters' => ['optional' => true, 'type' => ASN1::TYPE_ANY],
							],
						],
						'hashedMessage' => ['type' => ASN1::TYPE_OCTET_STRING],
					],
				],
				'serialNumber' => ['type' => ASN1::TYPE_INTEGER],
				'genTime' => ['type' => ASN1::TYPE_GENERALIZED_TIME],
				'accuracy' => [
					'constant' => 0,
					'implicit' => true,
					'optional' => true,
					'type' => ASN1::TYPE_SEQUENCE,
					'children' => [
						'seconds' => ['constant' => 0, 'implicit' => true, 'optional' => true, 'type' => ASN1::TYPE_INTEGER],
						'millis' => ['constant' => 1, 'implicit' => true, 'optional' => true, 'type' => ASN1::TYPE_INTEGER],
						'micros' => ['constant' => 2, 'implicit' => true, 'optional' => true, 'type' => ASN1::TYPE_INTEGER],
					],
				],
				'ordering' => ['type' => ASN1::TYPE_BOOLEAN, 'optional' => true],
				'nonce' => ['type' => ASN1::TYPE_INTEGER, 'optional' => true],
				'tsa' => ['constant' => 0, 'optional' => true, 'implicit' => true, 'type' => ASN1::TYPE_ANY],
				'extensions' => ['constant' => 1, 'optional' => true, 'implicit' => true, 'type' => ASN1::TYPE_ANY],
			],
		];
	}

	public function extract(array $root): array {
		$cmsDer = null;
		$tstInfoOctets = null;
		$cnHints = [];

		$values = $this->getAttributeValuesSetAfterOID($root, self::TIMESTAMP_OIDS['TIME_STAMP_TOKEN']);
		if ($values) {
			foreach ($values as $candidate) {
				if (!isset($candidate['content'])) {
					continue;
				}

				$subtree = $this->processContentCandidate($candidate['content'], $cmsDer);

				if (!empty($subtree)) {
					$tstInfoOctets = $this->findContentAfterOID($subtree, self::TIMESTAMP_OIDS['TST_INFO'], ASN1::TYPE_OCTET_STRING);
					$cnHints = $this->extractCertificateHints($subtree);
					if ($tstInfoOctets) {
						break; // Found what we need, exit early
					}
				}
			}
		}
		if (!isset($tstInfoOctets)) {
			$tstInfoOctets = $this->findContentAfterOID($root, self::TIMESTAMP_OIDS['TST_INFO'], ASN1::TYPE_OCTET_STRING);
			$cnHints = $this->extractCertificateHints($root);
		}

		$tsa = ['genTime' => null, 'policy' => null, 'serialNumber' => null, 'cnHints' => []];

		if ($tstInfoOctets) {
			try {
				$decoded = $this->decodeWithCache($tstInfoOctets);
				$tstNode = $decoded[0] ?? null;

				$tst = null;
				if ($tstNode && ($tstNode['type'] ?? null) === ASN1::TYPE_SEQUENCE) {
					ASN1::setTimeFormat('Y-m-d\TH:i:s\Z');
					$tst = ASN1::asn1map($tstNode, self::$timestampInfoStructure ??= $this->buildTimestampInfoStructure());

					if (!is_array($tst)) {
						$tst = $this->parseTstInfoFallback($tstInfoOctets);
					}
				}
			} catch (\Throwable) {
				$tst = $this->parseTstInfoFallback($tstInfoOctets);
			}

			if (is_array($tst)) {
				$tsa['genTime'] = $tst['genTime'] ?? null;
				$policyOid = $tst['policy'] ?? null;
				$tsa['policy'] = $policyOid;
				$tsa['policyName'] = $this->resolveTsaPolicyName($policyOid);
				$tsa['serialNumber'] = $this->bigToString($tst['serialNumber'] ?? null);

				if (!empty($tst['messageImprint'])) {
					$algOid = $tst['messageImprint']['hashAlgorithm']['algorithm'] ?? null;

					$friendlyName = $this->resolveHashAlgorithm($algOid);

					$numericOid = $this->getNumericOid($algOid);

					$tsa['hashAlgorithm'] = $friendlyName;
					$tsa['hashAlgorithmOID'] = $numericOid;

					$hashed = $tst['messageImprint']['hashedMessage'] ?? null;
					if (is_string($hashed)) {
						$tsa['hashedMessageHex'] = strtoupper(bin2hex($hashed));
					}
				}
				if (!empty($tst['accuracy'])) {
					$acc = $tst['accuracy'];
					$tsa['accuracy'] = [
						'seconds' => isset($acc['seconds']) ? (int)$this->bigToString($acc['seconds']) : null,
						'millis' => isset($acc['millis'])  ? (int)$this->bigToString($acc['millis'])  : null,
						'micros' => isset($acc['micros'])  ? (int)$this->bigToString($acc['micros'])  : null,
					];
				}
				if (array_key_exists('ordering', $tst)) {
					$tsa['ordering'] = (bool)$tst['ordering'];
				}
				if (isset($tst['nonce'])) {
					$tsa['nonce'] = $this->bigToString($tst['nonce']);
				}
				if (isset($tst['tsa'])) {
					$tsa['tsa'] = $this->extractTimestampAuthorityName($tst['tsa']);
				}
			}
		}

		if ($cmsDer) {
			$pem = $this->convertDerToPkcs7Pem($cmsDer);
			$tsaPemCerts = [];
			if (@openssl_pkcs7_read($pem, $tsaPemCerts)) {
				$tsaChain = [];
				foreach ($tsaPemCerts as $idx => $pemCert) {
					$parsed = openssl_x509_parse($pemCert);
					if ($parsed) {
						$tsaChain[$idx] = $parsed;
					}
				}
				$tsa['chain'] = array_values($tsaChain);
			}
		}

		$tsa['cnHints'] = $cnHints;
		$tsa['displayName'] = $this->generateDistinguishedNames($cnHints);
		$tsa['genTime'] = $tsa['genTime'] ? new \DateTime($tsa['genTime']) : null;

		return $tsa;
	}

	public function getSigninTime($root): ?\DateTime {
		$signingTime = null;
		if ($values = $this->getAttributeValuesSetAfterOID($root, self::TIMESTAMP_OIDS['SIGNING_TIME'])) {
			foreach ($values as $v) {
				$t = $v['type'] ?? null;
				if ($t === ASN1::TYPE_UTC_TIME || $t === ASN1::TYPE_GENERALIZED_TIME) {
					$signingTime = $v['content'] ?? null;
					if ($signingTime !== null) {
						break;
					}
				}
			}
		}
		return $signingTime;
	}

	private function parseTstInfoFallback(string $tstInfoOctets): ?array {
		try {
			$nodes = $this->decodeWithCache($tstInfoOctets);
			$root = $nodes[0] ?? null;
			if (!$root || ($root['type'] ?? null) !== ASN1::TYPE_SEQUENCE || !is_array($root['content'] ?? null)) {
				return null;
			}
		} catch (\Throwable) {
			return null;
		}
		$out = ['policy' => null, 'serialNumber' => null, 'genTime' => null];

		$seenPolicy = false;
		$seenMsgImprint = false;
		$seenSerial = false;

		foreach ($root['content'] as $child) {
			$t = $child['type'] ?? null;

			if (!$seenPolicy && $t === ASN1::TYPE_OBJECT_IDENTIFIER && is_string($child['content'] ?? null)) {
				$out['policy'] = $child['content'];
				$seenPolicy = true;
				continue;
			}
			if (!$seenMsgImprint && $t === ASN1::TYPE_SEQUENCE && is_array($child['content'] ?? null)) {
				$hasOID = false;
				$hasOctet = false;
				foreach ($child['content'] as $miPart) {
					if (($miPart['type'] ?? null) === ASN1::TYPE_SEQUENCE) {
						foreach (($miPart['content'] ?? []) as $algPart) {
							if (($algPart['type'] ?? null) === ASN1::TYPE_OBJECT_IDENTIFIER) {
								$hasOID = true;
							}
						}
					}
					if (($miPart['type'] ?? null) === ASN1::TYPE_OCTET_STRING) {
						$hasOctet = true;
					}
				}
				if ($hasOID && $hasOctet) {
					$seenMsgImprint = true;
					continue;
				}
			}
			if ($seenMsgImprint && !$seenSerial && $t === ASN1::TYPE_INTEGER) {
				$out['serialNumber'] = $this->bigToString($child['content'] ?? null);
				$seenSerial = true;
				continue;
			}
			if ($t === ASN1::TYPE_GENERALIZED_TIME || $t === ASN1::TYPE_UTC_TIME) {
				if (is_string($child['content'] ?? null)) {
					$out['genTime'] = $child['content'];
				}
			}
		}

		if (!$out['genTime']) {
			foreach ($this->walkAsn1Tree([$root]) as $n) {
				$tt = $n['type'] ?? null;
				if (($tt === ASN1::TYPE_GENERALIZED_TIME || $tt === ASN1::TYPE_UTC_TIME) && is_string($n['content'] ?? null)) {
					$out['genTime'] = $n['content'];
					break;
				}
			}
		}
		return $out;
	}

	private function getAttributeValuesSetAfterOID(array $tree, string $oid): ?array {
		$seen = false;
		foreach ($this->walkAsn1Tree($tree) as $n) {
			if (($n['type'] ?? null) === ASN1::TYPE_OBJECT_IDENTIFIER && ($n['content'] ?? null) === $oid) {
				$seen = true;
				continue;
			}
			if ($seen && ($n['type'] ?? null) === ASN1::TYPE_SET && isset($n['content']) && is_array($n['content'])) {
				return $n['content'];
			}
		}
		return null;
	}

	private function findContentAfterOID(array $tree, string $oid, int $expectedType): ?string {
		$seen = false;
		foreach ($this->walkAsn1Tree($tree) as $n) {
			if (($n['type'] ?? null) === ASN1::TYPE_OBJECT_IDENTIFIER
				&& ($n['content'] ?? null) === $oid
			) {
				$seen = true;
				continue;
			}
			if ($seen
				&& ($n['type'] ?? null) === $expectedType
				&& is_string($n['content'] ?? null)
			) {
				return $n['content'];
			}
		}
		return null;
	}

	private function extractCertificateHints(array $asn1Tree): array {
		$certificateHints = [];
		$currentAttributeOid = null;

		foreach ($this->walkAsn1Tree($asn1Tree) as $node) {
			if (($node['type'] ?? null) === ASN1::TYPE_OBJECT_IDENTIFIER
				&& isset($node['content'], self::CERTIFICATE_ATTRIBUTE_OIDS[$node['content']])) {
				$currentAttributeOid = $node['content'];
				continue;
			}

			if ($currentAttributeOid !== null
				&& isset($node['content'])
				&& is_string($node['content'])
				&& $this->isStringValidUtf8($node['content'])
			) {
				$certificateHints[self::CERTIFICATE_ATTRIBUTE_OIDS[$currentAttributeOid]] = $node['content'];
				$currentAttributeOid = null;
			}
		}
		return $certificateHints;
	}

	private function isStringValidUtf8(string $text): bool {
		return mb_check_encoding($text, 'UTF-8')
			&& preg_match('/[\P{C}]/u', $text)
			&& !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text);
	}

	private function generateDistinguishedNames(array $hints): string {
		$mapping = [
			'countryName' => 'C',
			'stateOrProvinceName' => 'ST',
			'localityName' => 'L',
			'organizationName' => 'O',
			'organizationalUnitName' => 'OU',
			'commonName' => 'CN',
			'description' => 'description',
			'emailAddress' => 'emailAddress',
		];

		$parts = array_filter(
			array_map(
				fn ($field) => empty($hints[$field])
					? null
					: $mapping[$field] . '=' . addcslashes($hints[$field], '/+<>"#;'),
				array_keys($mapping)
			)
		);

		return '/' . implode('/', $parts);
	}

	private function bigToString($v): ?string {
		return match (true) {
			$v === null => null,
			$v instanceof BigInteger => $v->toString(),
			is_int($v) => (string)$v,
			is_string($v) => ctype_digit($v) ? $v : null,
			is_array($v) && isset($v['content']) => $this->bigToString($v['content']),
			default => null,
		};
	}

	private function resolveHashAlgorithm(?string $oid): ?string {
		return match ($oid) {
			null => null,
			'1.3.14.3.2.26' => 'SHA-1',
			'2.16.840.1.101.3.4.2.4' => 'SHA-224',
			'2.16.840.1.101.3.4.2.1' => 'SHA-256',
			'2.16.840.1.101.3.4.2.2' => 'SHA-384',
			'2.16.840.1.101.3.4.2.3' => 'SHA-512',
			'1.2.840.113549.2.5' => 'MD5',
			'1.2.840.113549.2.2' => 'MD2',
			'id-sha1', 'sha1withrsaencryption', 'ecdsa-with-sha1', 'id-dsa-with-sha1' => 'SHA-1',
			'id-sha224', 'sha224withrsaencryption', 'ecdsa-with-sha224', 'id-dsa-with-sha224' => 'SHA-224',
			'id-sha256', 'sha256withrsaencryption', 'ecdsa-with-sha256', 'id-dsa-with-sha256' => 'SHA-256',
			'id-sha384', 'sha384withrsaencryption', 'ecdsa-with-sha384' => 'SHA-384',
			'id-sha512', 'sha512withrsaencryption', 'ecdsa-with-sha512' => 'SHA-512',
			'md2', 'md2withrsaencryption' => 'MD2',
			'md5', 'md5withrsaencryption' => 'MD5',
			default => $oid, // Return original if not mapped
		};
	}

	private function getNumericOid(?string $oid): ?string {
		if (!$oid || preg_match('/^\d+(\.\d+)*$/', $oid)) {
			return $oid;
		}

		return match ($oid) {
			'id-sha1', 'sha1withrsaencryption', 'ecdsa-with-sha1', 'id-dsa-with-sha1' => '1.3.14.3.2.26',
			'id-sha224', 'sha224withrsaencryption', 'ecdsa-with-sha224', 'id-dsa-with-sha224' => '2.16.840.1.101.3.4.2.4',
			'id-sha256', 'sha256withrsaencryption', 'ecdsa-with-sha256', 'id-dsa-with-sha256' => '2.16.840.1.101.3.4.2.1',
			'id-sha384', 'sha384withrsaencryption', 'ecdsa-with-sha384' => '2.16.840.1.101.3.4.2.2',
			'id-sha512', 'sha512withrsaencryption', 'ecdsa-with-sha512' => '2.16.840.1.101.3.4.2.3',
			'md2', 'md2withrsaencryption' => '1.2.840.113549.2.2',
			'md5', 'md5withrsaencryption' => '1.2.840.113549.2.5',
			default => $oid, // Return original if not mapped
		};
	}

	private function resolveTsaPolicyName(?string $policyOid): ?string {
		if (!$policyOid) {
			return null;
		}

		$resolved = ASN1::getOID($policyOid);
		if ($resolved && $resolved !== $policyOid) {
			return $resolved;
		}

		return match ($policyOid) {
			'1.2.3.4.1' => 'FreeTSA Policy',
			'1.3.6.1.4.1.601.10.3.1' => 'VeriSign TSA Policy',
			'1.3.6.1.4.1.311.3.2.1' => 'Microsoft TSA Policy',
			'2.16.840.1.114412.7.1' => 'DigiCert TSA Policy',
			'1.3.6.1.4.1.8302.3.1' => 'Comodo TSA Policy',
			'2.16.840.1.113733.1.7.23.3' => 'Symantec TSA Policy',
			default => null,
		};
	}

	private function decodeWithCache(string $asn1Data): array {
		$cacheKey = hash('xxh3', $asn1Data);

		if (isset(self::$asn1DecodingCache[$cacheKey])) {
			return self::$asn1DecodingCache[$cacheKey];
		}

		$decodedResult = ASN1::decodeBER($asn1Data);
		if ($decodedResult === null) {
			$decodedResult = [];
		}

		if (count(self::$asn1DecodingCache) >= self::CACHE_SIZE_LIMIT) {
			array_shift(self::$asn1DecodingCache);
		}

		self::$asn1DecodingCache[$cacheKey] = $decodedResult;
		return $decodedResult;
	}

	public static function clearCache(): void {
		self::$asn1DecodingCache = [];
	}

	private function walkAsn1Tree(array $nodes): \Generator {
		$processingStack = $nodes;

		while (!empty($processingStack)) {
			$currentNode = array_shift($processingStack);
			yield $currentNode;

			foreach (['content', 'children'] as $childrenKey) {
				if (isset($currentNode[$childrenKey]) && is_array($currentNode[$childrenKey])) {
					array_unshift($processingStack, ...$currentNode[$childrenKey]);
				}
			}
		}
	}
}
