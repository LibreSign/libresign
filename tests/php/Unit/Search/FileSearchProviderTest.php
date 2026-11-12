<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Search;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Search\FileSearchProvider;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\App\IAppManager;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResultEntry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

class FileSearchProviderTest extends TestCase {
	private const LIMIT = 5;

	private IL10N&MockObject $l10n;
	private IAppManager&MockObject $appManager;
	private SignRequestMapper&MockObject $fileMapper;
	private FileSearchProvider $provider;

	protected function setUp(): void {
		parent::setUp();

		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);

		$this->appManager = $this->createMock(IAppManager::class);
		$this->appManager->method('isEnabledForUser')->willReturn(true);

		$this->fileMapper = $this->createMock(SignRequestMapper::class);

		$this->provider = new FileSearchProvider(
			$this->l10n,
			$this->createMock(IURLGenerator::class),
			$this->createMock(IRootFolder::class),
			$this->appManager,
			$this->createMock(IMimeTypeDetector::class),
			$this->fileMapper,
		);
	}

	/**
	 * Invalid cursors and limits must be rejected before touching the database.
	 */
	#[DataProvider('providerInvalidCursorOrLimit')]
	public function testSearchReturnsEmptyWithoutQueryingTheDatabase(mixed $cursor, int $limit): void {
		$this->fileMapper->expects($this->never())->method('getFilesToSearchProvider');

		$data = $this->provider->search(
			$this->createMock(IUser::class),
			$this->buildQuery($cursor, $limit),
		)->jsonSerialize();

		$this->assertFalse($data['isPaginated']);
		$this->assertEmpty($data['entries']);
	}

	public static function providerInvalidCursorOrLimit(): array {
		return [
			'letters' => ['abc', self::LIMIT],
			'empty string' => ['', self::LIMIT],
			'decimal string' => ['1.5', self::LIMIT],
			'negative string' => ['-1', self::LIMIT],
			'negative int' => [-1, self::LIMIT],
			'zero limit' => ['10', 0],
			'negative limit' => ['10', -3],
			'offset + limit overflows' => [PHP_INT_MAX - 5, self::LIMIT],
			'digits beyond int range' => ['99999999999999999999', self::LIMIT],
		];
	}

	/**
	 * Valid cursors are normalized to an int and the mapper is asked for
	 * one extra row, used only to detect that a next page exists.
	 */
	#[DataProvider('providerValidCursor')]
	public function testSearchAsksTheMapperForOneExtraRow(mixed $cursor, int $expectedOffset): void {
		$user = $this->createMock(IUser::class);

		$this->fileMapper->expects($this->once())
			->method('getFilesToSearchProvider')
			->with($user, 'contrat', self::LIMIT + 1, $expectedOffset)
			->willReturn([]);

		$this->provider->search($user, $this->buildQuery($cursor, self::LIMIT));
	}

	public static function providerValidCursor(): array {
		return [
			'null means first page' => [null, 0],
			'zero' => [0, 0],
			'int' => [10, 10],
			'numeric string' => ['10', 10],
			'largest offset that still fits' => [PHP_INT_MAX - 10, PHP_INT_MAX - 10],
		];
	}

	/**
	 * Pagination metadata: a next page exists only if the mapper returned more
	 * rows than the requested limit.
	 */
	#[DataProvider('providerPagination')]
	public function testSearchPaginationMetadata(int $rowsReturned, int $offset, bool $isPaginated, int $expectedEntries, ?int $expectedCursor): void {
		$this->fileMapper->method('getFilesToSearchProvider')
			->willReturn($this->buildFiles($rowsReturned));

		$data = $this->provider->search(
			$this->createMock(IUser::class),
			$this->buildQuery($offset, self::LIMIT),
		)->jsonSerialize();

		$this->assertSame($isPaginated, $data['isPaginated']);
		$this->assertCount($expectedEntries, $data['entries']);
		$this->assertSame($expectedCursor, $data['cursor']);
	}

	public static function providerPagination(): array {
		return [
			'empty dataset' => [0, 0, false, 0, null],
			'partial page' => [3, 0, false, 3, null],
			'exactly the limit' => [5, 0, false, 5, null],
			'one more than the limit' => [6, 0, true, 5, 5],
			'next page of a multi-page dataset' => [6, 10, true, 5, 15],
			'last page of a multi-page dataset' => [2, 10, false, 2, null],
		];
	}

	/**
	 * The extra row fetched only to detect the next page must never be shown.
	 */
	public function testSearchDoesNotReturnTheExtraRow(): void {
		$this->fileMapper->method('getFilesToSearchProvider')
			->willReturn($this->buildFiles(self::LIMIT + 1));

		$data = $this->provider->search(
			$this->createMock(IUser::class),
			$this->buildQuery(0, self::LIMIT),
		)->jsonSerialize();

		$titles = array_map(
			static fn (SearchResultEntry $entry): string => $entry->jsonSerialize()['title'],
			$data['entries'],
		);
		$this->assertSame(['doc-1.pdf', 'doc-2.pdf', 'doc-3.pdf', 'doc-4.pdf', 'doc-5.pdf'], $titles);
	}

	private function buildQuery(mixed $cursor, int $limit): ISearchQuery&MockObject {
		$query = $this->createMock(ISearchQuery::class);
		$query->method('getTerm')->willReturn('contrat');
		$query->method('getLimit')->willReturn($limit);
		$query->method('getCursor')->willReturn($cursor);
		return $query;
	}

	/**
	 * @return list<File>
	 */
	private function buildFiles(int $count): array {
		$files = [];
		for ($i = 1; $i <= $count; $i++) {
			$file = new File();
			$file->setUserId('user-' . $i);
			$file->setUuid('uuid-' . $i);
			$file->setName('doc-' . $i . '.pdf');
			$file->setNodeId($i);
			$files[] = $file;
		}
		return $files;
	}
}
