<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
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
	 * @return array{action: int, errors: list<array{message: string, title?: string}>}
	 */
	public function handleException(\Throwable $exception): array {
		if ($exception instanceof LibresignException) {
			return $this->handleLibresignException($exception);
		}

		return $this->handleGenericException($exception);
	}

	/**
	 * @return array{action: int, errors: list<array{message: string}>}
	 */
	private function handleLibresignException(LibresignException $exception): array {
		$code = $exception->getCode();
		$action = $code === 400
			? JSActions::ACTION_CREATE_SIGNATURE_PASSWORD
			: JSActions::ACTION_DO_NOTHING;

		return [
			'action' => $action,
			'errors' => [['message' => $exception->getMessage()]],
		];
	}

	/**
	 * @return array{action: int, errors: list<array{message: string, title?: string}>}
	 */
	private function handleGenericException(\Throwable $exception): array {
		$message = $exception->getMessage();

		return [
			'action' => JSActions::ACTION_DO_NOTHING,
			'errors' => $this->isKnownError($message)
				? [['message' => $this->l10n->t($message)]]
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

	/**
	 * @return list<array{message: string, title: string}>
	 */
	private function formatUnknownError(string $message, \Throwable $exception): array {
		$this->logger->error($message, ['exception' => $exception]);

		return [[
			'message' => sprintf(
				"The server was unable to complete your request.\n"
				. "If this happens again, please send the technical details below to the server administrator.\n"
				. "## Technical details:\n"
				. "**Remote Address**: %s\n"
				. "**Request ID**: %s\n"
				. '**Message**: %s',
				$this->request->getRemoteAddress(),
				$this->request->getId(),
				$message,
			),
			'title' => $this->l10n->t('Internal Server Error'),
		]];
	}
}
