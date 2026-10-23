<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

require $argv[1] . '/lib/base.php';

$pfx = \OCP\Server::get(\OCA\Libresign\Handler\SignEngine\Pkcs12Handler::class)
	->getPfxOfCurrentSigner($argv[2]);
if (!openssl_pkcs12_read($pfx, $contents, $argv[3])) {
	throw new RuntimeException('Cannot read issued PKCS#12 certificate');
}
$publicKey = openssl_pkey_get_public($contents['cert']);
$details = $publicKey === false ? false : openssl_pkey_get_details($publicKey);
if (!is_array($details) || $details['type'] !== OPENSSL_KEYTYPE_RSA || $details['bits'] !== 2048) {
	throw new RuntimeException('Issued certificate must contain an RSA-2048 public key');
}
