<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\CfsslReal;

use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Tests\Integration\TestCase;

/**
 * Runs only in the CFSSL-enabled integration workflow, not the regular PHPUnit suites.
 *
 * @group DB
 */
final class CfsslCertificateProfileTest extends TestCase {
	public function testIssuedLeafCertificateHasRsa2048PublicKey(): void {
		$pfx = \OCP\Server::get(Pkcs12Handler::class)->generateCertificate(
			[
				'host' => 'signer@example.test',
				'uid' => 'account:cfssl-profile-test',
				'name' => 'CFSSL Profile Test',
			],
			'test-signing-password',
			'CFSSL Profile Test',
		);

		$this->assertTrue(openssl_pkcs12_read($pfx, $contents, 'test-signing-password'));
		$publicKey = openssl_pkey_get_public($contents['cert']);
		$this->assertNotFalse($publicKey);
		$keyDetails = openssl_pkey_get_details($publicKey);
		$this->assertIsArray($keyDetails);
		$this->assertSame(OPENSSL_KEYTYPE_RSA, $keyDetails['type']);
		$this->assertSame(2048, $keyDetails['bits']);
	}
}
