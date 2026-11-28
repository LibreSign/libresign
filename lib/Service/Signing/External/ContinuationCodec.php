<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing\External;

/** Pure, bounded serialization of encrypted provider continuation attributes. */
final class ContinuationCodec {
	private const MAX_SECRET_JSON = 64 * 1024;

	/** @param array<string, string> $secrets */
	public static function encode(#[\SensitiveParameter] array $secrets): string {
		foreach ($secrets as $key => $value) {
			if (!is_string($key) || preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/D', $key) !== 1
				|| !is_string($value) || $value === '' || strlen($value) > 16384) {
				throw new \InvalidArgumentException('Invalid private continuation data');
			}
		}
		$json = json_encode((object)$secrets, JSON_THROW_ON_ERROR);
		if (strlen($json) > self::MAX_SECRET_JSON) {
			throw new \InvalidArgumentException('Continuation data exceeds size limit');
		}
		return $json;
	}

	/** @return array<string, string> */
	public static function decode(#[\SensitiveParameter] string $json): array {
		if (strlen($json) > self::MAX_SECRET_JSON) {
			throw new \UnexpectedValueException('Private continuation data exceeds size limit');
		}
		$value = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
		if (!is_array($value) || ($value !== [] && array_is_list($value))) {
			throw new \UnexpectedValueException('Invalid continuation data shape');
		}
		foreach ($value as $key => $entry) {
			if (!is_string($key) || !is_string($entry)) {
				throw new \UnexpectedValueException('Invalid continuation data');
			}
		}
		return $value;
	}
}
