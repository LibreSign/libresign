<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Collaboration\Collaborators;

use OC\Collaboration\Collaborators\SearchResult;
use OCA\Libresign\Collaboration\Collaborators\SignerPlugin;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Service\Identify\SignerSearchContext;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SignerPluginTest extends TestCase {
	#[DataProvider('providerSearchScenarios')]
	public function testSearchFiltersResultsCorrectly(
		string $method,
		string $search,
		array $identifiers,
		int $limit,
		int $offset,
		int $expectedWideCount,
		int $expectedExactCount,
		bool $expectedHasMore,
	): void {
		$mapper = $this->createMock(IdentifyMethodMapper::class);
		$mapper->method('searchByIdentifierValue')
			->with($search, 'current', $method, $limit + 1, $offset)
			->willReturn($identifiers);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('current');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$context = new SignerSearchContext();
		$context->set($method, $search);

		$plugin = new SignerPlugin(
			$mapper,
			$context,
			$userSession,
		);

		$searchResult = new SearchResult();
		$hasMore = $plugin->search($search, $limit, $offset, $searchResult);

		$results = $searchResult->asArray();
		$wideResults = $results['signer'] ?? [];
		$exactResults = $results['exact']['signer'] ?? [];

		$this->assertCount($expectedWideCount, $wideResults, 'Wide results count mismatch');
		$this->assertCount($expectedExactCount, $exactResults, 'Exact results count mismatch');
		$this->assertSame($expectedHasMore, $hasMore, 'Has more flag mismatch');
	}

	public function testSearchReturnsEmptyWhenNoIdentifiersFound(): void {
		$mapper = $this->createMock(IdentifyMethodMapper::class);
		$mapper->method('searchByIdentifierValue')
			->willReturn([]);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('current');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$context = new SignerSearchContext();
		$context->set('email', 'nonexistent@example.com');

		$plugin = new SignerPlugin(
			$mapper,
			$context,
			$userSession,
		);

		$searchResult = new SearchResult();
		$hasMore = $plugin->search('nonexistent@example.com', 25, 0, $searchResult);

		$results = $searchResult->asArray();
		$this->assertFalse($hasMore);
		$this->assertEmpty($results['signer'] ?? []);
	}

	public function testSearchSeparatesExactAndWideMatches(): void {
		$identifiers = [
			['identifier_value' => 'exact@example.com', 'identifier_key' => 'email', 'display_name' => 'Exact User'],
			['identifier_value' => 'similar@example.com', 'identifier_key' => 'email', 'display_name' => 'Similar User'],
			['identifier_value' => 'another@example.com', 'identifier_key' => 'email', 'display_name' => 'exact@example.com'],
		];

		$mapper = $this->createMock(IdentifyMethodMapper::class);
		$mapper->method('searchByIdentifierValue')
			->willReturn($identifiers);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('current');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$context = new SignerSearchContext();
		$context->set('email', 'exact@example.com');

		$plugin = new SignerPlugin(
			$mapper,
			$context,
			$userSession,
		);

		$searchResult = new SearchResult();
		$plugin->search('exact@example.com', 25, 0, $searchResult);

		$results = $searchResult->asArray();
		$wideResults = $results['signer'] ?? [];
		$exactResults = $results['exact']['signer'] ?? [];

		$this->assertCount(1, $wideResults, 'Should have 1 wide result');
		$this->assertCount(2, $exactResults, 'Should have 2 exact results (matched by value and display name)');
	}

	public function testSearchIsCaseInsensitive(): void {
		$identifiers = [
			['identifier_value' => 'Test@Example.COM', 'identifier_key' => 'email', 'display_name' => 'Test User'],
		];

		$mapper = $this->createMock(IdentifyMethodMapper::class);
		$mapper->method('searchByIdentifierValue')
			->willReturn($identifiers);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('current');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$context = new SignerSearchContext();
		$context->set('email', 'test@example.com');

		$plugin = new SignerPlugin(
			$mapper,
			$context,
			$userSession,
		);

		$searchResult = new SearchResult();
		$plugin->search('test@example.com', 25, 0, $searchResult);

		$results = $searchResult->asArray();
		$exactResults = $results['exact']['signer'] ?? [];

		$this->assertCount(1, $exactResults, 'Should match case-insensitively');
	}

	public function testSearchHandlesPagination(): void {
		$identifiers = [];
		for ($i = 0; $i < 31; $i++) {
			$identifiers[] = [
				'identifier_value' => 'user' . $i . '@example.com',
				'identifier_key' => 'email',
				'display_name' => 'User ' . $i,
			];
		}

		$mapper = $this->createMock(IdentifyMethodMapper::class);
		$mapper->method('searchByIdentifierValue')
			->with('user', 'current', 'email', 26, 0)
			->willReturn($identifiers);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('current');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$context = new SignerSearchContext();
		$context->set('email', 'user');

		$plugin = new SignerPlugin(
			$mapper,
			$context,
			$userSession,
		);

		$searchResult = new SearchResult();
		$hasMore = $plugin->search('user', 25, 0, $searchResult);

		$results = $searchResult->asArray();
		$allResults = array_merge($results['signer'] ?? [], $results['exact']['signer'] ?? []);

		$this->assertTrue($hasMore, 'Should indicate more results available');
		$this->assertCount(30, $allResults, 'Should return all results after trimming one');
	}

	public function testSearchRespectOffset(): void {
		$identifiers = [
			['identifier_value' => 'user1@example.com', 'identifier_key' => 'email', 'display_name' => 'User 1'],
			['identifier_value' => 'user2@example.com', 'identifier_key' => 'email', 'display_name' => 'User 2'],
			['identifier_value' => 'user3@example.com', 'identifier_key' => 'email', 'display_name' => 'User 3'],
		];

		$mapper = $this->createMock(IdentifyMethodMapper::class);
		$mapper->method('searchByIdentifierValue')
			->with('user', 'current', 'email', 11, 10)
			->willReturn($identifiers);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('current');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$context = new SignerSearchContext();
		$context->set('email', 'user');

		$plugin = new SignerPlugin(
			$mapper,
			$context,
			$userSession,
		);

		$searchResult = new SearchResult();
		$plugin->search('user', 10, 10, $searchResult);

		$results = $searchResult->asArray();
		$allResults = array_merge($results['signer'] ?? [], $results['exact']['signer'] ?? []);

		$this->assertCount(3, $allResults);
	}

	public function testSearchIncludesMethodInResult(): void {
		$identifiers = [
			['identifier_value' => '+5521987776666', 'identifier_key' => 'sms', 'display_name' => 'Mobile User'],
		];

		$mapper = $this->createMock(IdentifyMethodMapper::class);
		$mapper->method('searchByIdentifierValue')
			->willReturn($identifiers);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('current');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$context = new SignerSearchContext();
		$context->set('sms', '+5521987776666');

		$plugin = new SignerPlugin(
			$mapper,
			$context,
			$userSession,
		);

		$searchResult = new SearchResult();
		$plugin->search('+5521987776666', 25, 0, $searchResult);

		$results = $searchResult->asArray();
		$wideResults = $results['signer'] ?? [];
		$exactResults = $results['exact']['signer'] ?? [];
		$items = array_merge($wideResults, $exactResults);

		$this->assertCount(1, $items);
		$this->assertSame('sms', $items[0]['method']);
	}

	public function testSearchReturnsCorrectShareType(): void {
		$identifiers = [
			['identifier_value' => 'test@example.com', 'identifier_key' => 'email', 'display_name' => 'Test User'],
		];

		$mapper = $this->createMock(IdentifyMethodMapper::class);
		$mapper->method('searchByIdentifierValue')
			->willReturn($identifiers);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('current');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$context = new SignerSearchContext();
		$context->set('email', 'test@example.com');

		$plugin = new SignerPlugin(
			$mapper,
			$context,
			$userSession,
		);

		$searchResult = new SearchResult();
		$plugin->search('test@example.com', 25, 0, $searchResult);

		$results = $searchResult->asArray();
		$items = array_merge($results['signer'] ?? [], $results['exact']['signer'] ?? []);

		$this->assertCount(1, $items);
		$this->assertSame(SignerPlugin::TYPE_SIGNER, $items[0]['value']['shareType']);
	}

	public function testSearchUsesContextMethod(): void {
		$mapper = $this->createMock(IdentifyMethodMapper::class);
		$mapper->expects($this->once())
			->method('searchByIdentifierValue')
			->with('test', 'current', 'account', 26, 0)
			->willReturn([]);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('current');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$context = new SignerSearchContext();
		$context->set('account', 'test');

		$plugin = new SignerPlugin(
			$mapper,
			$context,
			$userSession,
		);

		$searchResult = new SearchResult();
		$plugin->search('test', 25, 0, $searchResult);
	}

	public static function providerSearchScenarios(): array {
		return [
			'single exact match' => [
				'method' => 'email',
				'search' => 'test@example.com',
				'identifiers' => [
					['identifier_value' => 'test@example.com', 'identifier_key' => 'email', 'display_name' => 'Test User'],
				],
				'limit' => 25,
				'offset' => 0,
				'expectedWideCount' => 0,
				'expectedExactCount' => 1,
				'expectedHasMore' => false,
			],
			'multiple matches' => [
				'method' => 'email',
				'search' => 'test',
				'identifiers' => [
					['identifier_value' => 'test1@example.com', 'identifier_key' => 'email', 'display_name' => 'Test User 1'],
					['identifier_value' => 'test2@example.com', 'identifier_key' => 'email', 'display_name' => 'Test User 2'],
				],
				'limit' => 25,
				'offset' => 0,
				'expectedWideCount' => 2,
				'expectedExactCount' => 0,
				'expectedHasMore' => false,
			],
			'mixed exact and wide' => [
				'method' => 'email',
				'search' => 'test@example.com',
				'identifiers' => [
					['identifier_value' => 'test@example.com', 'identifier_key' => 'email', 'display_name' => 'Test User'],
					['identifier_value' => 'testing@example.com', 'identifier_key' => 'email', 'display_name' => 'Similar User'],
				],
				'limit' => 25,
				'offset' => 0,
				'expectedWideCount' => 1,
				'expectedExactCount' => 1,
				'expectedHasMore' => false,
			],
			'no matches' => [
				'method' => 'email',
				'search' => 'nonexistent',
				'identifiers' => [],
				'limit' => 25,
				'offset' => 0,
				'expectedWideCount' => 0,
				'expectedExactCount' => 0,
				'expectedHasMore' => false,
			],
			'phone method' => [
				'method' => 'sms',
				'search' => '+5521987776666',
				'identifiers' => [
					['identifier_value' => '+5521987776666', 'identifier_key' => 'sms', 'display_name' => 'Mobile User'],
				],
				'limit' => 25,
				'offset' => 0,
				'expectedWideCount' => 0,
				'expectedExactCount' => 1,
				'expectedHasMore' => false,
			],
			'account method' => [
				'method' => 'account',
				'search' => 'john',
				'identifiers' => [
					['identifier_value' => 'john', 'identifier_key' => 'account', 'display_name' => 'John Doe'],
				],
				'limit' => 25,
				'offset' => 0,
				'expectedWideCount' => 0,
				'expectedExactCount' => 1,
				'expectedHasMore' => false,
			],
		];
	}
}
