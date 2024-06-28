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
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
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
	 * @param array{email: string}[] $signers Signers data
	 * @return DataResponse<Http::STATUS_OK, array{message: string}, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, array{messages: array{type: 'danger', message: string}[]}, array{}>
	 *
	 * 200: OK
	 * 401: Unauthorized
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/notify/signers', requirements: ['apiVersion' => '(v1)'])]
	public function signers(int $fileId, array $signers): DataResponse {
		try {
			$this->notifyService->signers($fileId, $signers);
		} catch (\Throwable $th) {
			return new DataResponse(
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
		return new DataResponse([
			'message' => $this->l10n->t('Notification sent with success.')
		], Http::STATUS_OK);
	}

	/**
	 * Notify a signer of a file
	 *
	 * @param integer $fileId The identifier value of LibreSign file
	 * @param integer $signRequestId The sign request id
	 * @return DataResponse<Http::STATUS_OK, array{message: string}, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, array{messages: array{type: 'danger', message: string}[]}, array{}>
	 *
	 * 200: OK
	 * 401: Unauthorized
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/notify/signer', requirements: ['apiVersion' => '(v1)'])]
	public function signer(int $fileId, int $signRequestId): DataResponse {
		try {
			$this->notifyService->signer($fileId, $signRequestId);
		} catch (LibresignException $e) {
			throw $e;
		} catch (\Throwable $th) {
			return new DataResponse(
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
		return new DataResponse([
			'message' => $this->l10n->t('Notification sent with success.')
		], Http::STATUS_OK);
	}

	/**
	 * Dismiss a specific notification
	 *
	 * @param integer $signRequestId The sign request id
	 * @param integer $timestamp Timestamp of notification to dismiss
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>
	 *
	 * 200: OK
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/notify/notification', requirements: ['apiVersion' => '(v1)'])]
	public function notificationDismiss(int $signRequestId, int $timestamp): DataResponse {
		$this->notifyService->notificationDismiss(
			$signRequestId,
			$this->userSession->getUser(),
			$timestamp
		);
		return new DataResponse();
	}
}
