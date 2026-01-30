<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Collaboration\Collaborators;

use OCA\Libresign\Collaboration\Collaborators\ManualPhonePlugin;
use OCA\Libresign\Service\Identify\SignerSearchContext;
use OC\Collaboration\Collaborators\SearchResult;
use OCP\IConfig;
use OCP\IPhoneNumberUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ManualPhonePluginTest extends TestCase {
	#[DataProvider('providerSearchScenarios')]
	public function testSearchReturnsManualEntryOnlyWhenValid(
		string $method,
		string $rawSearch,
		?string $normalized,
		string $defaultRegion,
		?string $expectedRegionParam,
		int $expectedCount,
	): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValueString')
			->with('default_phone_region', '')
			->willReturn($defaultRegion);

		$phoneUtil = $this->createMock(IPhoneNumberUtil::class);
		$phoneUtil->method('convertToStandardFormat')
			->with($rawSearch, $expectedRegionParam)
			->willReturn($normalized);

		$context = new SignerSearchContext();
		$context->set($method, $normalized ?? '', $rawSearch);

		$plugin = new ManualPhonePlugin($config, $phoneUtil, $context);

		$searchResult = new SearchResult();
		$plugin->search($normalized ?? '', 10, 0, $searchResult);

		$results = $searchResult->asArray();
		$items = array_merge($results['manual-phone'] ?? [], $results['exact']['manual-phone'] ?? []);
		$this->assertCount($expectedCount, $items);
		if ($expectedCount > 0) {
			$this->assertSame(ManualPhonePlugin::TYPE_SIGNER_MANUAL_PHONE, $items[0]['value']['shareType']);
			$this->assertSame($normalized, $items[0]['value']['shareWith']);
		}
	}

	public static function providerSearchScenarios(): array {
		return [
			'non phone method' => [
				'method' => 'email',
				'rawSearch' => '21987654321',
				'normalized' => '+5521987654321',
				'defaultRegion' => 'BR',
				'expectedRegionParam' => 'BR',
				'expectedCount' => 0,
			],
			'invalid number' => [
				'method' => 'sms',
				'rawSearch' => '123',
				'normalized' => null,
				'defaultRegion' => 'BR',
				'expectedRegionParam' => 'BR',
				'expectedCount' => 0,
			],
			'valid number' => [
				'method' => 'whatsapp',
				'rawSearch' => '21987654321',
				'normalized' => '+5521987654321',
				'defaultRegion' => 'BR',
				'expectedRegionParam' => 'BR',
				'expectedCount' => 1,
			],
			'valid e164 without default region' => [
				'method' => 'sms',
				'rawSearch' => '+12025551234',
				'normalized' => '+12025551234',
				'defaultRegion' => '',
				'expectedRegionParam' => null,
				'expectedCount' => 1,
			],
		];
	}
}
