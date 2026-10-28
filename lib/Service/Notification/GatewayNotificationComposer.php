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

/** Builds fully localized notification content before a transport submission is claimed. */
final class GatewayNotificationComposer {
	public function __construct(
		private NotificationHistoryService $history,
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private WhatsAppBusinessTemplateOptions $templates,
	) {
	}

	public function compose(string $eventType, SignRequest $request, File $file, string $method): string {
		return $this->render($eventType, $request, $file, $method)[0];
	}

	public function composePayload(string $eventType, SignRequest $request, File $file, string $method, string $gatewayName): GatewayPayload {
		[$message, $text, $url] = $this->render($eventType, $request, $file, $method);
		$extra = $gatewayName === 'whatsappbusiness'
			? $this->templates->forNotification($text, $url)
			: [];
		return new GatewayPayload($gatewayName, $message, $extra);
	}

	/** @return array{string, string, string} Full message, independent template text, action URL. */
	private function render(string $eventType, SignRequest $request, File $file, string $method): array {
		return match ($eventType) {
			'sign_request' => $this->signRequest($request, $file, $method),
			'file_signed' => $this->signedFile($request, $file),
			default => throw new \InvalidArgumentException('Unsupported gateway notification event'),
		};
	}

	/** @return array{string, string, string} */
	private function signRequest(SignRequest $request, File $file, string $method): array {
		$url = $this->urlGenerator->linkToRouteAbsolute('libresign.page.sign', ['uuid' => $request->getUuid()]);
		$description = $request->getDescription();
		$hasDescription = is_string($description) && trim($description) !== '';
		$name = $file->getName();

		if ($this->history->isFirst($request, $method)) {
			if ($hasDescription) {
				// TRANSLATORS: Initial signature invitation with a personal message. %1$s is the sender's description, %2$s is the document name, %3$s is the signing URL. Keep the complete message and line breaks together.
				$message = $this->l10n->t("%1\$s\n\nThere is a document for you to sign. Access the link below:\n%2\$s: %3\$s", [$description, $name, $url]);
				// TRANSLATORS: Initial signature invitation for a WhatsApp template. %1$s is the sender's description and %2$s is the document name; the signing URL is a separate template variable.
				$text = $this->l10n->t("%1\$s\n\nThere is a document for you to sign. Access the link below:\n%2\$s:", [$description, $name]);
			} else {
				// TRANSLATORS: Initial signature invitation. %1$s is the document name and %2$s is the signing URL. Keep the URL in the same translated message.
				$message = $this->l10n->t("There is a document for you to sign. Access the link below:\n%1\$s: %2\$s", [$name, $url]);
				// TRANSLATORS: Initial signature invitation for a WhatsApp template. %1$s is the document name; the signing URL is supplied in a separate template variable.
				$text = $this->l10n->t("There is a document for you to sign. Access the link below:\n%1\$s:", [$name]);
			}
		} elseif ($hasDescription) {
			// TRANSLATORS: Updated signature request with a personal message. %1$s is the sender's description, %2$s is the document name, %3$s is the signing URL.
			$message = $this->l10n->t("%1\$s\n\nChanges have been made in a file that you have to sign. Access the link below:\n%2\$s: %3\$s", [$description, $name, $url]);
			// TRANSLATORS: Updated signature request for a WhatsApp template. %1$s is the sender's description, %2$s is the document name; URL is in a separate template variable.
			$text = $this->l10n->t("%1\$s\n\nChanges have been made in a file that you have to sign. Access the link below:\n%2\$s:", [$description, $name]);
		} else {
			// TRANSLATORS: Updated signature request. %1$s is the document name and %2$s is the signing URL.
			$message = $this->l10n->t("Changes have been made in a file that you have to sign. Access the link below:\n%1\$s: %2\$s", [$name, $url]);
			// TRANSLATORS: Updated signature request for a WhatsApp template. %1$s is the document name; URL is in a separate template variable.
			$text = $this->l10n->t("Changes have been made in a file that you have to sign. Access the link below:\n%1\$s:", [$name]);
		}

		return [$message, $text, $url];
	}

	/** @return array{string, string, string} */
	private function signedFile(SignRequest $request, File $file): array {
		$url = $this->urlGenerator->linkToRouteAbsolute('libresign.page.validationFilePublic', ['uuid' => $file->getUuid()]);
		$name = $file->getName();
		$signer = $request->getDisplayName();
		// TRANSLATORS: Signature completion notification. %1$s is the signer name, %2$s is the document name, %3$s is the public validation URL. Keep the complete sentence and line breaks together.
		$message = $this->l10n->t("LibreSign: A file has been signed\n%1\$s signed the document. You can access it using the link below:\n%2\$s: %3\$s", [$signer, $name, $url]);
		// TRANSLATORS: Signature completion notification for a WhatsApp template. %1$s is the signer name and %2$s the document name; the validation URL is sent as a separate template variable.
		$text = $this->l10n->t("LibreSign: A file has been signed\n%1\$s signed the document. You can access it using the link below:\n%2\$s:", [$signer, $name]);
		return [$message, $text, $url];
	}
}
