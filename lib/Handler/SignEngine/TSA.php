<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\SignEngine;

use phpseclib3\File\ASN1;
use phpseclib3\File\ASN1\Element;
use phpseclib3\Math\BigInteger;

class TSA {
	private static bool $oidsLoaded = false;
	private const OID_MAP = [
		'2.5.4.3' => 'commonName',
		'2.5.4.6' => 'countryName',
		'2.5.4.7' => 'localityName',
		'2.5.4.8' => 'stateOrProvinceName',
		'2.5.4.10' => 'organizationName',
		'2.5.4.11' => 'organizationalUnitName',
		'2.5.4.13' => 'description',
		'1.2.840.113549.1.9.1' => 'emailAddress',
	];

	public function __construct() {
		if (!self::$oidsLoaded) {
			ASN1::loadOIDs([
				'md2' => '1.2.840.113549.2.2',
				'md4' => '1.2.840.113549.2.4',
				'md5' => '1.2.840.113549.2.5',
				'id-sha1' => '1.3.14.3.2.26',
				'id-sha256' => '2.16.840.1.101.3.4.2.1',
				'id-sha384' => '2.16.840.1.101.3.4.2.2',
				'id-sha512' => '2.16.840.1.101.3.4.2.3',
				'id-sha224' => '2.16.840.1.101.3.4.2.4',
				'id-sha512/224' => '2.16.840.1.101.3.4.2.5',
				'id-sha512/256' => '2.16.840.1.101.3.4.2.6',

				'id-mgf1' => '1.2.840.113549.1.1.8',
			]);
			self::$oidsLoaded = true;
		}
	}

	private function derPkcs7ToPem(string $der): string {
		$b64 = chunk_split(base64_encode($der), 64, "\n");
		return "-----BEGIN PKCS7-----\n{$b64}-----END PKCS7-----\n";
	}

	private function extractTsaNameFromAny($tsaAny): array {
		if (!$tsaAny instanceof Element || !is_string($tsaAny->element)) {
			return [];
		}
		$node = \phpseclib3\File\ASN1::decodeBER($tsaAny)[0] ?? null;
		if (!$node) {
			return [];
		}
		return $this->collectCNHints([$node]);
	}

	public function extract(array $root): array {
		$cmsDer = null;

		$values = $this->getAttributeValuesSetAfterOID($root, '1.2.840.113549.1.9.16.2.14'); // id-aa-timeStampToken
		if ($values) {
			foreach ($values as $candidate) {
				if (isset($candidate['content'])) {

					if ($candidate['content'] instanceof Element) {
						$cmsDer = $candidate['content']->element;
						$subtree = ASN1::decodeBER($cmsDer);
					} elseif (is_string($candidate['content'])) {
						$cmsDer = $candidate['content'];
						$subtree = ASN1::decodeBER($cmsDer);
					} elseif (is_array($candidate['content'])) {
						$subtree = $candidate['content'];
					} else {
						$subtree = is_array($candidate) ? [$candidate] : [];
					}
				}
				if (!empty($subtree)) {
					$tstInfoOctets = $this->findTstInfoOctetsInTree($subtree);
					$cnHints = $this->collectCNHints($subtree);
					if ($tstInfoOctets) {
						break;
					}
				}
			}
		}
		if (!isset($tstInfoOctets)) {
			$tstInfoOctets = $this->findTstInfoOctetsInTree($root);
			$cnHints = $this->collectCNHints($root);
		}

		$tsa = ['genTime' => null, 'policy' => null, 'serialNumber' => null, 'cnHints' => []];

		if ($tstInfoOctets) {
			$tstNode = ASN1::decodeBER($tstInfoOctets)[0] ?? null;

			$tst = null;
			if ($tstNode && ($tstNode['type'] ?? null) === ASN1::TYPE_SEQUENCE) {
				ASN1::setTimeFormat('Y-m-d\TH:i:s\Z');
				foreach ($this->tstInfoMaps() as $MAP) {
					$tst = ASN1::asn1map($tstNode, $MAP);
					if (is_array($tst)) {
						break;
					}
				}
			}

			if (!is_array($tst)) {
				$tst = $this->parseTstInfoFallback($tstInfoOctets);
			}

			if (is_array($tst)) {
				$tsa['genTime'] = $tst['genTime'] ?? null;
				$tsa['policy'] = $tst['policy'] ?? null;
				$tsa['serialNumber'] = $this->bigToString($tst['serialNumber'] ?? null);

				if (!empty($tst['messageImprint'])) {
					$algOid = $tst['messageImprint']['hashAlgorithm']['algorithm'] ?? null;
					$tsa['hashAlgorithmOID'] = $algOid;
					$tsa['hashAlgorithm'] = ASN1::getOID($algOid) ?? $algOid;
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
					$tsa['tsa'] = $this->extractTsaNameFromAny($tst['tsa']);
				}
			}
		}

		if ($cmsDer) {
			$pem = $this->derPkcs7ToPem($cmsDer);
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
		$tsa['genTime'] = new \DateTime($tsa['genTime']);

		return $tsa;
	}

	public function getSigninTime($root): ?\DateTime {
		$signingTime = null;
		if ($values = $this->getAttributeValuesSetAfterOID($root, '1.2.840.113549.1.9.5')) {
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

	private function tstInfoMaps(): array {
		return [
			$this->mapTSTInfoVariant(true, true),
			$this->mapTSTInfoVariant(true, false),
			$this->mapTSTInfoVariant(false, true),
			$this->mapTSTInfoVariant(false, false),
		];
	}

	private function mapTSTInfoVariant(bool $tsaExplicit, bool $extExplicit): array {
		$tsaTag = $tsaExplicit ? ['explicit' => true] : ['implicit' => true];
		$extTag = $extExplicit ? ['explicit' => true] : ['implicit' => true];

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

				'tsa' => array_merge(['constant' => 0, 'optional' => true, 'type' => ASN1::TYPE_ANY], $tsaTag),

				'extensions' => array_merge(['constant' => 1, 'optional' => true, 'type' => ASN1::TYPE_ANY], $extTag),
			],
		];
	}

	private function parseTstInfoFallback(string $tstInfoOctets): ?array {
		$nodes = ASN1::decodeBER($tstInfoOctets);
		$root = $nodes[0] ?? null;
		if (!$root || ($root['type'] ?? null) !== ASN1::TYPE_SEQUENCE || !is_array($root['content'] ?? null)) {
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
			foreach ($this->asn1Walk([$root]) as $n) {
				$tt = $n['type'] ?? null;
				if (($tt === ASN1::TYPE_GENERALIZED_TIME || $tt === ASN1::TYPE_UTC_TIME) && is_string($n['content'] ?? null)) {
					$out['genTime'] = $n['content'];
					break;
				}
			}
		}
		return $out;
	}

	private function asn1Walk(array $nodes): \Generator {
		foreach ($nodes as $n) {
			yield $n;
			foreach (['content', 'children'] as $k) {
				if (isset($n[$k]) && is_array($n[$k])) {
					yield from $this->asn1Walk($n[$k]);
				}
			}
		}
	}

	private function getAttributeValuesSetAfterOID(array $tree, string $oid): ?array {
		$seen = false;
		foreach ($this->asn1Walk($tree) as $n) {
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

	private function findTstInfoOctetsInTree(array $tree): ?string {
		$seen = false;
		foreach ($this->asn1Walk($tree) as $n) {
			if (($n['type'] ?? null) === ASN1::TYPE_OBJECT_IDENTIFIER
				&& ($n['content'] ?? null) === '1.2.840.113549.1.9.16.1.4'
			) { // id-ct-TSTInfo
				$seen = true;
				continue;
			}
			if ($seen && ($n['type'] ?? null) === ASN1::TYPE_OCTET_STRING && is_string($n['content'] ?? null)) {
				return $n['content'];
			}
		}
		return null;
	}

	private function collectCNHints(array $subtree): array {
		$hints = [];
		$type = null;

		foreach ($this->asn1Walk($subtree) as $n) {
			if (isset($n['type']) && $n['type'] === ASN1::TYPE_OBJECT_IDENTIFIER && isset(self::OID_MAP[$n['content']])) {
				$type = $n['content'];
				continue;
			}

			if ($type !== null && isset($n['content']) && is_string($n['content'])) {
				$txt = $n['content'];
				if (@preg_match('//u', $txt) && preg_match('/[\P{C}]/u', $txt)) {
					$hints[self::OID_MAP[$type]] = $txt;
				}
				$type = null;
			}
		}
		return $hints;
	}

	private function generateDistinguishedNames(array $hints): string {
		$map = [
			'countryName' => 'C',
			'stateOrProvinceName' => 'ST',
			'localityName' => 'L',
			'organizationName' => 'O',
			'organizationalUnitName' => 'OU',
			'commonName' => 'CN',
			'description' => 'description',
			'emailAddress' => 'emailAddress',
		];

		$order = [
			'countryName',
			'stateOrProvinceName',
			'localityName',
			'organizationName',
			'organizationalUnitName',
			'commonName',
			'description',
			'emailAddress',
		];

		$parts = [];
		foreach ($order as $field) {
			if (!empty($hints[$field])) {
				$abbr = $map[$field];
				$val = addcslashes($hints[$field], '/+<>"#;');
				$parts[] = "$abbr=$val";
			}
		}

		return '/' . implode('/', $parts);
	}

	private function bigToString($v): ?string {
		if ($v === null) {
			return null;
		}
		if (is_object($v) && $v instanceof BigInteger) {
			return (string)$v;
		}
		if (is_int($v)) {
			return (string)$v;
		}
		if (is_string($v)) {
			return $v;
		}
		return null;
	}
}
