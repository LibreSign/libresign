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

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function signers($fileId, $signers): JSONResponse {
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

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function signer($fileId, $signRequestId): JSONResponse {
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
