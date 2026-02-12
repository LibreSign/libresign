<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Crl;

use OCA\Libresign\Service\Crl\CrlUrlParserService;
use OCP\IURLGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CrlUrlParserServiceTest extends TestCase {
	private IURLGenerator $urlGenerator;
	private CrlUrlParserService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->urlGenerator = \OCP\Server::get(IURLGenerator::class);
		$this->service = new CrlUrlParserService($this->urlGenerator);
	}

	#[DataProvider('validUrlProvider')]
	public function testParseValidUrls(string $path, array $expected): void {
		$crlUrl = $this->buildUrl($path);
		$result = $this->service->parseUrl($crlUrl);

		$this->assertNotNull($result);
		$this->assertSame($expected['instanceId'], $result['instanceId']);
		$this->assertSame($expected['generation'], $result['generation']);
		$this->assertSame($expected['engineType'], $result['engineType']);
	}

	#[DataProvider('invalidUrlProvider')]
	public function testParseInvalidUrls(string $path): void {
		$crlUrl = $this->buildUrl($path);
		$result = $this->service->parseUrl($crlUrl);

		$this->assertNull($result, "Expected null for invalid URL: $crlUrl");
	}

	public static function validUrlProvider(): array {
		return [
			'Index.php with OpenSSL engine' => [
				'/index.php/apps/libresign/crl/libresign_g6zm32osou_12_o.crl',
				['instanceId' => 'g6zm32osou', 'generation' => 12, 'engineType' => 'o'],
			],
			'No index.php with OpenSSL engine' => [
				'/apps/libresign/crl/libresign_g6zm32osou_12_o.crl',
				['instanceId' => 'g6zm32osou', 'generation' => 12, 'engineType' => 'o'],
			],
			'CFSSL engine type' => [
				'/apps/libresign/crl/libresign_def456_2_c.crl',
				['instanceId' => 'def456', 'generation' => 2, 'engineType' => 'c'],
			],
			'None engine type' => [
				'/index.php/apps/libresign/crl/libresign_ghi789_3_n.crl',
				['instanceId' => 'ghi789', 'generation' => 3, 'engineType' => 'n'],
			],
			'High generation' => [
				'/index.php/apps/libresign/crl/libresign_abc_999999_o.crl',
				['instanceId' => 'abc', 'generation' => 999999, 'engineType' => 'o'],
			],
			'Short instance id' => [
				'/index.php/apps/libresign/crl/libresign_a_1_o.crl',
				['instanceId' => 'a', 'generation' => 1, 'engineType' => 'o'],
			],
			'Long instance id' => [
				'/apps/libresign/crl/libresign_abcdefghij_1_o.crl',
				['instanceId' => 'abcdefghij', 'generation' => 1, 'engineType' => 'o'],
			],
			'Instance id with digits' => [
				'/index.php/apps/libresign/crl/libresign_abc123def456_1_o.crl',
				['instanceId' => 'abc123def456', 'generation' => 1, 'engineType' => 'o'],
			],
			'Uppercase instance id' => [
				'/index.php/apps/libresign/crl/libresign_ABC123_10_o.crl',
				['instanceId' => 'ABC123', 'generation' => 10, 'engineType' => 'o'],
			],
		];
	}

	public static function invalidUrlProvider(): array {
		return [
			'Non LibreSign URL' => ['/crl/some-other.crl'],
			'Wrong prefix' => ['/index.php/apps/libresign/crl/notlibresign_abc_1_o.crl'],
			'Non numeric generation' => ['/index.php/apps/libresign/crl/libresign_abc_notanumber_o.crl'],
			'Engine type too long' => ['/index.php/apps/libresign/crl/libresign_abc_1_xy.crl'],
			'Wrong file extension' => ['/index.php/apps/libresign/crl/libresign_abc_1_o.crl.bak'],
		];
	}

	private function buildUrl(string $path): string {
		if (str_starts_with($path, 'http')) {
			return $path;
		}

		return $this->urlGenerator->getAbsoluteURL($path);
	}
}
