<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

use OCP\IAppConfig;

/**
 * LibreSign owns positional UTILITY template values and their order.
 *
 * Configure both app-config values whatsapp_business_template_name and
 * whatsapp_business_template_language to opt in to structured delivery.
 * Otherwise preserve the existing one-body-value gateway behavior.
 * {{1}} holds one-line notification text; {{2}} holds the action URL.
 */
final class WhatsAppBusinessTemplateOptions {
	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	/** @return array<string, mixed> */
	public function forNotification(string $text, string $url): array {
		$name = trim($this->appConfig->getValueString('libresign', 'whatsapp_business_template_name', ''));
		$language = trim($this->appConfig->getValueString('libresign', 'whatsapp_business_template_language', ''));
		if ($name === '' && $language === '') {
			return [];
		}
		if ($name === '' || $language === '') {
			throw new \InvalidArgumentException('WhatsApp Business template name and language are both required.');
		}
		$normalized = preg_replace('/\s+/u', ' ', trim($text));
		if ($normalized === null || $normalized === '') {
			throw new \InvalidArgumentException('Invalid WhatsApp Business notification text.');
		}
		return [
			'template_name' => $name,
			'template_language' => $language,
			'template_category' => 'UTILITY',
			'body_parameters' => [$normalized, $url],
			'expected_body_parameter_count' => 2,
		];
	}
}
