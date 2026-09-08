<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Collaboration\Collaborators;

use OC\Collaboration\Collaborators\SearchResult;
use OCA\Libresign\Collaboration\Collaborators\ManualPhonePlugin;
use OCA\Libresign\Service\Identify\SignerSearchContext;
use OCP\IConfig;
use OCP\IPhoneNumberUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ManualPhonePluginTest extends TestCase {
	#[DataProvider('providerValidPhoneNumbers')]
	public function testSearchAddsAnExactManualEntryForAValidPhoneNumber(
		string $method,
		string $search,
		string $rawSearch,
		string $expectedRawSearch,
		string $defaultRegion,
		?string $expectedRegion,
		string $standardFormat,
		string $expectedShareWith,
	): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValueString')
			->with('default_phone_region', '')
			->willReturn($defaultRegion);

		$phoneUtil = $this->createMock(IPhoneNumberUtil::class);
		$phoneUtil->expects($this->once())
			->method('convertToStandardFormat')
			->with($expectedRawSearch, $expectedRegion)
			->willReturn($standardFormat);

		$context = new SignerSearchContext();
		$context->set($method, $search, $rawSearch);

		$plugin = new ManualPhonePlugin($config, $phoneUtil, $context);

		$searchResult = new SearchResult();
		$hasMore = $plugin->search($search, 10, 0, $searchResult);

		$results = $searchResult->asArray();
		$exact = $results['exact']['manual-phone'] ?? [];
		$wide = $results['manual-phone'] ?? [];

		$this->assertFalse($hasMore, 'The manual entry is the only result this plugin can offer');
		$this->assertEmpty($wide, 'A manual entry is always an exact match');
		$this->assertCount(1, $exact);
		$this->assertSame($expectedShareWith, $exact[0]['label']);
		$this->assertSame($expectedShareWith, $exact[0]['shareWithDisplayNameUnique']);
		$this->assertSame($method, $exact[0]['method']);
		$this->assertSame($expectedShareWith, $exact[0]['value']['shareWith']);
		$this->assertSame(ManualPhonePlugin::TYPE_SIGNER_MANUAL_PHONE, $exact[0]['value']['shareType']);
	}

	#[DataProvider('providerSearchesWithoutAPhoneNumberToValidate')]
	public function testSearchDoesNotValidateWhenThereIsNoPhoneNumberToOffer(
		string $method,
		string $search,
		string $contextSearch,
		string $rawSearch,
	): void {
		$config = $this->createMock(IConfig::class);
		$config->expects($this->never())
			->method('getSystemValueString');

		$phoneUtil = $this->createMock(IPhoneNumberUtil::class);
		$phoneUtil->expects($this->never())
			->method('convertToStandardFormat');

		$context = new SignerSearchContext();
		$context->set($method, $contextSearch, $rawSearch);

		$plugin = new ManualPhonePlugin($config, $phoneUtil, $context);

		$searchResult = new SearchResult();
		$hasMore = $plugin->search($search, 10, 0, $searchResult);

		$results = $searchResult->asArray();

		$this->assertFalse($hasMore);
		$this->assertEmpty($results['exact']['manual-phone'] ?? []);
		$this->assertEmpty($results['manual-phone'] ?? []);
	}

	public function testSearchDoesNotOfferANumberRejectedByThePhoneNumberUtil(): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValueString')
			->with('default_phone_region', '')
			->willReturn('BR');

		$phoneUtil = $this->createMock(IPhoneNumberUtil::class);
		$phoneUtil->expects($this->once())
			->method('convertToStandardFormat')
			->with('123', 'BR')
			->willReturn(null);

		$context = new SignerSearchContext();
		$context->set('sms', '123', '123');

		$plugin = new ManualPhonePlugin($config, $phoneUtil, $context);

		$searchResult = new SearchResult();
		$hasMore = $plugin->search('123', 10, 0, $searchResult);

		$results = $searchResult->asArray();

		$this->assertFalse($hasMore);
		$this->assertEmpty($results['exact']['manual-phone'] ?? []);
		$this->assertEmpty($results['manual-phone'] ?? []);
	}

	public static function providerValidPhoneNumbers(): array {
		return [
			'national number with a default region' => [
				'method' => 'whatsapp',
				'search' => '+5521987654321',
				'rawSearch' => '21987654321',
				'expectedRawSearch' => '21987654321',
				'defaultRegion' => 'BR',
				'expectedRegion' => 'BR',
				'standardFormat' => '+5521987654321',
				'expectedShareWith' => '+5521987654321',
			],
			'e164 number without a default region' => [
				'method' => 'sms',
				'search' => '+12025551234',
				'rawSearch' => '+12025551234',
				'expectedRawSearch' => '+12025551234',
				'defaultRegion' => '',
				'expectedRegion' => null,
				'standardFormat' => '+12025551234',
				'expectedShareWith' => '+12025551234',
			],
			'surrounding whitespace is discarded' => [
				'method' => 'signal',
				'search' => '  +5521987654321  ',
				'rawSearch' => "\t21987654321 ",
				'expectedRawSearch' => '21987654321',
				'defaultRegion' => 'BR',
				'expectedRegion' => 'BR',
				'standardFormat' => '+55 21 98765-4321',
				'expectedShareWith' => '+5521987654321',
			],
		];
	}

	public static function providerSearchesWithoutAPhoneNumberToValidate(): array {
		return [
			'method that does not use a phone number' => [
				'method' => 'email',
				'search' => '+5521987654321',
				'contextSearch' => '+5521987654321',
				'rawSearch' => '21987654321',
			],
			'empty search' => [
				'method' => 'sms',
				'search' => '',
				'contextSearch' => '',
				'rawSearch' => '21987654321',
			],
			'search made only of whitespace' => [
				'method' => 'sms',
				'search' => '   ',
				'contextSearch' => '   ',
				'rawSearch' => '21987654321',
			],
			'context without the raw search typed by the user' => [
				'method' => 'sms',
				'search' => '+5521987654321',
				'contextSearch' => '',
				'rawSearch' => '',
			],
		];
	}
}
