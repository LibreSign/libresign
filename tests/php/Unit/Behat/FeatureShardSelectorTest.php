<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\Libresign\Tests\Unit\Behat;

require_once __DIR__ . '/../../../integration/sharding/FeatureShardSelector.php';

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
		$directory = dirname(__DIR__, 3) . '/integration/features';
		$all = FeatureShardSelector::discover($directory);
		self::assertNotEmpty($all);
		self::assertSame(array_values(array_unique($all)), $all);
		$weights = [];
		foreach ($all as $feature) {
			$weights[$feature] = FeatureShardSelector::countScenarios($directory . '/' . $feature);
		}

		$union = [];
		$seen = [];
		$scenarioTotal = 0;
		foreach (range(0, $total - 1) as $index) {
			$selector = new FeatureShardSelector($index, $total);
			$assigned = $selector->select($all, $weights);
			self::assertSame([], array_intersect($seen, $assigned), "Shard $index overlaps previous shards");
			foreach ($assigned as $feature) {
				$scenarioTotal += $weights[$feature];
			}
			array_push($union, ...$assigned);
			array_push($seen, ...$assigned);
		}
		sort($union, SORT_STRING);
		self::assertSame($all, $union, 'A feature was omitted or assigned more than once');
		self::assertSame(array_sum($weights), $scenarioTotal, 'A scenario was omitted or assigned more than once');
	}

	public static function shardTotals(): iterable {
		yield 'one' => [1];
		yield 'two' => [2];
		yield 'four' => [4];
	}

	public function testWeightedFeaturesAreAssignedDeterministically(): void {
		$features = ['z.feature', 'd.feature', 'c.feature', 'a.feature', 'b.feature', 'e.feature'];
		$weights = ['a.feature' => 8, 'b.feature' => 5, 'c.feature' => 3, 'd.feature' => 2, 'e.feature' => 1, 'z.feature' => 1];
		$expected = [
			['a.feature'],
			['b.feature'],
			['c.feature', 'z.feature'],
			['d.feature', 'e.feature'],
		];
		foreach ($expected as $index => $paths) {
			$selector = new FeatureShardSelector($index, 4);
			self::assertSame($paths, $selector->select($features, $weights));
			self::assertSame($paths, $selector->select(array_reverse($features), $weights));
		}
	}

	#[DataProvider('shardTotals')]
	public function testWeightedFeaturesHaveCompleteDisjointUnion(int $total): void {
		$features = [
			'sign/reject.feature',
			'sign/request.feature',
			'policies/signature_rejection_authorization.feature',
			'build/sign.feature',
			'account/signature.feature',
			'file/validate.feature',
		];
		$weights = [
			'sign/reject.feature' => 38,
			'sign/request.feature' => 23,
			'policies/signature_rejection_authorization.feature' => 18,
			'build/sign.feature' => 2,
			'account/signature.feature' => 11,
			'file/validate.feature' => 11,
		];
		$selectedByShard = [];
		for ($index = 0; $index < $total; $index++) {
			$selector = new FeatureShardSelector($index, $total);
			$selectedByShard[$index] = $selector->select($features, $weights);
			self::assertSame($selectedByShard[$index], $selector->select(array_reverse($features), $weights));
		}

		$all = array_merge(...$selectedByShard);
		sort($all, SORT_STRING);
		$expected = $features;
		sort($expected, SORT_STRING);
		self::assertSame($expected, $all);
		if ($total > 1) {
			self::assertContains('sign/reject.feature', $selectedByShard[0]);
			self::assertContains('sign/request.feature', $selectedByShard[1]);
		}
	}

	public function testScenarioCountsIncludeOutlineRowsAndMultipleExamples(): void {
		$path = tempnam(sys_get_temp_dir(), 'libresign-behat-');
		self::assertNotFalse($path);
		try {
			file_put_contents($path, <<<'GHERKIN'
Feature: Count only runnable scenarios
  Scenario: Ordinary
    Given something
  Scenario Outline: Values <value>
    Given value <value>
    Examples:
      | value |
      | one   |
      | two   |
    @extra
    Examples:
      | value |
      | three |
  Scenario: Another
    Given a string:
      """
      Scenario: This is not an actual scenario
      """
GHERKIN);
			self::assertSame(5, FeatureShardSelector::countScenarios($path));
		} finally {
			unlink($path);
		}
	}

	public function testUnreadableFeatureFileIsRejected(): void {
		$this->expectException(\RuntimeException::class);
		FeatureShardSelector::countScenarios(__DIR__ . '/missing.feature');
	}

	public function testZeroScenarioFileIsGivenMinimumWeight(): void {
		$selector = new FeatureShardSelector(0, 1);
		self::assertSame(['empty.feature', 'real.feature'], $selector->select(
			['empty.feature', 'real.feature'],
			['empty.feature' => 0, 'real.feature' => 1],
		));
	}

	public function testNegativeFeatureWeightIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		(new FeatureShardSelector(0, 2))->select(['a.feature'], ['a.feature' => -1]);
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

}
