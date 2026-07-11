<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

final class PreparePsalmContext {
	private const REQUIRED_SERVER_PATHS = [
		'lib/OC.php',
		'lib/private',
		'apps/files/lib',
		'apps/files_sharing/lib',
		'apps/settings/lib/Mailer',
		'apps/dav/lib/Connector/Sabre',
		'3rdparty/sabre/dav/lib/DAV',
		'3rdparty/sabre/event/lib',
		'3rdparty/doctrine/dbal/src',
		'3rdparty/stecman/symfony-console-completion/src',
		'core/Command',
	];

	private const SPARSE_CHECKOUT_PATHS = [
		'3rdparty/doctrine/dbal/src',
		'3rdparty/sabre/dav/lib/DAV',
		'3rdparty/sabre/event/lib',
		'3rdparty/stecman/symfony-console-completion/src',
		'apps/dav/lib/Connector/Sabre',
		'apps/files/lib',
		'apps/files_sharing/lib',
		'apps/settings/lib/Mailer',
		'core/Command',
		'lib',
	];

	private const MIRRORED_TOP_LEVELS = [
		'3rdparty',
		'apps',
		'core',
		'lib',
	];

	public static function main(): int {
		try {
			$projectRoot = dirname(__DIR__);
			$sharedRoot = self::getSharedRoot($projectRoot);
			$managedCheckoutRoot = $projectRoot . '/build/psalm-context/server';

			if (self::layoutExists($sharedRoot) && !self::isManagedMirror($managedCheckoutRoot, $sharedRoot)) {
				return 0;
			}

			$serverRoot = self::getServerRoot($projectRoot, $managedCheckoutRoot);
			self::mirrorServerTree($serverRoot, $sharedRoot);
			self::assertLayoutExists($sharedRoot);
			return 0;
		} catch (Throwable $throwable) {
			fwrite(STDERR, '[psalm-context] ' . $throwable->getMessage() . PHP_EOL);
			return 1;
		}
	}

	private static function getSharedRoot(string $projectRoot): string {
		$sharedRoot = getenv('LIBRESIGN_PSALM_SHARED_ROOT');
		if (is_string($sharedRoot) && $sharedRoot !== '') {
			return self::normalizePath($sharedRoot);
		}

		return dirname(dirname($projectRoot));
	}

	private static function getServerRoot(string $projectRoot, string $managedCheckoutRoot): string {
		$serverCheckout = getenv('LIBRESIGN_PSALM_SERVER_CHECKOUT');
		if (is_string($serverCheckout) && $serverCheckout !== '') {
			$serverRoot = self::normalizePath($serverCheckout);
			self::assertLayoutExists($serverRoot);
			return $serverRoot;
		}

		$branch = getenv('LIBRESIGN_PSALM_SERVER_BRANCH');
		if (!is_string($branch) || $branch === '') {
			$branch = self::detectNextcloudServerBranch($projectRoot . '/composer.lock');
		}

		self::prepareManagedCheckout($managedCheckoutRoot, $branch);
		return $managedCheckoutRoot;
	}

	private static function detectNextcloudServerBranch(string $composerLockPath): string {
		if (!is_file($composerLockPath)) {
			throw new RuntimeException('Unable to determine Nextcloud server branch: composer.lock not found.');
		}

		/** @var array{packages?: list<array{name?: string, version?: string, pretty_version?: string}>, packages-dev?: list<array{name?: string, version?: string, pretty_version?: string}>} $composerLock */
		$composerLock = json_decode((string)file_get_contents($composerLockPath), true, 512, JSON_THROW_ON_ERROR);
		$packages = array_merge($composerLock['packages'] ?? [], $composerLock['packages-dev'] ?? []);

		foreach ($packages as $package) {
			if (($package['name'] ?? null) !== 'nextcloud/ocp') {
				continue;
			}

			foreach (['pretty_version', 'version'] as $field) {
				$value = $package[$field] ?? null;
				if (!is_string($value)) {
					continue;
				}

				if (preg_match('/(?:^|\s)dev-([A-Za-z0-9._-]+)(?:$|\s)/', $value, $matches) === 1) {
					return $matches[1];
				}
			}
		}

		throw new RuntimeException('Unable to determine Nextcloud server branch from nextcloud/ocp in composer.lock.');
	}

	private static function prepareManagedCheckout(string $serverRoot, string $branch): void {
		$branchFile = $serverRoot . '/.psalm-branch';
		if (self::preparedCheckoutMatches($serverRoot, $branchFile, $branch)) {
			return;
		}

		self::removeDirectory($serverRoot);
		if (!is_dir(dirname($serverRoot)) && !mkdir(dirname($serverRoot), 0777, true) && !is_dir(dirname($serverRoot))) {
			throw new RuntimeException('Unable to create Psalm context directory.');
		}

		fwrite(STDERR, sprintf('[psalm-context] cloning nextcloud/server (%s) for Psalm context%s', $branch, PHP_EOL));

		self::runCommand(sprintf(
			'git clone --depth 1 --filter=blob:none --sparse --branch %s https://github.com/nextcloud/server.git %s',
			escapeshellarg($branch),
			escapeshellarg($serverRoot),
		));

		self::runCommand(sprintf(
			'git -C %s sparse-checkout set %s',
			escapeshellarg($serverRoot),
			implode(' ', array_map('escapeshellarg', self::SPARSE_CHECKOUT_PATHS)),
		));

		if (file_put_contents($branchFile, $branch . PHP_EOL) === false) {
			throw new RuntimeException('Unable to write Psalm context branch marker.');
		}
	}

	private static function preparedCheckoutMatches(string $serverRoot, string $branchFile, string $branch): bool {
		if (!is_dir($serverRoot) || !is_file($branchFile)) {
			return false;
		}

		$preparedBranch = trim((string)file_get_contents($branchFile));
		return $preparedBranch === $branch && self::layoutExists($serverRoot);
	}

	private static function isManagedMirror(string $managedCheckoutRoot, string $sharedRoot): bool {
		$managedCheckoutRealPath = realpath($managedCheckoutRoot);
		if (!is_string($managedCheckoutRealPath) || $managedCheckoutRealPath === '') {
			return false;
		}

		foreach (self::MIRRORED_TOP_LEVELS as $topLevel) {
			$linkPath = $sharedRoot . '/' . $topLevel;
			if (!is_link($linkPath)) {
				return false;
			}

			$linkTarget = realpath($linkPath);
			if (!is_string($linkTarget) || !str_starts_with($linkTarget, $managedCheckoutRealPath . '/')) {
				return false;
			}
		}

		return true;
	}

	private static function mirrorServerTree(string $serverRoot, string $sharedRoot): void {
		if (!is_dir($sharedRoot) && !mkdir($sharedRoot, 0777, true) && !is_dir($sharedRoot)) {
			throw new RuntimeException('Unable to create shared Psalm root: ' . $sharedRoot);
		}

		foreach (self::MIRRORED_TOP_LEVELS as $topLevel) {
			$targetPath = $serverRoot . '/' . $topLevel;
			$linkPath = $sharedRoot . '/' . $topLevel;

			if (is_link($linkPath)) {
				unlink($linkPath);
			} elseif (file_exists($linkPath)) {
				continue;
			}

			if (!symlink($targetPath, $linkPath)) {
				throw new RuntimeException('Unable to mirror Nextcloud path for Psalm: ' . $linkPath);
			}
		}
	}

	private static function layoutExists(string $rootPath): bool {
		foreach (self::REQUIRED_SERVER_PATHS as $relativePath) {
			if (!file_exists($rootPath . '/' . $relativePath)) {
				return false;
			}
		}

		return true;
	}

	private static function assertLayoutExists(string $rootPath): void {
		foreach (self::REQUIRED_SERVER_PATHS as $relativePath) {
			if (file_exists($rootPath . '/' . $relativePath)) {
				continue;
			}

			throw new RuntimeException(sprintf(
				'Missing Nextcloud server path required by Psalm: %s',
				$rootPath . '/' . $relativePath,
			));
		}
	}

	private static function normalizePath(string $path): string {
		$realPath = realpath($path);
		if (is_string($realPath) && $realPath !== '') {
			return $realPath;
		}

		return rtrim($path, '/');
	}

	private static function removeDirectory(string $path): void {
		if (!file_exists($path)) {
			return;
		}

		if (is_file($path) || is_link($path)) {
			unlink($path);
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST,
		);

		foreach ($iterator as $fileInfo) {
			$filePath = $fileInfo->getPathname();
			if ($fileInfo->isLink() || $fileInfo->isFile()) {
				unlink($filePath);
				continue;
			}

			rmdir($filePath);
		}

		rmdir($path);
	}

	private static function runCommand(string $command): void {
		$output = [];
		$returnCode = 0;
		exec($command . ' 2>&1', $output, $returnCode);
		if ($returnCode === 0) {
			return;
		}

		throw new RuntimeException(sprintf(
			"Command failed (%d): %s\n%s",
			$returnCode,
			$command,
			implode(PHP_EOL, $output),
		));
	}
}

exit(PreparePsalmContext::main());
