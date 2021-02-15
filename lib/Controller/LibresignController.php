<?php

namespace OCA\Libresign\Controller;

use OC\Files\Filesystem;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\JLibresignHandler;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\LibresignService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\IRootFolder;
use OCP\IL10N;
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
	/** @var IL10N */
	private $l10n;
	/** @var AccountService */
	private $account;
	/** @var JLibresignHandler */
	private $libresignHandler;
	/** @var string */
	private $userId;

	public function __construct(
		IRequest $request,
		LibresignService $service,
		FileUserMapper $fileUserMapper,
		FileMapper $fileMapper,
		IRootFolder $root,
		IL10N $l10n,
		AccountService $account,
		JLibresignHandler $libresignHandler,
		$userId
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->service = $service;
		$this->fileUserMapper = $fileUserMapper;
		$this->fileMapper = $fileMapper;
		$this->root = $root;
		$this->l10n = $l10n;
		$this->account = $account;
		$this->libresignHandler = $libresignHandler;
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
	): JSONResponse {
		try {
			$this->checkParams([
				'inputFilePath' => $inputFilePath,
				'outputFolderPath' => $outputFolderPath,
				'certificatePath' => $certificatePath,
				'password' => $password,
			]);

			$fileSigned = $this->service->sign($inputFilePath, $outputFolderPath, $certificatePath, $password);

			return new JSONResponse(
				['fileSigned' => $fileSigned->getInternalPath()],
				HTTP::STATUS_OK
			);
		} catch (\Exception $exception) {
			return new JSONResponse(
				[
					'action' => JSActions::ACTION_DO_NOTHING,
					'errors' => [$this->l10n->t($exception->getMessage())]
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function signUsingUuid(string $uuid, string $password): JSONResponse {
		try {
			$fileUser = $this->fileUserMapper->getByUuidAndUserId($uuid, $this->userId);
			$fileData = $this->fileMapper->getById($fileUser->getFileId());
			Filesystem::initMountPoints($fileData->getuserId());
			$inputFile = $this->root->getById($fileData->getNodeId());
			if (count($inputFile) < 1) {
				throw new LibresignException($this->l10n->t('File not found'));
			}
			$inputFile = $inputFile[0];
			$signedFilePath = preg_replace(
				'/' . $inputFile->getExtension() . '$/',
				$this->l10n->t('signed').'.'.$inputFile->getExtension(),
				$inputFile->getPath()
			);
			if ($this->root->nodeExists($signedFilePath)) {
				$signedFile = $this->root->get($signedFilePath);
				$inputFile = $signedFilePath;
			}
			$certificatePath = $this->account->getPfx($fileUser->getUserId());
			list(, $signedContent) = $this->libresignHandler->signExistingFile($inputFile, $certificatePath, $password);
			if (!$signedFile) {
				$signedFile = $this->root->newFile($signedFilePath);
			}
			$signedFile->putContent($signedContent);
			return new JSONResponse(
				[
					'action' => JSActions::ACTION_DO_NOTHING,
					'message' => $this->l10n->t('File signed')
				],
				Http::STATUS_OK
			);
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
