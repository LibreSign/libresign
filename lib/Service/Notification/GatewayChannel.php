<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\NotificationPreparationException;
use OCA\Libresign\Service\TwofactorGatewayService;
use OCP\DB\Exception as DatabaseException;
use OCP\IL10N;
use OCP\IURLGenerator;

/** SMS, WhatsApp, Signal, Telegram and XMPP retain their own gateway identity. */
final class GatewayChannel implements NotificationChannel {
	public function __construct(
		private TwofactorGatewayService $gateway,
		private SignRequestMapper $signRequestMapper,
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
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
		$request = $message->signRequest;
		$file = $message->file;
		if ($message->eventType === 'sign_request') {
			try {
				$isFirst = $this->signRequestMapper->incrementNotificationCounter($request, $payload->identifierKey);
			} catch (DatabaseException $e) {
				throw new NotificationPreparationException('history_write_failed', $e);
			}
			$body = $request->getDescription() ? $request->getDescription() . "\n\n" : '';
			if ($isFirst) {
				// TRANSLATORS Message sent through an external gateway for a new signing request.
				$body .= $this->l10n->t('There is a document for you to sign. Access the link below:');
			} else {
				// TRANSLATORS Message sent through an external gateway after a signing request has changed.
				$body .= $this->l10n->t('Changes have been made in a file that you have to sign. Access the link below:');
			}
			$body .= "\n" . $file->getName() . ': ' . $this->urlGenerator->linkToRouteAbsolute('libresign.page.sign', ['uuid' => $request->getUuid()]);
		} elseif ($message->eventType === 'file_signed') {
			$body = $this->l10n->t('LibreSign: A file has been signed') . "\n"
				. $this->l10n->t('%s signed the document. You can access it using the link below:', [$request->getDisplayName()])
				. "\n" . $file->getName() . ': '
				. $this->urlGenerator->linkToRouteAbsolute('libresign.page.validationFilePublic', ['uuid' => $file->getUuid()]);
		} else {
			throw new \InvalidArgumentException('Unsupported gateway notification event');
		}
		$this->gateway->send($payload->gatewayName, $message->recipient, $body);
	}
}
