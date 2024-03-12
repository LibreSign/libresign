<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\NotifyService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

class NotifyController extends Controller {
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
