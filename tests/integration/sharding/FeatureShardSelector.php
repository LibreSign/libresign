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
 * Partitions whole feature files using their sorted, repository-relative paths.
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
	 * @param list<string> $features
	 * @return list<string>
	 */
	public function select(array $features): array {
		foreach ($features as $feature) {
			if (!is_string($feature) || $feature === '') {
				throw new InvalidArgumentException('Feature paths must be non-empty strings.');
			}
		}

		$features = array_values(array_unique($features));
		sort($features, SORT_STRING);

		$selected = [];
		foreach ($features as $position => $feature) {
			if ($position % $this->total === $this->index) {
				$selected[] = $feature;
			}
		}

		return $selected;
	}
}
