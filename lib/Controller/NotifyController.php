<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Exception\NotificationTransportException;
use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Service\NotifyService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type LibresignDangerMessagesResponse from ResponseDefinitions
 * @psalm-import-type LibresignMessageResponse from ResponseDefinitions
 * @psalm-import-type LibresignActionErrorResponse from ResponseDefinitions
 */

class NotifyController extends AEnvironmentAwareController {
	public function __construct(
		IRequest $request,
		private IL10N $l10n,
		private NotifyService $notifyService,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Notify signers of a file
	 *
	 * @param integer $fileId The identifier value of LibreSign file
	 * @param array{email: string}[] $signers Signers data
	 * @return DataResponse<Http::STATUS_OK, LibresignMessageResponse, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, LibresignMessageResponse|LibresignActionErrorResponse, array{}>|DataResponse<Http::STATUS_BAD_GATEWAY|Http::STATUS_INTERNAL_SERVER_ERROR, LibresignDangerMessagesResponse, array{}>
	 *
	 * 200: Request processed
	 * 422: Request cannot be processed
	 * 500: Unexpected server error
	 * 502: Transport did not confirm submission
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/notify/signers', requirements: ['apiVersion' => '(v1)'])]
	public function signers(int $fileId, array $signers): DataResponse {
		return $this->sendNotification(fn () => $this->notifyService->signers($fileId, $signers));
	}

	/**
	 * Notify a signer of a file
	 *
	 * @param integer $fileId The identifier value of LibreSign file
	 * @param integer $signRequestId The sign request id
	 * @return DataResponse<Http::STATUS_OK, LibresignMessageResponse, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, LibresignMessageResponse|LibresignActionErrorResponse, array{}>|DataResponse<Http::STATUS_BAD_GATEWAY|Http::STATUS_INTERNAL_SERVER_ERROR, LibresignDangerMessagesResponse, array{}>
	 *
	 * 200: Request processed
	 * 422: Request cannot be processed
	 * 500: Unexpected server error
	 * 502: Transport did not confirm submission
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/notify/signer', requirements: ['apiVersion' => '(v1)'])]
	public function signer(int $fileId, int $signRequestId): DataResponse {
		return $this->sendNotification(fn () => $this->notifyService->signer($fileId, $signRequestId));
	}

	/**
	 * An accepted request is not proof that a remote recipient received the notification.
	 *
	 * @return DataResponse<Http::STATUS_OK, LibresignMessageResponse, array{}>|DataResponse<Http::STATUS_BAD_GATEWAY|Http::STATUS_INTERNAL_SERVER_ERROR, LibresignDangerMessagesResponse, array{}>
	 */
	private function sendNotification(callable $send): DataResponse {
		try {
			$send();
		} catch (LibresignException $e) {
			// MailService preserves the original classified transport exception
			// in the exception chain. It is not a validation error.
			for ($cause = $e->getPrevious(); $cause !== null; $cause = $cause->getPrevious()) {
				if ($cause instanceof NotificationTransportException) {
					return $this->unconfirmedResponse();
				}
			}
			throw $e;
		} catch (NotificationTransportException) {
			return $this->unconfirmedResponse();
		} catch (\Exception $e) {
			$this->logger->error('Manual notification request failed', ['exceptionType' => get_debug_type($e)]);
			return new DataResponse([
				'messages' => [[
					'type' => 'danger',
					// TRANSLATORS Error shown when a notification request fails unexpectedly.
					'message' => $this->l10n->t('The notification request failed. Please contact an administrator.'),
				]],
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new DataResponse([
			// TRANSLATORS Request completed; remote delivery or reading is not guaranteed.
			'message' => $this->l10n->t('Notification request processed. Delivery is not confirmed.'),
		], Http::STATUS_OK);
	}

	/** @return DataResponse<Http::STATUS_BAD_GATEWAY, LibresignDangerMessagesResponse, array{}> */
	private function unconfirmedResponse(): DataResponse {
		return new DataResponse([
			'messages' => [[
				'type' => 'danger',
				// TRANSLATORS Submission could have reached the provider. A new manual attempt may duplicate the message.
				'message' => $this->l10n->t('The notification could not be confirmed. Check with the recipient before trying again.'),
			]],
		], Http::STATUS_BAD_GATEWAY);
	}

	/**
	 * Dismiss a specific notification
	 *
	 * @param string $objectType The type of object
	 * @param integer $objectId The identifier value of LibreSign file
	 * @param string $subject The subject of notification
	 * @param integer $timestamp Timestamp of notification to dismiss
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>
	 *
	 * 200: OK
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/notify/notification', requirements: ['apiVersion' => '(v1)'])]
	public function notificationDismiss(
		string $objectType,
		int $objectId,
		string $subject,
		int $timestamp,
	): DataResponse {
		$this->notifyService->notificationDismiss(
			$objectType,
			$objectId,
			$subject,
			$this->userSession->getUser(),
			$timestamp
		);
		return new DataResponse();
	}
}
