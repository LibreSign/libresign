<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Composer\Autoload\ClassLoader;

final class UpgradeSafeAutoloader {
	private const AUTOLOAD_FILES_FILE = '/vendor/composer/autoload_files.php';
	private const AUTOLOAD_NAMESPACES_FILE = '/vendor/composer/autoload_namespaces.php';
	private const AUTOLOAD_PSR4_FILE = '/vendor/composer/autoload_psr4.php';
	private const CLASSMAP_FILE = '/vendor/composer/autoload_classmap.php';

	/**
	 * @var array<string, true>
	 */
	private static array $registeredRoots = [];

	private function __construct() {
	}

	public static function register(ClassLoader $loader, string $appRoot): void {
		$normalizedRoot = rtrim($appRoot, DIRECTORY_SEPARATOR);
		if ($normalizedRoot === '' || isset(self::$registeredRoots[$normalizedRoot])) {
			return;
		}

		$replacementLoader = self::buildCurrentLoader($normalizedRoot);
		if (!$replacementLoader instanceof ClassLoader) {
			return;
		}

		$loader->unregister();
		$replacementLoader->register(true);
		self::loadComposerFiles($normalizedRoot);
		self::$registeredRoots[$normalizedRoot] = true;
	}

	public static function registerCurrentAppLoader(string $appRoot): void {
		$loader = self::findRegisteredLoader(rtrim($appRoot, DIRECTORY_SEPARATOR));
		if (!$loader instanceof ClassLoader) {
			return;
		}

		self::register($loader, $appRoot);
	}

	/**
	 * @return array<string, list<string>>|null
	 */
	private static function readPsr0Namespaces(string $appRoot): ?array {
		return self::readComposerArrayFile($appRoot, self::AUTOLOAD_NAMESPACES_FILE);
	}

	/**
	 * @return array<string, list<string>>|null
	 */
	private static function readPsr4Namespaces(string $appRoot): ?array {
		return self::readComposerArrayFile($appRoot, self::AUTOLOAD_PSR4_FILE);
	}

	/**
	 * @return array<string, string>|null
	 */
	private static function readCurrentClassMap(string $appRoot): ?array {
		return self::readComposerArrayFile($appRoot, self::CLASSMAP_FILE);
	}

	/**
	 * @return array<string, string>|null
	 */
	private static function readComposerFiles(string $appRoot): ?array {
		return self::readComposerArrayFile($appRoot, self::AUTOLOAD_FILES_FILE);
	}

	/**
	 * @return array<mixed>|null
	 */
	private static function readComposerArrayFile(string $appRoot, string $relativeFile): ?array {
		$file = $appRoot . $relativeFile;
		if (!is_file($file)) {
			return null;
		}

		$data = require $file;
		if (!is_array($data)) {
			return null;
		}

		return $data;
	}

	private static function buildCurrentLoader(string $appRoot): ?ClassLoader {
		$psr0Namespaces = self::readPsr0Namespaces($appRoot);
		$psr4Namespaces = self::readPsr4Namespaces($appRoot);
		$classMap = self::readCurrentClassMap($appRoot);
		if ($psr0Namespaces === null || $psr4Namespaces === null || $classMap === null) {
			return null;
		}

		$loader = new ClassLoader($appRoot . '/vendor');
		foreach ($psr0Namespaces as $prefix => $paths) {
			if (!is_string($prefix)) {
				continue;
			}
			$normalizedPaths = self::normalizePathList($paths);
			if ($normalizedPaths === []) {
				continue;
			}
			$loader->add($prefix, $normalizedPaths);
		}

		foreach ($psr4Namespaces as $prefix => $paths) {
			if (!is_string($prefix)) {
				continue;
			}
			$normalizedPaths = self::normalizePathList($paths);
			if ($normalizedPaths === []) {
				continue;
			}
			$loader->addPsr4($prefix, $normalizedPaths);
		}

		$loader->addClassMap(self::normalizeClassMap($classMap));

		return $loader;
	}

	/**
	 * @param mixed $paths
	 * @return list<string>
	 */
	private static function normalizePathList(mixed $paths): array {
		if (is_string($paths)) {
			return [$paths];
		}

		if (!is_array($paths)) {
			return [];
		}

		$normalizedPaths = [];
		foreach ($paths as $path) {
			if (is_string($path)) {
				$normalizedPaths[] = $path;
			}
		}

		return $normalizedPaths;
	}

	/**
	 * @param array<mixed> $classMap
	 * @return array<string, string>
	 */
	private static function normalizeClassMap(array $classMap): array {
		$normalizedClassMap = [];
		foreach ($classMap as $class => $path) {
			if (is_string($class) && is_string($path)) {
				$normalizedClassMap[$class] = $path;
			}
		}

		return $normalizedClassMap;
	}

	private static function findRegisteredLoader(string $appRoot): ?ClassLoader {
		$vendorDir = $appRoot . '/vendor';

		return ClassLoader::getRegisteredLoaders()[$vendorDir] ?? null;
	}

	private static function loadComposerFiles(string $appRoot): void {
		$files = self::readComposerFiles($appRoot);
		if ($files === null) {
			return;
		}

		if (!isset($GLOBALS['__composer_autoload_files']) || !is_array($GLOBALS['__composer_autoload_files'])) {
			$GLOBALS['__composer_autoload_files'] = [];
		}

		foreach ($files as $identifier => $file) {
			if (!is_string($identifier) || !is_string($file) || isset($GLOBALS['__composer_autoload_files'][$identifier])) {
				continue;
			}

			$GLOBALS['__composer_autoload_files'][$identifier] = true;
			require $file;
		}
	}
}
