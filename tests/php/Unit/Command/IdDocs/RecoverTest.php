<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Command\IdDocs;

use OCA\Libresign\Command\IdDocs\Recover;
use OCA\Libresign\Service\IdDocsRecoveryService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class RecoverTest extends TestCase {
	private function runCommand(array $results, bool $execute, array $input = []): CommandTester {
		$service = $this->createMock(IdDocsRecoveryService::class);
		$service->expects($this->once())->method('run')->with($execute)->willReturn($results);
		$tester = new CommandTester(new Recover($service));
		$tester->execute($input);
		return $tester;
	}

	public function testRunsAsDryRunByDefault(): void {
		$tester = $this->runCommand([
			['idDocId' => 1, 'fileId' => 10, 'nodeId' => 100, 'status' => 'affected', 'reason' => 'stored in unauthenticated appdata'],
			['idDocId' => 2, 'fileId' => 11, 'nodeId' => 101, 'status' => 'already_correct', 'reason' => 'readable in owner storage'],
		], false);

		$this->assertSame(Command::SUCCESS, $tester->getStatusCode());
		$display = $tester->getDisplay();
		$this->assertStringContainsString('Checked 2 identification documents (affected: 1, already_correct: 1).', $display);
		$this->assertStringContainsString('Dry-run: nothing was changed.', $display);
	}

	public function testExecuteOptionRecoversAndShowsTheNewNode(): void {
		$tester = $this->runCommand([
			['idDocId' => 1, 'fileId' => 10, 'nodeId' => 100, 'status' => 'recovered', 'reason' => 'copied to owner storage, original kept', 'newNodeId' => 200],
		], true, ['--execute' => true]);

		$this->assertSame(Command::SUCCESS, $tester->getStatusCode());
		$display = $tester->getDisplay();
		$this->assertMatchesRegularExpression('/\|\s*1\s*\|\s*10\s*\|\s*100\s*\|\s*recovered\s*\|.*\|\s*200\s*\|/', $display);
		$this->assertStringNotContainsString('Dry-run', $display);
	}

	public function testFailureReturnsFailureCodeAndShowsLeftover(): void {
		$tester = $this->runCommand([
			['idDocId' => 1, 'fileId' => 10, 'nodeId' => 100, 'status' => 'failed', 'reason' => 'unexpected error, see the server log', 'leftoverNodeId' => 300],
		], true, ['--execute' => true]);

		$this->assertSame(Command::FAILURE, $tester->getStatusCode());
		$this->assertMatchesRegularExpression('/failed\s*\|.*\|\s*300\s*\|/', $tester->getDisplay());
	}

	public function testReportsWhenThereIsNothingToCheck(): void {
		$tester = $this->runCommand([], false);

		$this->assertSame(Command::SUCCESS, $tester->getStatusCode());
		$this->assertSame('Checked 0 identification documents.' . PHP_EOL, $tester->getDisplay());
	}
}
