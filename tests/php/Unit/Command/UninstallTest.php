<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Command;

use OCA\Libresign\Command\Uninstall;
use OCA\Libresign\Service\Install\InstallService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class UninstallTest extends TestCase {
	private InstallService&MockObject $installService;
	private CommandTester $tester;

	#[\Override]
	protected function setUp(): void {
		$this->installService = $this->createMock(InstallService::class);
		$this->installService->method('getAvailableResources')
			->willReturn(['java', 'jsignpdf', 'pdftk', 'cfssl']);

		$this->tester = new CommandTester(new Uninstall(
			$this->installService,
			$this->createMock(LoggerInterface::class),
		));
	}

	#[DataProvider('singleResourceProvider')]
	public function testUninstallsRequestedResource(string $option, string $resource): void {
		$this->installService->expects($this->once())
			->method('uninstall')
			->with($resource);

		$status = $this->tester->execute([$option => true]);

		$this->assertSame(Command::SUCCESS, $status);
	}

	public static function singleResourceProvider(): array {
		return [
			'java' => ['--java', 'java'],
			'jsignpdf' => ['--jsignpdf', 'jsignpdf'],
			'pdftk' => ['--pdftk', 'pdftk'],
			'cfssl' => ['--cfssl', 'cfssl'],
		];
	}

	#[DataProvider('architectureProvider')]
	public function testNormalizesArchitectureAlias(string $input, string $expected): void {
		$this->installService->expects($this->once())
			->method('setArchitecture')
			->with($expected)
			->willReturnSelf();
		$this->installService->expects($this->once())
			->method('uninstall')
			->with('java');

		$status = $this->tester->execute([
			'--java' => true,
			'--architecture' => $input,
		]);

		$this->assertSame(Command::SUCCESS, $status);
	}

	public static function architectureProvider(): array {
		return [
			'x86_64' => ['x86_64', 'x86_64'],
			'amd64' => ['amd64', 'x86_64'],
			'aarch64' => ['aarch64', 'aarch64'],
			'arm64' => ['arm64', 'aarch64'],
		];
	}

	public function testAllUninstallsEveryResourceOnce(): void {
		$resources = ['java', 'jsignpdf', 'pdftk', 'cfssl'];
		$uninstalled = [];
		$this->installService->expects($this->exactly(count($resources)))
			->method('uninstall')
			->willReturnCallback(static function (string $resource) use (&$uninstalled): void {
				$uninstalled[] = $resource;
			});

		$status = $this->tester->execute(['--all' => true]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertSame($resources, $uninstalled);
	}

	public function testFailsWhenNoResourceWasSelected(): void {
		$this->installService->expects($this->never())->method('uninstall');

		$status = $this->tester->execute([]);

		$this->assertSame(Command::FAILURE, $status);
		$this->assertStringContainsString('Please inform what you want to uninstall', $this->tester->getDisplay());
	}
}
