<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Middleware\Attribute\RequireSignerUuid;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\SessionService;
use OCA\Libresign\Service\SignRequest\ProgressService;
use OCA\Libresign\Service\Worker\WorkerHealthService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-import-type LibresignValidateFile from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignProgressPayload from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignProgressError from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignProgressResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignProgressFile from \OCA\Libresign\ResponseDefinitions
 */
class FileProgressController extends AEnvironmentAwareController {
	public function __construct(
		IRequest $request,
		private FileMapper $fileMapper,
		private SignRequestMapper $signRequestMapper,
		private FileService $fileService,
		private SessionService $sessionService,
		private IUserSession $userSession,
		private WorkerHealthService $workerHealthService,
		private ProgressService $progressService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Check file progress by sign request UUID with long-polling (similar to Talk)
	 *
	 * Waits up to 30 seconds for status change using cache for efficiency.
	 * Returns progress for the specific sign request, not the global file status.
	 *
	 * @param string $uuid Sign request UUID
	 * @param int $timeout Maximum seconds to wait (default 30)
	 * @return DataResponse<Http::STATUS_OK, LibresignProgressResponse, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string, status: string}, array{}>
	 *
	 * 200: Status and progress returned
	 * 404: Sign request not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireSignerUuid]
	#[PublicPage]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/file/progress/{uuid}', requirements: ['apiVersion' => '(v1)'])]
	public function checkProgressByUuid(string $uuid, int $timeout = 30): DataResponse {
		$timeout = max(1, min($timeout, 30));
		try {
			$signRequest = $this->signRequestMapper->getByUuid($uuid);
			$file = $this->fileMapper->getById($signRequest->getFileId());
			$currentStatus = $this->progressService->getStatusCodeForSignRequest($file, $signRequest);

			if ($timeout > 0) {
				if ($file->getStatus() === FileStatus::SIGNING_IN_PROGRESS->value) {
					$this->workerHealthService->ensureWorkerRunning();
				}
				$currentStatus = $this->progressService->pollForStatusOrErrorChange($file, $signRequest, $currentStatus, $timeout);
			}

			return $this->buildStatusResponse($file, $signRequest, $currentStatus);

		} catch (\Exception $e) {
			return new DataResponse([
				'message' => $e->getMessage(),
				'status' => 'ERROR',
			], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Build HTTP response with status and progress information
	 *
	 * @param FileEntity $file The file entity
	 * @param SignRequestEntity $signRequest The sign request entity
	 * @param int $status Current status code
	 * @return DataResponse<Http::STATUS_OK, LibresignProgressResponse, array{}>
	 * @psalm-return DataResponse<Http::STATUS_OK, LibresignProgressResponse, array{}>
	 */
	private function buildStatusResponse(FileEntity $file, SignRequestEntity $signRequest, int $status): DataResponse {
		$statusEnum = FileStatus::tryFrom($status);
		/** @psalm-var LibresignProgressPayload $progress */
		$progress = $this->progressService->getSignRequestProgress($file, $signRequest);
		/** @psalm-var LibresignProgressError|null $error */
		$error = $this->progressService->getSignRequestError($signRequest->getUuid());

		$hasFileErrors = !empty($progress['files']) && $this->hasErrorsInFiles($progress['files']);

		/** @psalm-var LibresignProgressResponse $response */
		$response = [
			'status' => $statusEnum?->name ?? 'UNKNOWN',
			'statusCode' => $status,
			'statusText' => $this->fileMapper->getTextOfStatus($status),
			'fileId' => $file->getId(),
			'progress' => $progress,
		];

		if ($error && !$hasFileErrors) {
			$response['status'] = 'ERROR';
			if (!empty($error['message'])) {
				$response['statusText'] = (string)$error['message'];
			}
			$response['error'] = $error;
		}

		$hasAnyError = $error || $hasFileErrors || ($progress['errors'] ?? 0) > 0;
		if (!$hasAnyError && $this->progressService->isProgressComplete($progress)) {
			$response['file'] = $this->fileService
				->setFile($file)
				->setIdentifyMethodId($this->sessionService->getIdentifyMethodId())
				->setHost($this->request->getServerHost())
				->setMe($this->userSession->getUser())
				->showVisibleElements()
				->showSigners()
				->showSettings()
				->showMessages()
				->showValidateFile()
				->toArray();
		}

		return new DataResponse($response, Http::STATUS_OK);
	}

	/**
	 * @param list<LibresignProgressFile> $files
	 */
	private function hasErrorsInFiles(array $files): bool {
		foreach ($files as $file) {
			if (!empty($file['error'])) {
				return true;
			}
		}
		return false;
	}
}
