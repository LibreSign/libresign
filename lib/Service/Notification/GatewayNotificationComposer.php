<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequest;
use OCP\IL10N;
use OCP\IURLGenerator;

/** Builds content in memory, before a transport submission is claimed. */
final class GatewayNotificationComposer {
	public function __construct(
		private NotificationHistoryService $history,
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private WhatsAppBusinessTemplateOptions $templates,
	) {
	}

	public function compose(string $eventType, SignRequest $request, File $file, string $method): string {
		[$text, $url] = $this->parts($eventType, $request, $file, $method);
		return $text . $url;
	}

	/** Compose a transport-neutral message with optional structured delivery data. */
	public function composePayload(string $eventType, SignRequest $request, File $file, string $method, string $gatewayName): GatewayPayload {
		[$text, $url] = $this->parts($eventType, $request, $file, $method);
		$extra = $gatewayName === 'whatsappbusiness'
			? $this->templates->forNotification($text, $url)
			: [];
		return new GatewayPayload($gatewayName, $text . $url, $extra);
	}

	/** @return array{string, string} */
	private function parts(string $eventType, SignRequest $request, File $file, string $method): array {
		return match ($eventType) {
			'sign_request' => $this->signRequest($request, $file, $method),
			'file_signed' => $this->signedFile($request, $file),
			default => throw new \InvalidArgumentException('Unsupported gateway notification event'),
		};
	}

	/** @return array{string, string} */
	private function signRequest(SignRequest $request, File $file, string $method): array {
		$body = $request->getDescription() ? $request->getDescription() . "\n\n" : '';
		$body .= $this->history->isFirst($request, $method)
			? $this->l10n->t('There is a document for you to sign. Access the link below:')
			: $this->l10n->t('Changes have been made in a file that you have to sign. Access the link below:');
		return [
			$body . "\n" . $file->getName() . ': ',
			$this->urlGenerator->linkToRouteAbsolute('libresign.page.sign', ['uuid' => $request->getUuid()]),
		];
	}

	/** @return array{string, string} */
	private function signedFile(SignRequest $request, File $file): array {
		return [
			$this->l10n->t('LibreSign: A file has been signed') . "\n"
				. $this->l10n->t('%s signed the document. You can access it using the link below:', [$request->getDisplayName()])
				. "\n" . $file->getName() . ': ',
			$this->urlGenerator->linkToRouteAbsolute('libresign.page.validationFilePublic', ['uuid' => $file->getUuid()]),
		];
	}
}
