<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace LibreSign\Release;

final class ReleaseFiles {
	public function readVersion(string $root): string {
		$xml = simplexml_load_file($root . '/appinfo/info.xml');
		if ($xml === false) {
			throw new \RuntimeException('Unable to read appinfo/info.xml');
		}
		return (string)$xml->version;
	}

	public function readNextcloudMinVersion(string $root): string {
		$xml = simplexml_load_file($root . '/appinfo/info.xml');
		if ($xml === false) {
			throw new \RuntimeException('Unable to read appinfo/info.xml');
		}
		$version = (string)($xml->dependencies->nextcloud['min-version'] ?? '');
		if ($version === '') {
			throw new \RuntimeException('Missing Nextcloud min-version in appinfo/info.xml');
		}
		return $version;
	}

	public function readPackageVersion(string $root): string {
		return $this->readJsonVersion($root . '/package.json');
	}

	public function readPackageLockVersion(string $root): string {
		return $this->readJsonVersion($root . '/package-lock.json');
	}

	public function assertVersions(string $root, string $expected): void {
		$versions = [
			'info.xml' => $this->readVersion($root),
			'package.json' => $this->readPackageVersion($root),
			'package-lock.json' => $this->readPackageLockVersion($root),
		];

		foreach ($versions as $file => $version) {
			if ($version !== $expected) {
				throw new \RuntimeException(sprintf(
					'Version mismatch: expected %s but %s contains %s',
					$expected,
					$file,
					$version,
				));
			}
		}
	}

	public function apply(string $root, string $version, string $changelog): void {
		$this->writeInfoVersion($root . '/appinfo/info.xml', $version);
		$this->writePackageVersion($root . '/package.json', $version);
		$this->writePackageLockVersion($root . '/package-lock.json', $version);

		$path = $root . '/CHANGELOG.md';
		$current = (string)file_get_contents($path);
		if (preg_match('/^## ' . preg_quote($version, '/') . ' - /m', $current)) {
			throw new \RuntimeException("CHANGELOG.md already contains {$version}");
		}
		file_put_contents($path, rtrim($changelog) . "\n\n" . $current);
	}

	public function changelogSection(string $root, string $version): string {
		$contents = (string)file_get_contents($root . '/CHANGELOG.md');
		$pattern = '/^## ' . preg_quote($version, '/') . ' - [^\n]+\n(?<body>.*?)(?=^## |\z)/ms';
		if (!preg_match($pattern, $contents, $matches)) {
			throw new \RuntimeException("CHANGELOG.md has no release section for {$version}");
		}
		return trim((string)$matches['body']);
	}

	private function readJsonVersion(string $path): string {
		$data = json_decode((string)file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
		if (!is_array($data) || !isset($data['version']) || !is_string($data['version'])) {
			throw new \RuntimeException("Missing version in {$path}");
		}
		return $data['version'];
	}

	private function writeInfoVersion(string $path, string $version): void {
		$contents = (string)file_get_contents($path);
		$updated = preg_replace('#<version>[^<]+</version>#', "<version>{$version}</version>", $contents, 1, $count);
		if ($updated === null || $count !== 1) {
			throw new \RuntimeException('Unable to update appinfo/info.xml version');
		}
		file_put_contents($path, $updated);
	}

	private function writePackageVersion(string $path, string $version): void {
		$contents = (string)file_get_contents($path);
		$updated = preg_replace_callback(
			'/(^\s*"version"\s*:\s*")[^"]+(")/m',
			static fn (array $matches): string => $matches[1] . $version . $matches[2],
			$contents,
			1,
			$count,
		);
		if ($updated === null || $count !== 1) {
			throw new \RuntimeException("Unable to update version in {$path}");
		}
		file_put_contents($path, $updated);
	}

	private function writePackageLockVersion(string $path, string $version): void {
		$contents = (string)file_get_contents($path);

		$updated = preg_replace_callback(
			'/(^\s{2}"version"\s*:\s*")[^"]+(")/m',
			static fn (array $matches): string => $matches[1] . $version . $matches[2],
			$contents,
			1,
			$topLevelCount,
		);
		if ($updated === null || $topLevelCount !== 1) {
			throw new \RuntimeException("Unable to update top-level version in {$path}");
		}

		$updated = preg_replace_callback(
			'/(^\s{6}"version"\s*:\s*")[^"]+(")/m',
			static fn (array $matches): string => $matches[1] . $version . $matches[2],
			$updated,
			1,
			$rootPackageCount,
		);
		if ($updated === null || $rootPackageCount !== 1) {
			throw new \RuntimeException("Unable to update root package version in {$path}");
		}

		file_put_contents($path, $updated);
	}
}
