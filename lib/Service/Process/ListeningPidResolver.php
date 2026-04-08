<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Process;

use OCA\Libresign\Vendor\Symfony\Component\Process\Process;

class ListeningPidResolver {
	/**
	 * @return int[]
	 */
	public function findListeningPids(int $port): array {
		if ($port <= 0) {
			throw new \RuntimeException('Invalid port to inspect process listeners.');
		}

		$usedStrategy = false;
		$pids = [];

		$ss = $this->findListeningPidsUsingSs($port);
		if ($ss !== null) {
			$usedStrategy = true;
			$pids = array_merge($pids, $ss);
		}

		$lsof = $this->findListeningPidsUsingLsof($port);
		if ($lsof !== null) {
			$usedStrategy = true;
			$pids = array_merge($pids, $lsof);
		}

		$proc = $this->findListeningPidsUsingProc($port);
		if ($proc !== null) {
			$usedStrategy = true;
			$pids = array_merge($pids, $proc);
		}

		if (!$usedStrategy) {
			throw new \RuntimeException('Unable to inspect listening process PIDs: no strategy available.');
		}

		return array_values(array_filter(
			array_unique(array_map('intval', $pids)),
			static fn (int $pid): bool => $pid > 0,
		));
	}

	/**
	 * @return int[]|null null means strategy unavailable
	 */
	protected function findListeningPidsUsingSs(int $port): ?array {
		if (!$this->commandIsAvailable('ss')) {
			return null;
		}

		$process = $this->createProcess(['ss', '-ltnp', 'sport = :' . $port]);
		$process->run();
		if (!$process->isSuccessful()) {
			return [];
		}

		$output = $process->getOutput();
		preg_match_all('/pid=(\d+)/', $output, $matches);
		if (!isset($matches[1]) || !is_array($matches[1])) {
			return [];
		}

		return array_map('intval', $matches[1]);
	}

	/**
	 * @return int[]|null null means strategy unavailable
	 */
	protected function findListeningPidsUsingLsof(int $port): ?array {
		if (!$this->commandIsAvailable('lsof')) {
			return null;
		}

		$process = $this->createProcess(['lsof', '-ti', 'tcp:' . $port, '-sTCP:LISTEN']);
		$process->run();
		if (!$process->isSuccessful()) {
			return [];
		}

		$lines = preg_split('/\R/', trim($process->getOutput()));
		if (!is_array($lines)) {
			return [];
		}

		return array_map('intval', array_filter($lines, static fn (string $line): bool => $line !== ''));
	}

	/**
	 * @return int[]|null null means strategy unavailable
	 */
	protected function findListeningPidsUsingProc(int $port): ?array {
		if (PHP_OS_FAMILY !== 'Linux') {
			return null;
		}

		if (!is_readable('/proc/net/tcp') && !is_readable('/proc/net/tcp6')) {
			return null;
		}

		$inodesByPort = $this->getListeningSocketInodesByPort($port);
		if (empty($inodesByPort)) {
			return [];
		}

		$fdPaths = glob('/proc/[0-9]*/fd/[0-9]*');
		if (!is_array($fdPaths)) {
			return [];
		}

		$pids = [];
		foreach ($fdPaths as $fdPath) {
			$target = @readlink($fdPath);
			if (!is_string($target) || !preg_match('/^socket:\\[(\\d+)\\]$/', $target, $matches)) {
				continue;
			}

			$inode = $matches[1] ?? '';
			if ($inode === '' || !isset($inodesByPort[$inode])) {
				continue;
			}

			if (preg_match('#^/proc/([0-9]+)/fd/[0-9]+$#', $fdPath, $pidMatches)) {
				$pids[] = (int)$pidMatches[1];
			}
		}

		return $pids;
	}

	/**
	 * @return array<string, true>
	 */
	protected function getListeningSocketInodesByPort(int $port): array {
		$portHex = strtoupper(str_pad(dechex($port), 4, '0', STR_PAD_LEFT));
		$inodes = [];

		foreach (['/proc/net/tcp', '/proc/net/tcp6'] as $path) {
			foreach ($this->readListeningInodesFromProcTable($path, $portHex) as $inode) {
				$inodes[$inode] = true;
			}
		}

		return $inodes;
	}

	/**
	 * @return string[]
	 */
	protected function readListeningInodesFromProcTable(string $path, string $portHex): array {
		if (!is_readable($path)) {
			return [];
		}

		$lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if (!is_array($lines) || count($lines) <= 1) {
			return [];
		}

		$inodes = [];
		foreach (array_slice($lines, 1) as $line) {
			$columns = preg_split('/\s+/', trim($line));
			if (!is_array($columns) || count($columns) < 10) {
				continue;
			}

			$localAddress = $columns[1];
			$state = $columns[3];
			$inode = $columns[9] ?? '';

			if ($state !== '0A' || !is_string($inode) || $inode === '') {
				continue;
			}

			$addressParts = explode(':', $localAddress);
			if (count($addressParts) !== 2) {
				continue;
			}

			if (strtoupper($addressParts[1]) !== $portHex) {
				continue;
			}

			$inodes[] = $inode;
		}

		return $inodes;
	}

	protected function commandIsAvailable(string $command): bool {
		$process = $this->createProcess(['/bin/sh', '-lc', 'command -v ' . escapeshellarg($command) . ' >/dev/null 2>&1']);
		$process->run();
		return $process->isSuccessful();
	}

	/**
	 * @param string[] $command
	 */
	protected function createProcess(array $command): Process {
		return new Process($command);
	}
}
