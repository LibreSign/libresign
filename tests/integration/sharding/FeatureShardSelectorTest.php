<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreSign\Behat\Sharding\Tests;

require_once __DIR__ . '/FeatureShardSelector.php';

use LibreSign\Behat\Sharding\FeatureShardSelector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FeatureShardSelectorTest extends TestCase {
	#[DataProvider('knownPartitions')]
	public function testKnownPartitionsHaveNoDuplication(int $total, array $expected): void {
		$discovered = ['z.feature', 'd.feature', 'a.feature', 'e.feature', 'b.feature', 'c.feature', 'a.feature'];

		foreach ($expected as $index => $files) {
			$selector = new FeatureShardSelector($index, $total);
			self::assertSame($files, $selector->select($discovered));
			self::assertSame($files, $selector->select(array_reverse($discovered)));
			self::assertSame($files, $selector->select($discovered));
		}
	}

	public static function knownPartitions(): iterable {
		yield 'one shard' => [1, [
			['a.feature', 'b.feature', 'c.feature', 'd.feature', 'e.feature', 'z.feature'],
		]];
		yield 'two shards' => [2, [
			['a.feature', 'c.feature', 'e.feature'],
			['b.feature', 'd.feature', 'z.feature'],
		]];
		yield 'four shards' => [4, [
			['a.feature', 'e.feature'],
			['b.feature', 'z.feature'],
			['c.feature'],
			['d.feature'],
		]];
	}

	#[DataProvider('shardTotals')]
	public function testActualFeatureTreeHasCompleteDisjointUnion(int $total): void {
		$all = FeatureShardSelector::discover(dirname(__DIR__) . '/features');
		self::assertNotEmpty($all);
		self::assertSame(array_values(array_unique($all)), $all);

		$union = [];
		$seen = [];
		foreach (range(0, $total - 1) as $index) {
			$selector = new FeatureShardSelector($index, $total);
			$assigned = $selector->select($all);
			self::assertSame([], array_intersect($seen, $assigned), "Shard $index overlaps previous shards");
			array_push($union, ...$assigned);
			array_push($seen, ...$assigned);
		}
		sort($union, SORT_STRING);
		self::assertSame($all, $union, 'A feature was omitted or assigned more than once');
	}

	public static function shardTotals(): iterable {
		yield 'one' => [1];
		yield 'two' => [2];
		yield 'four' => [4];
	}

	#[DataProvider('shardTotals')]
	public function testEmptyFeatureSetIsEmptyForEveryShard(int $total): void {
		for ($index = 0; $index < $total; $index++) {
			self::assertSame([], (new FeatureShardSelector($index, $total))->select([]));
		}
	}

	#[DataProvider('invalidEnvironments')]
	public function testInvalidEnvironmentIsRejected(array $environment): void {
		$this->expectException(\InvalidArgumentException::class);
		FeatureShardSelector::fromEnvironment($environment);
	}

	public static function invalidEnvironments(): iterable {
		yield 'both missing' => [[]];
		yield 'index missing' => [['SHARD_TOTAL' => '2']];
		yield 'total missing' => [['SHARD_INDEX' => '0']];
		yield 'empty index' => [['SHARD_INDEX' => '', 'SHARD_TOTAL' => '2']];
		yield 'empty total' => [['SHARD_INDEX' => '0', 'SHARD_TOTAL' => '']];
		yield 'negative index' => [['SHARD_INDEX' => '-1', 'SHARD_TOTAL' => '2']];
		yield 'zero total' => [['SHARD_INDEX' => '0', 'SHARD_TOTAL' => '0']];
		yield 'out of range' => [['SHARD_INDEX' => '2', 'SHARD_TOTAL' => '2']];
		yield 'non numeric' => [['SHARD_INDEX' => 'x', 'SHARD_TOTAL' => '2']];
		yield 'fractional' => [['SHARD_INDEX' => '0', 'SHARD_TOTAL' => '2.5']];
		yield 'leading whitespace' => [['SHARD_INDEX' => ' 0', 'SHARD_TOTAL' => '2']];
		yield 'leading zero' => [['SHARD_INDEX' => '00', 'SHARD_TOTAL' => '2']];
		yield 'positive sign' => [['SHARD_INDEX' => '+0', 'SHARD_TOTAL' => '2']];
		yield 'overflow' => [['SHARD_INDEX' => '0', 'SHARD_TOTAL' => '9999999999999999999999']];
	}

	public function testValidEnvironmentSelectsTheRequestedShard(): void {
		$selector = FeatureShardSelector::fromEnvironment(['SHARD_INDEX' => '1', 'SHARD_TOTAL' => '2']);
		self::assertSame(['b.feature'], $selector->select(['a.feature', 'b.feature', 'c.feature']));
	}

	public function testBadFeaturePathIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		(new FeatureShardSelector(0, 1))->select(['a.feature', '']);
	}

	public function testNonStringFeaturePathIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		(new FeatureShardSelector(0, 1))->select(['a.feature', null]);
	}

	public function testDiscoverReturnsSortedRelativePathsOnly(): void {
		$root = sys_get_temp_dir() . '/libresign-shards-' . bin2hex(random_bytes(8));
		self::assertTrue(mkdir($root . '/nested', 0700, true));

		try {
			file_put_contents($root . '/z.feature', 'Feature: Z');
			file_put_contents($root . '/nested/a.feature', 'Feature: A');
			file_put_contents($root . '/nested/ignored.txt', 'not a feature');

			self::assertSame(['nested/a.feature', 'z.feature'], FeatureShardSelector::discover($root));
		} finally {
			unlink($root . '/nested/a.feature');
			unlink($root . '/nested/ignored.txt');
			unlink($root . '/z.feature');
			rmdir($root . '/nested');
			rmdir($root);
		}
	}

	public function testMissingDirectoryIsRejected(): void {
		$this->expectException(\RuntimeException::class);
		FeatureShardSelector::discover(__DIR__ . '/missing-features');
	}

	public function testCliListsOnlyAssignedFeatures(): void {
		$all = FeatureShardSelector::discover(dirname(__DIR__) . '/features');
		$expected = array_map(
			static fn (string $file): string => 'features/' . $file,
			(new FeatureShardSelector(1, 2))->select($all),
		);
		[$exitCode, $stdout, $stderr] = self::runCli('1', '2', '--list');

		self::assertSame(0, $exitCode, $stderr);
		self::assertSame(implode("\n", $expected) . "\n", $stdout);
	}

	public function testCliVerifiesCompletePartitionWithoutNextcloud(): void {
		$count = count(FeatureShardSelector::discover(dirname(__DIR__) . '/features'));
		[$exitCode, $stdout, $stderr] = self::runCli('0', '4', '--verify');

		self::assertSame(0, $exitCode, $stderr);
		self::assertStringContainsString("Verified $count unique features across 4 shard(s), with no omissions or overlaps.", $stdout);
	}

	public function testEmptyShardDoesNotStartBehat(): void {
		$count = count(FeatureShardSelector::discover(dirname(__DIR__) . '/features'));
		[$exitCode, $stdout, $stderr] = self::runCli((string)$count, (string)($count + 1), '--run');

		self::assertSame(0, $exitCode, $stderr);
		self::assertSame('', $stdout);
		self::assertStringContainsString('Behat was not started', $stderr);
	}

	public function testCliRejectsInvalidEnvironment(): void {
		[$exitCode, $stdout, $stderr] = self::runCli('2', '2', '--list');

		self::assertSame(2, $exitCode);
		self::assertSame('', $stdout);
		self::assertStringContainsString('SHARD_INDEX', $stderr);
	}

	/**
	 * @return array{int, string, string}
	 */
	private static function runCli(string $index, string $total, string $mode): array {
		$process = proc_open(
			[PHP_BINARY, '-d', 'memory_limit=128M', __DIR__ . '/behat-shard.php', $mode],
			[['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
			$pipes,
			dirname(__DIR__),
			['SHARD_INDEX' => $index, 'SHARD_TOTAL' => $total],
		);
		self::assertIsResource($process);
		fclose($pipes[0]);
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		return [proc_close($process), $stdout, $stderr];
	}
}
