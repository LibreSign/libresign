<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use GuzzleHttp\Exception\ConnectException;

/**
 * Retries HTTP calls that fail with a transient connection drop.
 *
 * The PHP built-in server can intermittently close the socket mid-request
 * (cURL error 52 / Empty reply from server) even with a small worker count.
 */
final class TransientConnectionRetry {
	/**
	 * @template T
	 * @param callable(): T $request
	 * @return T
	 */
	public static function run(callable $request, int $maxAttempts = 3, int $baseDelayMicros = 100000): mixed {
		if ($maxAttempts < 1) {
			throw new InvalidArgumentException('maxAttempts must be at least 1');
		}

		$attempt = 0;
		while (true) {
			try {
				return $request();
			} catch (ConnectException $exception) {
				$attempt++;
				if ($attempt >= $maxAttempts) {
					throw $exception;
				}
				fwrite(
					STDERR,
					sprintf(
						"[behat] transient connection error (attempt %d/%d): %s\n",
						$attempt,
						$maxAttempts,
						$exception->getMessage()
					)
				);
				usleep($baseDelayMicros * $attempt);
			}
		}
	}
}
