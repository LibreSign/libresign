<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

use OCA\Libresign\Service\TwofactorGatewayService;

/** Only transmit a pre-composed message to a selected gateway. */
final class GatewayChannel implements NotificationChannel {
	public function __construct(
		private TwofactorGatewayService $gateway,
	) {
	}

	#[\Override]
	public function supports(string $channel): bool {
		return str_starts_with($channel, 'gateway:') && strlen($channel) > strlen('gateway:');
	}

	#[\Override]
	public function deliver(NotificationMessage $message): void {
		$payload = $message->payload;
		if (!$payload instanceof GatewayPayload || 'gateway:' . $payload->gatewayName !== $message->channel) {
			throw new \InvalidArgumentException('Invalid gateway notification payload');
		}
		$this->gateway->send($payload->gatewayName, $message->recipient, $payload->body);
	}
}
