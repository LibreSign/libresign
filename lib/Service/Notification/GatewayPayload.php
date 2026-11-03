<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

/** Validate structured gateway options before both immediate delivery and queue persistence. */
final readonly class GatewayPayload implements NotificationPayload {
	private const MAX_PARAMETERS = 32;
	private const MAX_PARAMETER_BYTES = 4096;

	public function __construct(
		public string $gatewayName,
		public string $body,
		/** @var array<string, mixed> Transport options; never authentication credentials. */
		public array $extra = [],
	) {
		$this->validateOptions($extra);
	}

	/** @param array<string, mixed> $extra */
	private function validateOptions(array $extra): void {
		if ($extra === []) {
			return;
		}

		$allowed = ['body_parameters', 'expected_body_parameter_count', 'template_name', 'template_language', 'template_category'];
		if (array_diff(array_keys($extra), $allowed) !== []) {
			throw new \InvalidArgumentException('Unsupported gateway option.');
		}
		$values = $extra['body_parameters'] ?? null;
		if (!is_array($values) || !array_is_list($values) || $values === [] || count($values) > self::MAX_PARAMETERS) {
			throw new \InvalidArgumentException('Invalid ordered gateway body parameters.');
		}
		foreach ($values as $value) {
			if (!is_string($value) || trim($value) === '' || !mb_check_encoding($value, 'UTF-8')
				|| strlen($value) > self::MAX_PARAMETER_BYTES) {
				throw new \InvalidArgumentException('Invalid gateway body parameter.');
			}
		}
		$this->validateExpectedCount($extra, count($values));
		$this->validateTemplateOptions($extra);
	}

	/** @param array<string, mixed> $extra */
	private function validateExpectedCount(array $extra, int $count): void {
		if (!array_key_exists('expected_body_parameter_count', $extra)) {
			return;
		}
		$expected = $extra['expected_body_parameter_count'];
		if (!is_int($expected) || $expected < 1 || $expected !== $count) {
			throw new \InvalidArgumentException('Invalid gateway body parameter count.');
		}
	}

	/** @param array<string, mixed> $extra */
	private function validateTemplateOptions(array $extra): void {
		foreach (['template_name', 'template_language', 'template_category'] as $key) {
			if (!array_key_exists($key, $extra)) {
				continue;
			}
			$value = $extra[$key];
			if (!is_string($value) || trim($value) === '' || strlen($value) > 256
				|| !mb_check_encoding($value, 'UTF-8') || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
				throw new \InvalidArgumentException('Invalid gateway template option.');
			}
		}
	}
}
