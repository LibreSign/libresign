<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\OrganizationSeal;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\OrganizationSeal\OrganizationSealStore;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\IAppConfig;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\Attributes\DataProvider;

final class OrganizationSealStoreTest extends TestCase {
	private IAppConfig $appConfig;
	private int $idCounter = 0;

	public function setUp(): void {
		parent::setUp();
		$this->appConfig = $this->getMockAppConfigWithReset();
	}

	private function getStore(?IAppConfig $appConfig = null): OrganizationSealStore {
		$secureRandom = $this->createMock(ISecureRandom::class);
		$secureRandom->method('generate')->willReturnCallback(fn (): string => 'seal' . ++$this->idCounter);
		return new OrganizationSealStore($appConfig ?? $this->appConfig, $secureRandom);
	}

	public function testAddListsTheSealWithoutItsCredentials(): void {
		$credential = self::generatePkcs12('Example Seal', 'secret');
		$store = $this->getStore();

		$seal = $store->add('  Main seal ', $credential['pfx'], 'secret');

		$this->assertSame('seal1', $seal['id']);
		$this->assertSame('Main seal', $seal['label']);
		$this->assertSame('Example Seal', $seal['subject']);
		$this->assertNotSame('', $seal['validTo']);
		$this->assertSame([$seal], $store->list());
		$index = $this->appConfig->getValueString(Application::APP_ID, OrganizationSealStore::INDEX_APP_CONFIG_KEY);
		$this->assertStringNotContainsString('secret', $index);
		$this->assertStringNotContainsString(base64_encode($credential['pfx']), $index);
	}

	public function testGetCredentialsReturnsTheStoredCertificate(): void {
		$credential = self::generatePkcs12('Example Seal', 'secret');
		$store = $this->getStore();
		$seal = $store->add('Main seal', $credential['pfx'], 'secret');

		$this->assertSame(
			['pfx' => $credential['pfx'], 'password' => 'secret', 'subject' => 'Example Seal'],
			$store->getCredentials($seal['id']),
		);
		$this->assertNull($store->getCredentials('unknown'));
	}

	public function testCredentialsAreStoredAsSensitiveLazyValue(): void {
		$credential = self::generatePkcs12('Example Seal', 'secret');
		$writes = [];
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getValueString')->willReturn('[]');
		$appConfig->method('setValueString')->willReturnCallback(
			function (string $app, string $key, string $value, bool $lazy = false, bool $sensitive = false) use (&$writes): bool {
				$writes[$key] = ['lazy' => $lazy, 'sensitive' => $sensitive];
				return true;
			}
		);

		$this->getStore($appConfig)->add('Main seal', $credential['pfx'], 'secret');

		$this->assertSame(['lazy' => true, 'sensitive' => true], $writes[OrganizationSealStore::CREDENTIAL_APP_CONFIG_KEY_PREFIX . 'seal1']);
		$this->assertSame(['lazy' => true, 'sensitive' => false], $writes[OrganizationSealStore::INDEX_APP_CONFIG_KEY]);
	}

	public function testDeleteRemovesTheSealAndItsCredentials(): void {
		$store = $this->getStore();
		$first = $store->add('First', self::generatePkcs12('First Seal', 'one')['pfx'], 'one');
		$second = $store->add('Second', self::generatePkcs12('Second Seal', 'two')['pfx'], 'two');

		$this->assertTrue($store->delete($first['id']));
		$this->assertFalse($store->delete($first['id']));

		$this->assertSame([$second], $store->list());
		$this->assertFalse($this->appConfig->hasKey(Application::APP_ID, OrganizationSealStore::CREDENTIAL_APP_CONFIG_KEY_PREFIX . $first['id']));
		$this->assertTrue($store->exists($second['id']));
	}

	public function testAddRejectsADuplicateLabel(): void {
		$store = $this->getStore();
		$store->add('Main seal', self::generatePkcs12('Example Seal', 'secret')['pfx'], 'secret');

		$this->expectException(LibresignException::class);
		$store->add('Main seal', self::generatePkcs12('Other Seal', 'secret')['pfx'], 'secret');
	}

	#[DataProvider('providerUnusableCertificate')]
	public function testAddRejectsAnUnusableCertificate(string $label, string $pfx, string $password): void {
		$store = $this->getStore();

		try {
			$store->add($label, $pfx, $password);
			$this->fail('The seal must be refused');
		} catch (LibresignException) {
		}

		$this->assertSame([], $store->list());
	}

	public static function providerUnusableCertificate(): array {
		$valid = self::generatePkcs12('Example Seal', 'secret');
		$expired = self::generatePkcs12('Expired Seal', 'secret', -1);
		return [
			'empty label' => [' ', $valid['pfx'], 'secret'],
			'label too long' => [str_repeat('a', OrganizationSealStore::MAX_LABEL_LENGTH + 1), $valid['pfx'], 'secret'],
			'wrong password' => ['Main seal', $valid['pfx'], 'wrong'],
			'not a PKCS#12 file' => ['Main seal', 'not a certificate', 'secret'],
			'empty file' => ['Main seal', '', 'secret'],
			'expired certificate' => ['Main seal', $expired['pfx'], 'secret'],
		];
	}

	/**
	 * A throwaway self-signed credential.
	 *
	 * @return array{pfx: string}
	 */
	public static function generatePkcs12(string $commonName, string $password, int $days = 1): array {
		$key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
		$csr = openssl_csr_new(['commonName' => $commonName], $key, ['digest_alg' => 'sha256']);
		$certificate = openssl_csr_sign($csr, null, $key, $days, ['digest_alg' => 'sha256'], random_int(1, 0x7fffffff));
		openssl_pkcs12_export($certificate, $pfx, $key, $password);
		return ['pfx' => $pfx];
	}
}
