<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Serves Behat PDF fixtures on a separate PHP built-in server.
 *
 * This must not be the same process as the Nextcloud Behat server, so
 * request-signature can exercise {"url": ...} without nested self-HTTP.
 */
final class FixtureHttpServer {
	private static string $pid = '0';
	private static string $host = '127.0.0.1';
	private static int $port = 0;
	private static string $documentRoot = '';

	public static function start(): void {
		if (self::isRunning()) {
			return;
		}

		self::$documentRoot = realpath(__DIR__ . '/../../../php/fixtures/pdfs');
		if (self::$documentRoot === false || !is_file(self::$documentRoot . '/small_valid.pdf')) {
			throw new RuntimeException('PDF fixture directory or small_valid.pdf is missing.');
		}

		self::$port = self::findOpenPort(self::$host);
		$cmd = sprintf(
			'php -S %s:%d -t %s > /dev/null 2>&1 & echo $!',
			escapeshellarg(self::$host),
			self::$port,
			escapeshellarg(self::$documentRoot)
		);

		self::$pid = trim((string)shell_exec($cmd));
		if (self::$pid === '' || !ctype_digit(self::$pid)) {
			throw new RuntimeException('Failed to start fixture HTTP server process.');
		}

		for ($attempt = 0; $attempt < 30; $attempt++) {
			usleep(100000);
			$socket = @fsockopen(self::$host, self::$port);
			if (is_resource($socket)) {
				fclose($socket);
				register_shutdown_function(static function (): void {
					self::stop();
				});
				return;
			}
		}

		self::stop();
		throw new RuntimeException('Fixture HTTP server did not become ready in time.');
	}

	public static function stop(): void {
		if (self::isRunning()) {
			exec('kill ' . self::$pid);
		}
		self::$pid = '0';
		self::$port = 0;
	}

	public static function getSmallValidPdfUrl(): string {
		if (!self::isRunning() || self::$port <= 0) {
			throw new RuntimeException('Fixture HTTP server is not running.');
		}

		return sprintf('http://%s:%d/small_valid.pdf', self::$host, self::$port);
	}

	private static function isRunning(): bool {
		if (self::$pid === '' || self::$pid === '0') {
			return false;
		}

		exec(sprintf('ps %d', (int)self::$pid), $result);

		return count($result) > 1;
	}

	private static function findOpenPort(string $host): int {
		$server = @stream_socket_server('tcp://' . $host . ':0', $errno, $errstr);
		if ($server === false) {
			throw new RuntimeException('Unable to allocate local port for fixture HTTP server: ' . $errstr);
		}

		$name = stream_socket_get_name($server, false);
		fclose($server);

		if (!is_string($name) || !str_contains($name, ':')) {
			throw new RuntimeException('Unable to detect allocated local port for fixture HTTP server.');
		}

		$parts = explode(':', $name);
		$port = (int)end($parts);
		if ($port <= 0) {
			throw new RuntimeException('Invalid allocated port for fixture HTTP server.');
		}

		return $port;
	}
}
