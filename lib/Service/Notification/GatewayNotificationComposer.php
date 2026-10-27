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
	) {
	}

	public function compose(string $eventType, SignRequest $request, File $file, string $method): string {
		[$text, $url] = $this->parts($eventType, $request, $file, $method);
		return $text . $url;
	}

	/**
	 * WhatsApp Business UTILITY body template contract (in positional order):
	 * {{1}} = one-line localized notification text including the file name (without URL);
	 * {{2}} = absolute signing/validation URL.
	 *
	 * Initial requests, updated requests and signature completion use the same
	 * two positions. A neutral approved template can serve all these events;
	 * other gateways keep their original text-only message.
	 */
	public function composePayload(string $eventType, SignRequest $request, File $file, string $method, string $gatewayName): GatewayPayload {
		[$text, $url] = $this->parts($eventType, $request, $file, $method);
		// Meta template text parameters may not contain newlines or tabs.
		// Preserve the original multiline text for all other gateway channels.
		$templateText = preg_replace('/\s+/u', ' ', trim($text));
		if ($gatewayName === 'whatsappbusiness' && ($templateText === null || $templateText === '')) {
			throw new \InvalidArgumentException('Invalid template notification text');
		}
		$extra = $gatewayName === 'whatsappbusiness' ? [
			'body_parameters' => [$templateText, $url],
			'expected_body_parameter_count' => 2,
		] : [];
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
