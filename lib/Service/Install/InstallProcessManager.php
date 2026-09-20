<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Install;

use Closure;
use OC;
use OCA\Libresign\Service\Process\ProcessManager;
use OCA\Libresign\Vendor\Symfony\Component\Process\Process;

class InstallProcessManager {
	private const string PROCESS_SOURCE = 'install';

	/**
	 * @param null|Closure(array<int, string>): Process $processFactory
	 */
	public function __construct(
		private ProcessManager $processManager,
		private ?Closure $processFactory = null,
	) {
	}

	public function start(string $resource, InstallTarget $target): ?int {
		$process = $this->createProcess($this->buildCommand($resource, $target));
		$process->setOptions(['create_new_console' => true]);
		$process->setTimeout(null);
		$process->start();

		$pid = $process->getPid();
		if (!$pid) {
			return null;
		}

		$this->processManager->register(self::PROCESS_SOURCE, $pid, [
			'resource' => $resource,
			'architecture' => $target->architecture(),
			'distro' => $target->distro(),
		]);

		return $pid;
	}

	public function findRunningPid(string $resource, InstallTarget $target, int $pid = 0): int {
		$matchesTarget = static fn (array $entry): bool
			=> ($entry['context']['resource'] ?? '') === $resource
			&& ($entry['context']['architecture'] ?? $target->architecture()) === $target->architecture()
			&& ($entry['context']['distro'] ?? $target->distro()) === $target->distro();

		if ($pid > 0) {
			$registeredPid = $this->processManager->findRunningPid(
				self::PROCESS_SOURCE,
				static fn (array $entry): bool => $entry['pid'] === $pid && $matchesTarget($entry),
			);
			if ($registeredPid > 0) {
				return $registeredPid;
			}

			$this->processManager->unregister(self::PROCESS_SOURCE, $pid);
			return 0;
		}

		return $this->processManager->findRunningPid(self::PROCESS_SOURCE, $matchesTarget);
	}

	/**
	 * @return list<string>
	 */
	public function buildCommand(string $resource, InstallTarget $target): array {
		$command = [
			OC::$SERVERROOT . '/occ',
			'libresign:install',
			'--' . $resource,
			'--architecture=' . $target->architecture(),
		];

		if ($resource === 'java') {
			$command[] = '--distro=' . $target->distro();
		}

		return $command;
	}

	/**
	 * @param list<string> $command
	 */
	private function createProcess(array $command): Process {
		if ($this->processFactory !== null) {
			return ($this->processFactory)($command);
		}
		return new Process($command);
	}
}
