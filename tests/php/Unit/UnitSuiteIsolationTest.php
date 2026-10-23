<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit;

use OCA\Libresign\Tests\Fixtures\UnitSuiteIsolation\AliasedServerTestCase;
use OCA\Libresign\Tests\Fixtures\UnitSuiteIsolation\ImportedServerTestCase;
use OCA\Libresign\Tests\Fixtures\UnitSuiteIsolation\IndirectServerTestCase;

final class UnitSuiteIsolationTest extends TestCase {
	/**
	 * The server base class cleans the configured data directory after every
	 * test class, so it must never be the parent of a unit test.
	 */
	private const SERVER_TEST_CASE = 'Test\\TestCase';

	public function testUnitTestsDoNotDependOnGlobalNextcloudState(): void {
		$forbidden = [
			'Server' . '::get(' => 'global Nextcloud service lookup',
			'OC' . '::$server' => 'global Nextcloud server container',
			'@group ' . 'DB' => 'real database test group',
			'Group(' . "'DB'" . ')' => 'real database test attribute',
		];

		foreach (self::phpFiles(__DIR__) as $file) {
			$content = file_get_contents($file);
			self::assertIsString($content);

			foreach ($forbidden as $needle => $description) {
				self::assertStringNotContainsString(
					$needle,
					$content,
					sprintf('%s must not use %s', $file, $description),
				);
			}
		}
	}

	public function testUnitTestsDoNotExtendTheServerTestCase(): void {
		self::assertSame([], self::classesExtending(__DIR__, self::SERVER_TEST_CASE));
	}

	public function testDetectsTheServerTestCaseThroughImportsAliasesAndInheritance(): void {
		$found = self::classesExtending(
			dirname(__DIR__) . '/fixtures/UnitSuiteIsolation',
			self::SERVER_TEST_CASE,
		);
		sort($found);

		self::assertSame([
			AliasedServerTestCase::class,
			ImportedServerTestCase::class,
			IndirectServerTestCase::class,
		], $found);
	}

	/**
	 * @return list<string>
	 */
	private static function classesExtending(string $directory, string $parent): array {
		$found = [];
		foreach (self::phpFiles($directory) as $file) {
			foreach (self::declaredClasses($file) as $class) {
				if (!class_exists($class)) {
					require_once $file;
				}
				self::assertTrue(class_exists($class), sprintf('%s must be loadable from %s', $class, $file));
				if (is_subclass_of($class, $parent)) {
					$found[] = $class;
				}
			}
		}
		return $found;
	}

	/**
	 * @return list<string>
	 */
	private static function declaredClasses(string $file): array {
		$content = file_get_contents($file);
		self::assertIsString($content);

		$tokens = array_values(array_filter(
			\PhpToken::tokenize($content),
			static fn (\PhpToken $token): bool => !$token->isIgnorable(),
		));

		$namespace = '';
		$classes = [];
		foreach ($tokens as $index => $token) {
			if ($token->is(T_NAMESPACE)) {
				$name = $tokens[$index + 1] ?? null;
				if ($name?->is([T_NAME_QUALIFIED, T_STRING])) {
					$namespace = $name->text . '\\';
				}
				continue;
			}
			if (!$token->is(T_CLASS)) {
				continue;
			}
			// Skip `Foo::class` and anonymous classes.
			if ($index > 0 && $tokens[$index - 1]->is([T_DOUBLE_COLON, T_NEW])) {
				continue;
			}
			$name = $tokens[$index + 1] ?? null;
			if ($name?->is(T_STRING)) {
				$classes[] = $namespace . $name->text;
			}
		}
		return $classes;
	}

	/**
	 * @return list<string>
	 */
	private static function phpFiles(string $directory): array {
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
		);

		$files = [];
		foreach ($iterator as $file) {
			if ($file->isFile() && $file->getExtension() === 'php') {
				$files[] = $file->getPathname();
			}
		}
		return $files;
	}
}
