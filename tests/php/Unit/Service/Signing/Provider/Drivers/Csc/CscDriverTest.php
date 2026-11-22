<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing\Provider\Drivers\Csc;

use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscDriver;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

final class CscDriverTest extends TestCase {
	public function testMetadataDescribesOnlyTheSupportedCscV1Fields(): void {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $label): string => $label);
		$metadata = (new CscDriver($l10n))->metadata();

		$this->assertSame(CscDriver::ID, $metadata['id']);
		$this->assertNotEmpty($metadata['name']);
		$this->assertNotEmpty($metadata['description']);
		$this->assertTrue($metadata['experimental']);
		$this->assertSame(['baseUrl', 'accessToken'], array_column($metadata['fields'], 'key'));
		$this->assertCount(2, array_unique(array_column($metadata['fields'], 'key')));
		$this->assertSame([
			'key' => 'baseUrl',
			'label' => 'CSC service URL',
			'type' => 'url',
			'required' => true,
			'secret' => false,
			'validation' => [
				'schemes' => ['https'],
				'allowUserInfo' => false,
			],
		], $metadata['fields'][0]);
		$this->assertSame('password', $metadata['fields'][1]['type']);
		$this->assertFalse($metadata['fields'][1]['required']);
		$this->assertTrue($metadata['fields'][1]['secret']);
		$this->assertArrayNotHasKey('validation', $metadata['fields'][1]);
		$this->assertArrayNotHasKey('value', $metadata['fields'][1]);
		$this->assertArrayNotHasKey('configured', $metadata['fields'][1]);

		$this->assertTrue(class_exists(\SignerPHP\PdfSigner\Infrastructure\Remote\Csc\CscClient::class));
		$this->assertTrue(is_subclass_of(
			\SignerPHP\PdfSigner\Infrastructure\Remote\Csc\CscCertificateDiscovery::class,
			\SignerPHP\PdfSigner\RemoteSigning\Contract\RemoteCertificateDiscoveryInterface::class,
		));
		$this->assertTrue(is_subclass_of(
			\SignerPHP\PdfSigner\Infrastructure\Remote\Csc\CscSignatureProvider::class,
			\SignerPHP\PdfSigner\RemoteSigning\Contract\RemoteSignatureProviderInterface::class,
		));
		$this->assertTrue($metadata['available']);
		$this->assertNull($metadata['unavailableReason']);
	}
}
