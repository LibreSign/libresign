<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use Exception;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Middleware\Attribute\RequireSignRequestUuid;
use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Service\AccountFileService;
use OCA\Libresign\Service\IdDocsService;
use OCA\Libresign\Service\SignFileService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type LibresignIdDocs from ResponseDefinitions
 */
class IdDocsController extends AEnvironmentAwareController implements ISignatureUuid {
	use LibresignTrait;
	public function __construct(
		IRequest $request,
		protected SignFileService $signFileService,
		protected IL10N $l10n,
		protected IdDocsService $idDocsService,
		protected AccountFileService $accountFileService,
		protected IUserSession $userSession,
		protected ValidateHelper $validateHelper,
		protected LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Add files to account profile
	 *
	 * @param LibresignIdDocs[] $files The list of files to add to profile
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, array{file: ?int, type: 'info'|'warning'|'danger', message: string}, array{}>
	 *
	 * 200: Certificate saved with success
	 * 401: No file provided or other problem with provided file
	 */
	#[PublicPage]
	#[AnonRateLimit(limit: 30, period: 60)]
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireSignRequestUuid(skipIfAuthenticated: true)]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/id-docs', requirements: ['apiVersion' => '(v1)'])]
	public function addFiles(array $files): DataResponse {
		try {
			if ($user = $this->userSession->getUser()) {
				$this->idDocsService->addFilesToAccount($files, $user);
			} elseif ($signRequest = $this->getSignRequestEntity()) {
				$this->idDocsService->addFilesToDocumentFolder($files, $signRequest);
			} else {
				throw new Exception('Invalid data');
			}
			return new DataResponse([], Http::STATUS_OK);
		} catch (\Exception $exception) {
			$exceptionData = json_decode($exception->getMessage());
			if (isset($exceptionData->file)) {
				$message = [
					'file' => $exceptionData->file,
					'type' => $exceptionData->type,
					'message' => $exceptionData->message
				];
			} else {
				$message = [
					'file' => null,
					'type' => null,
					'message' => $exception->getMessage()
				];
			}
			return new DataResponse(
				$message,
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Delete file from account
	 *
	 * @param int $nodeId the nodeId of file to be delete
	 *
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, array{messages: string[]}, array{}>
	 *
	 * 200: File deleted with success
	 * 401: Failure to delete file from account
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/id-docs', requirements: ['apiVersion' => '(v1)'])]
	public function deleteFile(int $nodeId): DataResponse {
		try {
			$this->idDocsService->deleteFileFromAccount($nodeId, $this->userSession->getUser());
			return new DataResponse([], Http::STATUS_OK);
		} catch (\Exception $exception) {
			return new DataResponse(
				[
					'messages' => [
						$exception->getMessage(),
					],
				],
				Http::STATUS_UNAUTHORIZED,
			);
		}
	}

	/**
	 * List files of unauthenticated account
	 *
	 * @param array{approved?: 'yes'}|null $filter Filter params
	 * @param int|null $page the number of page to return
	 * @param int|null $length Total of elements to return
	 * @return DataResponse<Http::STATUS_OK, array{pagination: LibresignPagination, data: LibresignFile[]}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Certificate saved with success
	 * 404: No file provided or other problem with provided file
	 */
	#[PublicPage]
	#[AnonRateLimit(limit: 30, period: 60)]
	#[NoCSRFRequired]
	#[RequireSignRequestUuid(skipIfAuthenticated: true)]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/id-docs', requirements: ['apiVersion' => '(v1)'])]
	public function listOfUnauthenticatedSigner(array $filter = [], ?int $page = null, ?int $length = null): DataResponse {
		try {
			if ($user = $this->userSession->getUser()) {
				$filter['userId'] = $user->getUID();
			} elseif ($signRequest = $this->getSignRequestEntity()) {
				$filter['singRequestId'] = $signRequest->getId();
			} else {
				throw new Exception('Invalid data');
			}

			$return = $this->idDocsService->list($filter, $page, $length);
			return new DataResponse($return, Http::STATUS_OK);
		} catch (\Throwable $th) {
			return new DataResponse(
				[
					'message' => $th->getMessage()
				],
				Http::STATUS_NOT_FOUND
			);
		}
	}

	/**
	 * List files that need to be approved
	 *
	 * @param array{approved?: 'yes'}|null $filter Filter params
	 * @param int|null $page the number of page to return
	 * @param int|null $length Total of elements to return
	 * @return DataResponse<Http::STATUS_OK, array{pagination: LibresignPagination, data: ?LibresignFile[]}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 404: Account not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/id-docs/approval/list', requirements: ['apiVersion' => '(v1)'])]
	public function listToApproval(array $filter = [], ?int $page = null, ?int $length = null): DataResponse {
		try {
			$this->validateHelper->userCanApproveValidationDocuments($this->userSession->getUser());
			$return = $this->accountFileService->accountFileList($filter, $page, $length);
			return new DataResponse($return, Http::STATUS_OK);
		} catch (\Throwable $th) {
			return new DataResponse(
				[
					'message' => $th->getMessage()
				],
				Http::STATUS_NOT_FOUND
			);
		}
	}
}
