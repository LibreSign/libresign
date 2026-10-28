<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

/** Validate gateway handoff before both immediate delivery and queue persistence. */
final readonly class GatewayPayload implements NotificationPayload {
	public function __construct(
		public string $gatewayName,
		public string $body,
		/** @var array<string, mixed> Provider-independent structured delivery options. */
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
		if (array_diff(array_keys($extra), $allowed) !== []
			|| !isset($extra['body_parameters'])
			|| !is_array($extra['body_parameters'])
			|| !array_is_list($extra['body_parameters'])
			|| $extra['body_parameters'] === []) {
			throw new \InvalidArgumentException('Invalid structured gateway options.');
		}
		foreach ($extra['body_parameters'] as $value) {
			if (!is_string($value) || trim($value) === '') {
				throw new \InvalidArgumentException('Invalid gateway body parameters.');
			}
		}
		if (array_key_exists('expected_body_parameter_count', $extra)
			&& (!is_int($extra['expected_body_parameter_count'])
				|| $extra['expected_body_parameter_count'] !== count($extra['body_parameters']))) {
			throw new \InvalidArgumentException('Invalid gateway body parameter count.');
		}
		foreach (['template_name', 'template_language', 'template_category'] as $key) {
			if (array_key_exists($key, $extra) && (!is_string($extra[$key]) || trim($extra[$key]) === '')) {
				throw new \InvalidArgumentException('Invalid gateway template option.');
			}
		}
	}
}
