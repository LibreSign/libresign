<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Exception\LibresignException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;

class FileStatusController extends AEnvironmentAwareController {
	public function __construct(
		IRequest $request,
		private FileMapper $fileMapper,
		private IL10N $l10n,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Get file status by nodeId
	 *
	 * @param int $nodeId Id of file node
	 * @return DataResponse<Http::STATUS_OK, array{status: int}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 400: Error
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/file/{nodeId}/status', requirements: ['apiVersion' => '(v1)'])]
	public function getStatusByNodeId(int $nodeId): DataResponse {
		try {
			$file = $this->fileMapper->getByFileId($nodeId);

			if (!$file) {
				return new DataResponse(['status' => File::STATUS_NOT_LIBRESIGN_FILE]);
			}

			if ($file->getNodeId() === $nodeId &&
				in_array($file->getStatus(), [File::STATUS_PARTIAL_SIGNED, File::STATUS_SIGNED])) {
				return new DataResponse(['status' => File::STATUS_ORIGINAL_FILE_SIGNED_SOMEWHERE]);
			}

			return new DataResponse(['status' => $file->getStatus()]);
		} catch (\Throwable $th) {
			throw new LibresignException($this->l10n->t('Invalid fileID'), 400);
		}
	}
}
