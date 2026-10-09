<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Db;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCP\IUser;
use OCP\Server;

/**
 * @group DB
 */
final class SignRequestMapperSearchPaginationTest extends \OCA\Libresign\Tests\Integration\TestCase {
	private FileMapper $fileMapper;
	private SignRequestMapper $signRequestMapper;
	private IUser $user;
	private string $userId;

	/** @var list<File> */
	private array $insertedFiles = [];

	public function setUp(): void {
		parent::setUp();
		$this->fileMapper = Server::get(FileMapper::class);
		$this->signRequestMapper = Server::get(SignRequestMapper::class);

		$this->userId = 'search-pagination-' . uniqid();
		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn($this->userId);
	}

	public function tearDown(): void {
		foreach ($this->insertedFiles as $file) {
			$this->fileMapper->delete($file);
		}
		$this->insertedFiles = [];
		parent::tearDown();
	}

	/**
	 * Newest first, and files created at the same instant are ordered by
	 * descending id, so the order never changes between two queries.
	 */
	public function testOrderingIsDeterministicWhenCreationTimesAreEqual(): void {
		$expectedIds = $this->insertFilesWithTwoCreationTimes();

		$actualIds = $this->ids($this->signRequestMapper->getFilesToSearchProvider($this->user, '', 100, 0));

		$this->assertSame($expectedIds, $actualIds);
	}

	/**
	 * Reading consecutive pages must return every file exactly once:
	 * no duplicate and no skipped file.
	 */
	public function testConsecutivePagesDoNotDuplicateNorSkipFiles(): void {
		$expectedIds = $this->insertFilesWithTwoCreationTimes();
		$limit = 3;

		$collected = [];
		for ($offset = 0; $offset < count($expectedIds); $offset += $limit) {
			$page = $this->signRequestMapper->getFilesToSearchProvider($this->user, '', $limit, $offset);
			$this->assertLessThanOrEqual($limit, count($page));
			$collected = array_merge($collected, $this->ids($page));
		}

		$this->assertSame($expectedIds, $collected);
		$this->assertSame($collected, array_values(array_unique($collected)));
	}

	/**
	 * The offset is applied as is, even when it is not a multiple of the limit
	 * (the old page-based computation shifted such offsets).
	 */
	public function testOffsetNotMultipleOfLimitIsAppliedDirectly(): void {
		$expectedIds = $this->insertFilesWithTwoCreationTimes();

		$page = $this->signRequestMapper->getFilesToSearchProvider($this->user, '', 3, 2);

		$this->assertSame(array_slice($expectedIds, 2, 3), $this->ids($page));
	}

	public function testLimitIsRespected(): void {
		$this->insertFilesWithTwoCreationTimes();

		$page = $this->signRequestMapper->getFilesToSearchProvider($this->user, '', 4, 0);

		$this->assertCount(4, $page);
	}

	public function testOffsetBeyondTheLastFileReturnsNothing(): void {
		$expectedIds = $this->insertFilesWithTwoCreationTimes();

		$page = $this->signRequestMapper->getFilesToSearchProvider($this->user, '', 3, count($expectedIds));

		$this->assertSame([], $page);
	}

	/**
	 * Inserts 3 recent files and 4 older ones. Inside each group all files
	 * share the exact same creation time.
	 *
	 * @return list<int> ids in the order the search must return them
	 */
	private function insertFilesWithTwoCreationTimes(): array {
		$older = new \DateTime('2026-01-01 10:00:00', new \DateTimeZone('UTC'));
		$newer = new \DateTime('2026-01-02 10:00:00', new \DateTimeZone('UTC'));

		$olderIds = [];
		for ($i = 1; $i <= 4; $i++) {
			$olderIds[] = $this->insertFile('older-' . $i, $older)->getId();
		}
		$newerIds = [];
		for ($i = 1; $i <= 3; $i++) {
			$newerIds[] = $this->insertFile('newer-' . $i, $newer)->getId();
		}

		rsort($newerIds);
		rsort($olderIds);
		return array_merge($newerIds, $olderIds);
	}

	private function insertFile(string $label, \DateTime $createdAt): File {
		$file = new File();
		$file->setNodeId(random_int(100000, 999999999));
		$file->setUserId($this->userId);
		$file->setUuid(uniqid('', true) . '-' . $label);
		$file->setCreatedAt(clone $createdAt);
		$file->setName('search-pagination-' . $label . '.pdf');
		$file->setStatus(FileStatus::DRAFT->value);

		$inserted = $this->fileMapper->insert($file);
		$this->insertedFiles[] = $inserted;
		return $inserted;
	}

	/**
	 * @param list<File> $files
	 * @return list<int>
	 */
	private function ids(array $files): array {
		return array_map(static fn (File $file): int => $file->getId(), $files);
	}
}
