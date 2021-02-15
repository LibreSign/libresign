<?php

namespace OCA\Libresign\Controller;

use OC\Files\Filesystem;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Service\AccountService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IRequest;

class AccountController extends ApiController {
	/** @var IL10N */
	private $l10n;
	/** @var AccountService */
	private $account;
	/** @var FileMapper */
	private $fileMapper;
	/** @var IRootFolder */
	private $root;

	public function __construct(
		IRequest $request,
		IL10N $l10n,
		AccountService $account,
		FileMapper $fileMapper,
		IRootFolder $root
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->l10n = $l10n;
		$this->account = $account;
		$this->fileMapper = $fileMapper;
		$this->root = $root;
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 * @PublicPage
	 * @return JSONResponse
	 */
	public function createToSign(string $uuid, string $email, string $password, string $signPassword) {
		try {
			$data = [
				'uuid' => $uuid,
				'email' => $email,
				'password' => $password,
				'signPassword' => $signPassword
			];
			$this->account->validateCreateToSign($data);
			$this->account->createToSign($uuid, $email, $password, $signPassword);
			$fileUser = $this->account->getFileUserByUuid($uuid);
			$fileData = $this->fileMapper->getById($fileUser->getFileId());
			Filesystem::initMountPoints($fileData->getUserId());
			$fileToSign = $this->root->getById($fileData->getNodeId());
			if (count($fileToSign) < 1) {
				return new JSONResponse(
					[
						'message' => $this->l10n->t('File not found'),
						'action' => JSActions::ACTION_DO_NOTHING
					],
					Http::STATUS_UNPROCESSABLE_ENTITY
				);
			}
			$fileToSign = $fileToSign[0];
			$data = [
				'message' => $this->l10n->t('Success'),
				'action' => JSActions::ACTION_SIGN,
				'pdf' => [
					'base64' => base64_encode($fileToSign->getContent())
				],
				'filename' => $fileData->getName(),
				'description' => $fileData->getDescription()
			];
		} catch (\Throwable $th) {
			return new JSONResponse(
				[
					'message' => $th->getMessage(),
					'action' => JSActions::ACTION_DO_NOTHING
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
		return new JSONResponse(
			$data,
			Http::STATUS_OK
		);
	}
}
