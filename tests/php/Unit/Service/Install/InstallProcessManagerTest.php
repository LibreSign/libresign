<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Install;

use OCA\Libresign\Service\Install\InstallProcessManager;
use OCA\Libresign\Service\Install\InstallTarget;
use OCA\Libresign\Service\Process\ProcessManager;
use OCA\Libresign\Vendor\Symfony\Component\Process\Process;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class InstallProcessManagerTest extends TestCase {
	private ProcessManager&MockObject $processManager;

	#[\Override]
	protected function setUp(): void {
		$this->processManager = $this->createMock(ProcessManager::class);
	}

	#[DataProvider('commandProvider')]
	public function testBuildCommand(
		string $resource,
		InstallTarget $target,
		array $expected,
	): void {
		$manager = new InstallProcessManager($this->processManager);

		$this->assertSame($expected, $manager->buildCommand($resource, $target));
	}

	public static function commandProvider(): array {
		return [
			'cfssl x86' => [
				'cfssl',
				InstallTarget::from('amd64', 'linux'),
				[
					\OC::$SERVERROOT . '/occ',
					'libresign:install',
					'--cfssl',
					'--architecture=x86_64',
				],
			],
			'java alpine arm' => [
				'java',
				InstallTarget::from('arm64', 'alpine-linux'),
				[
					\OC::$SERVERROOT . '/occ',
					'libresign:install',
					'--java',
					'--architecture=aarch64',
					'--distro=alpine-linux',
				],
			],
		];
	}

	public function testStartRegistersTargetContext(): void {
		$process = $this->createMock(Process::class);
		$process->expects($this->once())->method('setOptions')->with(['create_new_console' => true]);
		$process->expects($this->once())->method('setTimeout')->with(null);
		$process->expects($this->once())->method('start');
		$process->method('getPid')->willReturn(456);

		$receivedCommand = null;
		$manager = new InstallProcessManager(
			$this->processManager,
			static function (array $command) use (&$receivedCommand, $process): Process {
				$receivedCommand = $command;
				return $process;
			},
		);
		$target = InstallTarget::from('arm64', 'alpine-linux');

		$this->processManager->expects($this->once())
			->method('register')
			->with('install', 456, [
				'resource' => 'java',
				'architecture' => 'aarch64',
				'distro' => 'alpine-linux',
			]);

		$this->assertSame(456, $manager->start('java', $target));
		$this->assertSame([
			\OC::$SERVERROOT . '/occ',
			'libresign:install',
			'--java',
			'--architecture=aarch64',
			'--distro=alpine-linux',
		], $receivedCommand);
	}

	public function testStartReturnsNullWhenPidIsUnavailable(): void {
		$process = $this->createMock(Process::class);
		$process->method('getPid')->willReturn(null);

		$manager = new InstallProcessManager(
			$this->processManager,
			static fn (array $_command): Process => $process,
		);

		$this->processManager->expects($this->never())->method('register');

		$this->assertNull($manager->start('cfssl', InstallTarget::from('x86_64', 'linux')));
	}

	#[DataProvider('pidMatchProvider')]
	public function testFindRunningPidMatchesWholeTarget(
		array $context,
		int $expected,
	): void {
		$target = InstallTarget::from('x86_64', 'linux');
		$manager = new InstallProcessManager($this->processManager);

		$this->processManager->expects($this->once())
			->method('findRunningPid')
			->with('install', $this->callback('is_callable'))
			->willReturnCallback(
				static fn (string $_source, callable $filter): int => $filter([
					'pid' => 123,
					'context' => $context,
					'createdAt' => 123,
				]) ? 123 : 0,
			);

		$this->assertSame($expected, $manager->findRunningPid('cfssl', $target));
	}

	public static function pidMatchProvider(): array {
		return [
			'matching target' => [[
				'resource' => 'cfssl',
				'architecture' => 'x86_64',
				'distro' => 'linux',
			], 123],
			'different architecture' => [[
				'resource' => 'cfssl',
				'architecture' => 'aarch64',
				'distro' => 'linux',
			], 0],
			'different distro' => [[
				'resource' => 'cfssl',
				'architecture' => 'x86_64',
				'distro' => 'alpine-linux',
			], 0],
			'different resource' => [[
				'resource' => 'java',
				'architecture' => 'x86_64',
				'distro' => 'linux',
			], 0],
		];
	}

	public function testFindRequestedPidUnregistersStaleEntry(): void {
		$manager = new InstallProcessManager($this->processManager);
		$this->processManager->method('findRunningPid')->willReturn(0);
		$this->processManager->expects($this->once())
			->method('unregister')
			->with('install', 123);

		$this->assertSame(
			0,
			$manager->findRunningPid('cfssl', InstallTarget::from('x86_64', 'linux'), 123),
		);
	}
}
