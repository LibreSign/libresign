<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OpenApiGenerationTest extends TestCase {
	private string $binDir;

	protected function setUp(): void {
		parent::setUp();

		$temporaryFile = tempnam(sys_get_temp_dir(), 'libresign-openapi-');
		self::assertNotFalse($temporaryFile);
		unlink($temporaryFile);
		self::assertTrue(mkdir($temporaryFile, 0700));
		$this->binDir = $temporaryFile;

		file_put_contents($this->binDir . '/generate-spec', <<<'SH'
#!/bin/sh
printf 'spec %s\n' "$*" >> "$COMMAND_LOG"
exit "$SPEC_EXIT"
SH);
		file_put_contents($this->binDir . '/npm', <<<'SH'
#!/bin/sh
printf 'npm %s\n' "$*" >> "$COMMAND_LOG"
exit "$NPM_EXIT"
SH);
		chmod($this->binDir . '/generate-spec', 0700);
		chmod($this->binDir . '/npm', 0700);
	}

	protected function tearDown(): void {
		foreach (['generate-spec', 'npm', 'calls.log'] as $name) {
			$file = $this->binDir . '/' . $name;
			if (is_file($file)) {
				unlink($file);
			}
		}
		rmdir($this->binDir);
		parent::tearDown();
	}

	/**
	 * @return array{int, list<string>}
	 */
	private function runScript(string $name, int $specExit = 0, int $npmExit = 0): array {
		$composer = json_decode(
			(string)file_get_contents(dirname(__DIR__, 3) . '/composer.json'),
			true,
			512,
			JSON_THROW_ON_ERROR,
		);
		self::assertArrayHasKey($name, $composer['scripts']);

		$process = proc_open(
			['/bin/sh', '-c', $composer['scripts'][$name]],
			[0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
			$pipes,
			$this->binDir,
			[
				'PATH' => $this->binDir,
				'SPEC_EXIT' => (string)$specExit,
				'NPM_EXIT' => (string)$npmExit,
				'COMMAND_LOG' => $this->binDir . '/calls.log',
			],
		);
		self::assertIsResource($process);
		fclose($pipes[0]);
		stream_get_contents($pipes[1]);
		stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		$status = proc_close($process);
		$log = $this->binDir . '/calls.log';
		$commands = is_file($log) ? file($log, FILE_IGNORE_NEW_LINES) : [];
		return [$status, $commands];
	}

	public static function fullGenerationProvider(): array {
		return [
			'OpenAPI generation fails' => [23, 0, 23, ['spec --verbose']],
			'TypeScript generation fails' => [0, 37, 37, ['spec --verbose', 'npm run typescript:generate']],
			'both generators succeed' => [0, 0, 0, ['spec --verbose', 'npm run typescript:generate']],
		];
	}

	#[DataProvider('fullGenerationProvider')]
	public function testFullGenerationPropagatesExitStatus(
		int $specExit,
		int $npmExit,
		int $expectedStatus,
		array $expectedCommands,
	): void {
		[$status, $commands] = $this->runScript('openapi', $specExit, $npmExit);
		self::assertSame($expectedStatus, $status);
		self::assertSame($expectedCommands, $commands);
	}

	public function testFullGenerationFailsWithoutNpm(): void {
		unlink($this->binDir . '/npm');
		[$status, $commands] = $this->runScript('openapi');
		self::assertSame(127, $status);
		self::assertSame(['spec --verbose'], $commands);
	}

	public function testSpecificationOnlyWorksWithoutNpm(): void {
		unlink($this->binDir . '/npm');
		[$status, $commands] = $this->runScript('openapi:spec');
		self::assertSame(0, $status);
		self::assertSame(['spec --verbose'], $commands);
	}

	public function testSpecificationOnlyPropagatesFailures(): void {
		[$status, $commands] = $this->runScript('openapi:spec', 23);
		self::assertSame(23, $status);
		self::assertSame(['spec --verbose'], $commands);
	}
}
