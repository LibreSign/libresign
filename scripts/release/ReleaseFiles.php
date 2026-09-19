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
		$this->writeJsonVersion($root . '/package.json', $version);
		$this->writeJsonVersion($root . '/package-lock.json', $version);

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

	private function writeJsonVersion(string $path, string $version): void {
		$data = json_decode((string)file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
		if (!is_array($data)) {
			throw new \RuntimeException("Invalid JSON in {$path}");
		}
		$data['version'] = $version;
		file_put_contents(
			$path,
			json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
		);
	}
}
