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
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private WhatsAppBusinessTemplateOptions $templates,
	) {
	}

	public function composeSignRequest(SignRequest $request, File $file, bool $first, string $gatewayName): GatewayPayload {
		[$message, $text, $url] = $this->signRequest($request, $file, $first);
		return $this->payload($gatewayName, $message, $text, $url);
	}

	public function composeSignedFile(SignRequest $request, File $file, string $gatewayName): GatewayPayload {
		[$message, $text, $url] = $this->signedFile($request, $file);
		return $this->payload($gatewayName, $message, $text, $url);
	}

	private function payload(string $gatewayName, string $message, string $text, string $url): GatewayPayload {
		$extra = $gatewayName === 'whatsappbusiness'
			? $this->templates->forNotification($text, $url)
			: [];
		return new GatewayPayload($gatewayName, $message, $extra);
	}

	/** @return array{string, string, string} */
	private function signRequest(SignRequest $request, File $file, bool $first): array {
		$url = $this->urlGenerator->linkToRouteAbsolute('libresign.page.sign', ['uuid' => $request->getUuid()]);
		$description = $request->getDescription();
		$hasDescription = is_string($description) && trim($description) !== '';
		$name = $file->getName();

		if ($first) {
			if ($hasDescription) {
				// TRANSLATORS Initial signature invitation with a sender's description.
				// %1$s is that description, %2$s is the document name, and %3$s is the signing URL.
				// Example with "Please review this agreement", "Agreement.pdf", and "https://example.invalid/sign":
				//   Please review this agreement
				//
				//   There is a document for you to sign. Access the link below:
				//   Agreement.pdf: https://example.invalid/sign
				// Keep the whole message translatable. Preserve the intended paragraph break; placeholders may be reordered for the target language.
				$message = $this->l10n->t("%1\$s\n\nThere is a document for you to sign. Access the link below:\n%2\$s: %3\$s", [$description, $name, $url]);
				// TRANSLATORS Initial invitation sent through a WhatsApp Business UTILITY template.
				// %1$s is the sender's description; %2$s is the document name. The URL is not in this string.
				// The translated text has paragraph breaks, but the first WhatsApp template value is normalized to one line.
				// Example values sent to the approved template:
				//   {{1}} = Please review this agreement There is a document for you to sign. Access the link below: Agreement.pdf:
				//   {{2}} = https://example.invalid/sign
				// Do not add the URL to this translated text or alter the positional template variables.
				$text = $this->l10n->t("%1\$s\n\nThere is a document for you to sign. Access the link below:\n%2\$s:", [$description, $name]);
			} else {
				// TRANSLATORS Initial signature invitation without a sender's description.
				// %1$s is the document name; %2$s is the signing URL.
				// Example with "Agreement.pdf" and "https://example.invalid/sign":
				//   There is a document for you to sign. Access the link below:
				//   Agreement.pdf: https://example.invalid/sign
				// The entire message is translatable; placeholders may be reordered for the target language.
				$message = $this->l10n->t("There is a document for you to sign. Access the link below:\n%1\$s: %2\$s", [$name, $url]);
				// TRANSLATORS Initial invitation without a description, for a WhatsApp Business UTILITY template.
				// %1$s is the document name. The signing URL is a separate template variable, not part of this text.
				// Example values sent to the approved template (the first one is a single line):
				//   {{1}} = There is a document for you to sign. Access the link below: Agreement.pdf:
				//   {{2}} = https://example.invalid/sign
				// Translate the whole text while keeping the URL out of the first template value.
				$text = $this->l10n->t("There is a document for you to sign. Access the link below:\n%1\$s:", [$name]);
			}
		} elseif ($hasDescription) {
			// TRANSLATORS Updated signature invitation with a sender's description.
			// %1$s is the description, %2$s is the document name, and %3$s is the signing URL.
			// Example with "Please check the new version", "Agreement.pdf", and "https://example.invalid/sign":
			//   Please check the new version
			//
			//   Changes have been made in a file that you have to sign. Access the link below:
			//   Agreement.pdf: https://example.invalid/sign
			// Keep the paragraph break. The placeholders can be reordered to suit the language.
			$message = $this->l10n->t("%1\$s\n\nChanges have been made in a file that you have to sign. Access the link below:\n%2\$s: %3\$s", [$description, $name, $url]);
			// TRANSLATORS Updated invitation with a description, for a WhatsApp Business UTILITY template.
			// %1$s is the sender's description; %2$s is the document name. The URL is supplied separately.
			// Example values sent to the template after the translated text is normalized to one line:
			//   {{1}} = Please check the new version Changes have been made in a file that you have to sign. Access the link below: Agreement.pdf:
			//   {{2}} = https://example.invalid/sign
			// Do not insert the separate URL into this translated text.
			$text = $this->l10n->t("%1\$s\n\nChanges have been made in a file that you have to sign. Access the link below:\n%2\$s:", [$description, $name]);
		} else {
			// TRANSLATORS Updated signature invitation without a sender's description.
			// %1$s is the document name and %2$s is the signing URL.
			// Example with "Agreement.pdf" and "https://example.invalid/sign":
			//   Changes have been made in a file that you have to sign. Access the link below:
			//   Agreement.pdf: https://example.invalid/sign
			// The full notification can be rearranged for the target language.
			$message = $this->l10n->t("Changes have been made in a file that you have to sign. Access the link below:\n%1\$s: %2\$s", [$name, $url]);
			// TRANSLATORS Updated invitation without a description, for a WhatsApp Business UTILITY template.
			// %1$s is the document name, while the signing URL is supplied as the next template variable.
			// Example values sent to the template (the first one is a single line):
			//   {{1}} = Changes have been made in a file that you have to sign. Access the link below: Agreement.pdf:
			//   {{2}} = https://example.invalid/sign
			// The translated text must not include the separate signing URL.
			$text = $this->l10n->t("Changes have been made in a file that you have to sign. Access the link below:\n%1\$s:", [$name]);
		}

		return [$message, $text, $url];
	}

	/** @return array{string, string, string} */
	private function signedFile(SignRequest $request, File $file): array {
		$url = $this->urlGenerator->linkToRouteAbsolute('libresign.page.validationFilePublic', ['uuid' => $file->getUuid()]);
		$name = $file->getName();
		$signer = $request->getDisplayName();
		// TRANSLATORS Signature completion notification.
		// %1$s is the signer's display name, %2$s is the document name, and %3$s is the public validation URL.
		// Example with "Alex Example", "Agreement.pdf", and "https://example.invalid/validate":
		//   LibreSign: A file has been signed
		//   Alex Example signed the document. You can access it using the link below:
		//   Agreement.pdf: https://example.invalid/validate
		// Keep the full notification and its line breaks translatable; the placeholders may be reordered.
		$message = $this->l10n->t("LibreSign: A file has been signed\n%1\$s signed the document. You can access it using the link below:\n%2\$s: %3\$s", [$signer, $name, $url]);
		// TRANSLATORS Signature completion for a WhatsApp Business UTILITY template.
		// %1$s is the signer's display name, and %2$s is the document name. The validation URL is separate.
		// Example values sent to the approved template (the first one is a single line):
		//   {{1}} = LibreSign: A file has been signed Alex Example signed the document. You can access it using the link below: Agreement.pdf:
		//   {{2}} = https://example.invalid/validate
		// Do not include the separate validation URL in the translated text.
		$text = $this->l10n->t("LibreSign: A file has been signed\n%1\$s signed the document. You can access it using the link below:\n%2\$s:", [$signer, $name]);
		return [$message, $text, $url];
	}
}
