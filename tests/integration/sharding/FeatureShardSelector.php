<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreSign\Behat\Sharding;

use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Partitions whole feature files using deterministic, weighted assignment.
 *
 * Indexes are zero-based. Assignment is independent of discovery order and
 * does not require Behat, a Nextcloud instance, or worker-specific tags.
 */
final class FeatureShardSelector {
	public function __construct(
		private readonly int $index,
		private readonly int $total,
	) {
		if ($total < 1 || $index < 0 || $index >= $total) {
			throw new InvalidArgumentException('SHARD_TOTAL must be positive and SHARD_INDEX must be between 0 and SHARD_TOTAL - 1.');
		}
	}

	/**
	 * @param array<string, mixed> $environment
	 */
	public static function fromEnvironment(array $environment): self {
		$index = $environment['SHARD_INDEX'] ?? null;
		$total = $environment['SHARD_TOTAL'] ?? null;

		if (!is_string($index) || !is_string($total)) {
			throw new InvalidArgumentException('Both SHARD_INDEX (zero-based) and SHARD_TOTAL must be set.');
		}

		return new self(
			self::parseInteger($index, 0, 'SHARD_INDEX'),
			self::parseInteger($total, 1, 'SHARD_TOTAL'),
		);
	}

	private static function parseInteger(string $value, int $minimum, string $name): int {
		if (!preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $value)) {
			throw new InvalidArgumentException($name . ' must be a decimal integer without signs or whitespace.');
		}

		$integer = filter_var($value, FILTER_VALIDATE_INT);
		if ($integer === false || $integer < $minimum) {
			throw new InvalidArgumentException($name . ' must be an integer greater than or equal to ' . $minimum . '.');
		}

		return $integer;
	}

	/**
	 * @return list<string> Paths relative to the features directory.
	 */
	public static function discover(string $featuresDirectory): array {
		$root = realpath($featuresDirectory);
		if ($root === false || !is_dir($root)) {
			throw new RuntimeException('Feature directory does not exist: ' . $featuresDirectory);
		}

		$files = [];
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
		);

		foreach ($iterator as $file) {
			/** @var SplFileInfo $file */
			if (!$file->isFile() || $file->isLink() || $file->getExtension() !== 'feature') {
				continue;
			}

			$relativePath = substr($file->getPathname(), strlen($root) + 1);
			$files[] = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
		}

		sort($files, SORT_STRING);
		return array_values(array_unique($files));
	}

	/**
	 * Estimate the number of executed scenarios. Each Scenario Outline example
	 * row counts once, while its table header does not. This is only a scheduling
	 * weight: Behat remains responsible for parsing and running the feature.
	 */
	public static function countScenarios(string $featureFile): int {
		$lines = @file($featureFile, FILE_IGNORE_NEW_LINES);
		if ($lines === false) {
			throw new RuntimeException('Unable to read Behat feature: ' . $featureFile);
		}

		$count = 0;
		$isOutline = false;
		$inExamples = false;
		$hasExamplesHeader = false;
		$docStringDelimiter = null;
		foreach ($lines as $line) {
			if (preg_match('/^\s*("""|```)/', $line, $matches)) {
				if ($docStringDelimiter === null) {
					$docStringDelimiter = $matches[1];
				} elseif ($docStringDelimiter === $matches[1]) {
					$docStringDelimiter = null;
				}
				continue;
			}
			if ($docStringDelimiter !== null) {
				continue;
			}
			if (preg_match('/^\s*Scenario:\s*/', $line)) {
				$count++;
				$isOutline = false;
				$inExamples = false;
			} elseif (preg_match('/^\s*Scenario Outline:\s*/', $line)) {
				$isOutline = true;
				$inExamples = false;
			} elseif ($isOutline && preg_match('/^\s*Examples:\s*/', $line)) {
				$inExamples = true;
				$hasExamplesHeader = false;
			} elseif ($inExamples && preg_match('/^\s*\|/', $line)) {
				if ($hasExamplesHeader) {
					$count++;
				} else {
					$hasExamplesHeader = true;
				}
			}
		}

		return $count;
	}

	/**
	 * @param list<string> $features
	 * @param array<string, int> $weights Estimated scenarios per feature
	 * @return list<string>
	 */
	public function select(array $features, array $weights = []): array {
		foreach ($features as $feature) {
			if (!is_string($feature) || $feature === '') {
				throw new InvalidArgumentException('Feature paths must be non-empty strings.');
			}
			$weight = $weights[$feature] ?? 1;
			if (!is_int($weight) || $weight < 0) {
				throw new InvalidArgumentException('Feature weights must be non-negative integers.');
			}
		}

		$features = array_values(array_unique($features));
		// Largest features first; path name is the stable tie-breaker.
		usort($features, static function (string $left, string $right) use ($weights): int {
			$byWeight = ($weights[$right] ?? 1) <=> ($weights[$left] ?? 1);
			return $byWeight !== 0 ? $byWeight : strcmp($left, $right);
		});

		$loads = array_fill(0, $this->total, 0);
		$shards = array_fill(0, $this->total, []);
		foreach ($features as $feature) {
			// Choose the lowest estimated load, preferring the lowest index on ties.
			$index = 0;
			for ($candidate = 1; $candidate < $this->total; $candidate++) {
				if ($loads[$candidate] < $loads[$index]) {
					$index = $candidate;
				}
			}
			$shards[$index][] = $feature;
			$loads[$index] += max(1, $weights[$feature] ?? 1);
		}

		$selected = $shards[$this->index];
		sort($selected, SORT_STRING);
		return $selected;
	}
}
