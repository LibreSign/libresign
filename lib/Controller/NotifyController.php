<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\NotifyService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

class NotifyController extends AEnvironmentAwareController {
	public function __construct(
		IRequest $request,
		private IL10N $l10n,
		private NotifyService $notifyService,
		private IUserSession $userSession,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Notify signers of a file
	 *
	 * @param integer $fileId The identifier value of LibreSign file
	 * @param array<string, mixed> $signers Signers data
	 * @return JSONResponse<Http::STATUS_OK, array{}, array{}>|JSONResponse<Http::STATUS_UNAUTHORIZED, array{messages: array{}}, array{}>
	 *
	 * 200: OK
	 * 401: Unauthorized
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function signers(int $fileId, array $signers): JSONResponse {
		try {
			$this->notifyService->signers($fileId, $signers);
		} catch (\Throwable $th) {
			return new JSONResponse(
				[
					'messages' => [
						[
							'type' => 'danger',
							'message' => $th->getMessage()
						]
					]
				],
				Http::STATUS_UNAUTHORIZED
			);
		}
		return new JSONResponse([
			'message' => $this->l10n->t('Notification sent with success.')
		], Http::STATUS_OK);
	}

	/**
	 * Notify a signer of a file
	 *
	 * @param integer $fileId The identifier value of LibreSign file
	 * @param integer $signRequestId The sign request id
	 * @return JSONResponse<Http::STATUS_OK, array{}, array{}>|JSONResponse<Http::STATUS_UNAUTHORIZED, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 401: Unauthorized
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function signer(int $fileId, int $signRequestId): JSONResponse {
		try {
			$this->notifyService->signer($fileId, $signRequestId);
		} catch (LibresignException $e) {
			throw $e;
		} catch (\Throwable $th) {
			return new JSONResponse(
				[
					'messages' => [
						[
							'type' => 'danger',
							'message' => $th->getMessage()
						]
					]
				],
				Http::STATUS_UNAUTHORIZED
			);
		}
		return new JSONResponse([
			'message' => $this->l10n->t('Notification sent with success.')
		], Http::STATUS_OK);
	}

	/**
	 * Dismiss a specific notification
	 *
	 * @param integer $signRequestId The sign request id
	 * @param integer $timestamp Timestamp of notification to dismiss
	 * @return JSONResponse<Http::STATUS_OK, array{}, array{}>
	 *
	 * 200: OK
	 */
	#[NoAdminRequired]
	public function notificationDismiss(int $signRequestId, int $timestamp): JSONResponse {
		$this->notifyService->notificationDismiss(
			$signRequestId,
			$this->userSession->getUser(),
			$timestamp
		);
		return new JSONResponse();
	}
}
