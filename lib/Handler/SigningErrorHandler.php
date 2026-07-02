<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class SigningErrorHandler {
	public function __construct(
		private IL10N $l10n,
		private IRequest $request,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @return array{action: int, errors: list<array{message: string, code?: int, title?: string}>}
	 */
	public function handleException(\Throwable $exception): array {
		if ($exception instanceof LibresignException) {
			return $this->handleLibresignException($exception);
		}

		return $this->handleGenericException($exception);
	}

	/**
	 * @return array{action: int, errors: list<array{message: string, code: int}>}
	 */
	private function handleLibresignException(LibresignException $exception): array {
		$code = $exception->getCode();
		$action = $code === 400
			? JSActions::ACTION_CREATE_SIGNATURE_PASSWORD
			: JSActions::ACTION_DO_NOTHING;

		return [
			'action' => $action,
			'errors' => [[
				'message' => $exception->getMessage(),
				'code' => $code,
			]],
		];
	}

	/**
	 * @return array{action: int, errors: list<array{message: string, code?: int, title?: string}>}
	 */
	private function handleGenericException(\Throwable $exception): array {
		$message = $exception->getMessage();

		return [
			'action' => JSActions::ACTION_DO_NOTHING,
			'errors' => $this->isKnownError($message)
				? [['message' => $this->translateKnownError($message)]]
				: $this->formatUnknownError($message, $exception),
		];
	}

	private function isKnownError(string $message): bool {
		return in_array($message, [
			'Host violates local access rules.',
			'Certificate Password Invalid.',
			'Certificate Password is Empty.',
		], true);
	}

	private function translateKnownError(string $message): string {
		return match ($message) {
			// TRANSLATORS: Error returned when the signing service rejects a request to a local/private host for security reasons.
			'Host violates local access rules.' => $this->l10n->t('Host violates local access rules.'),
			// TRANSLATORS: Error shown when the password provided to unlock the signing certificate is incorrect.
			'Certificate Password Invalid.' => $this->l10n->t('Certificate Password Invalid.'),
			// TRANSLATORS: Error shown when no password was provided to unlock the signing certificate.
			'Certificate Password is Empty.' => $this->l10n->t('Certificate Password is Empty.'),
			default => $message,
		};
	}

	/**
	 * @return list<array{message: string, title: string}>
	 */
	private function formatUnknownError(string $message, \Throwable $exception): array {
		$this->logger->error($message, ['exception' => $exception]);

		return [[
			// TRANSLATORS: Multi-line error shown when an unexpected server error happens during signing. %1$s is the client IP address, %2$s is the request ID, and %3$s is the technical error message. Keep the Markdown formatting and line breaks.
			'message' => $this->l10n->t(
				"The server was unable to complete your request.\n"
				. "If this happens again, please send the technical details below to the server administrator.\n"
				. "## Technical details:\n"
				. "**Remote Address**: %1\$s\n"
				. "**Request ID**: %2\$s\n"
				. '**Message**: %3$s',
				[
					$this->request->getRemoteAddress(),
					$this->request->getId(),
					$message,
				],
			),
			'title' => $this->l10n->t('Internal Server Error'),
		]];
	}
}
