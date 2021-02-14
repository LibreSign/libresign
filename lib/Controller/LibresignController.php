<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Service\LibresignService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\IRootFolder;
use OCP\IRequest;

class LibresignController extends Controller {
	use HandleErrorsTrait;
	use HandleParamsTrait;

	/** @var LibresignService */
	private $service;

	/** @var FileUserMapper */
	private $fileUserMapper;
	/** @var FileMapper */
	private $fileMapper;
	/** @var IRootFolder */
	private $root;
	/** @var string */
	private $userId;

	public function __construct(
		IRequest $request,
		LibresignService $service,
		FileUserMapper $fileUserMapper,
		FileMapper $fileMapper,
		IRootFolder $root,
		$userId
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->service = $service;
		$this->fileUserMapper = $fileUserMapper;
		$this->fileMapper = $fileMapper;
		$this->root = $root;
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @todo remove NoCSRFRequired
	 */
	public function sign(
		string $inputFilePath = null,
		string $outputFolderPath = null,
		string $certificatePath = null,
		string $password = null
	): DataResponse {
		try {
			$this->checkParams([
				'inputFilePath' => $inputFilePath,
				'outputFolderPath' => $outputFolderPath,
				'certificatePath' => $certificatePath,
				'password' => $password,
			]);

			$fileSigned = $this->service->sign($inputFilePath, $outputFolderPath, $certificatePath, $password);

			return new DataResponse(['fileSigned' => $fileSigned->getInternalPath()]);
		} catch (\Exception $exception) {
			return $this->handleErrors($exception);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function signUsingUuid($uuid) {
		try {
			$fileUser = $this->fileUserMapper->getByUuidAndUserId($uuid, $this->userId);
			$fileData = $this->fileMapper->getById($fileUser->getLibresignFileId());
			$filePreview = $this->root->getById($fileData->getFileId());
			if (count($filePreview) < 1) {
				return new JSONResponse(
					[
						'message' => $this->l10n->t('File not found'),
						'action' => JSActions::ACTION_DO_NOTHING
					],
					Http::STATUS_UNPROCESSABLE_ENTITY
				);
			}
			$filePreview = $filePreview[0];
		} catch (\Throwable $th) {
			return new JSONResponse(
				[
					'action' => JSActions::ACTION_DO_NOTHING,
					'errors' => [$this->l10n->t('Invalid data to sign file')]
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
	}
}
