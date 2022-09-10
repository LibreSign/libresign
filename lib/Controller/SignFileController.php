<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\SignFileService;
use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class SignFileController extends ApiController {
	/** @var IL10N */
	protected $l10n;
	/** @var FileUserMapper */
	private $fileUserMapper;
	/** @var FileMapper */
	private $fileMapper;
	/** @var IUserSession */
	private $userSession;
	/** @var SignFileService */
	protected $signFileService;
	/** @var FileService */
	private $fileService;
	/** @var ValidateHelper */
	protected $validateHelper;
	/** @var LoggerInterface */
	private $logger;

	public function __construct(
		IRequest $request,
		IL10N $l10n,
		FileUserMapper $fileUserMapper,
		FileMapper $fileMapper,
		IUserSession $userSession,
		ValidateHelper $validateHelper,
		SignFileService $signFileService,
		FileService $fileService,
		LoggerInterface $logger
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->l10n = $l10n;
		$this->fileUserMapper = $fileUserMapper;
		$this->fileMapper = $fileMapper;
		$this->userSession = $userSession;
		$this->validateHelper = $validateHelper;
		$this->signFileService = $signFileService;
		$this->fileService = $fileService;
		$this->logger = $logger;
	}

	/**
	 * Request signature
	 *
	 * Request that a file be signed by a group of people
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param array $file
	 * @param array $users
	 * @param string $name
	 * @param string|null $callback
	 * @return JSONResponse
	 */
	public function requestSign(array $file, array $users, string $name, ?string $callback = null, ?int $status = 1) {
		$user = $this->userSession->getUser();
		$data = [
			'file' => $file,
			'name' => $name,
			'users' => $users,
			'status' => $status,
			'callback' => $callback,
			'userManager' => $user
		];
		try {
			$this->signFileService->validateNewRequestToFile($data);
			$return = $this->signFileService->save($data);
			unset(
				$return['id'],
				$return['users'],
			);
		} catch (\Throwable $th) {
			return new JSONResponse(
				[
					'message' => $th->getMessage(),
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
		return new JSONResponse(
			[
				'message' => $this->l10n->t('Success'),
				'data' => $return
			],
			Http::STATUS_OK
		);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $uuid
	 * @param array $users
	 * @return JSONResponse
	 */
	public function updateSign(?array $users = [], ?string $uuid = null, ?array $visibleElements = null, ?array $file = [], ?int $status = null) {
		$user = $this->userSession->getUser();
		$data = [
			'uuid' => $uuid,
			'file' => $file,
			'users' => $users,
			'userManager' => $user,
			'status' => $status,
			'visibleElements' => $visibleElements
		];
		try {
			$this->signFileService->validateUserManager($data);
			$this->validateHelper->validateExistingFile($data);
			$this->validateHelper->validateFileStatus($data);
			if (!empty($data['visibleElements'])) {
				$this->validateHelper->validateVisibleElements($data['visibleElements'], $this->validateHelper::TYPE_VISIBLE_ELEMENT_PDF);
			}
			$return = $this->signFileService->save($data);
			unset(
				$return['id'],
				$return['users'],
			);
		} catch (\Throwable $th) {
			return new JSONResponse(
				[
					'message' => $th->getMessage(),
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
		return new JSONResponse(
			[
				'message' => $this->l10n->t('Success'),
				'data' => $return
			],
			Http::STATUS_OK
		);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @NoCSRFRequired
	 *
	 * @param int $fileId
	 * @param string $password
	 *
	 * @return JSONResponse
	 */
	public function signUsingFileId(int $fileId, string $password = null, array $elements = [], string $code = null): JSONResponse {
		return $this->sign($password, $fileId, null, $elements, $code);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $uuid
	 * @param string $password
	 * @return JSONResponse
	 */
	public function signUsingUuid(string $uuid, string $password = null, array $elements = [], string $code = null): JSONResponse {
		return $this->sign($password, null, $uuid, $elements, $code);
	}

	public function sign(string $password = null, int $fileId = null, string $fileUserUuid = null, array $elements = [], string $code = null): JSONResponse {
		try {
			$user = $this->userSession->getUser();
			$this->validateHelper->canSignWithIdentificationDocumentStatus(
				$this->fileService->getIdentificationDocumentsStatus($user->getUID())
			);
			$libreSignFile = $this->signFileService->getLibresignFile($fileId, $fileUserUuid);
			$fileUser = $this->signFileService->getFileUserToSign($libreSignFile, $user);
			$this->validateHelper->validateVisibleElementsRelation($elements, $fileUser);
			$this->validateHelper->validateCredentials($fileUser, [
				'password' => $password,
				'code' => $code,
			]);
			$this->signFileService
				->setLibreSignFile($libreSignFile)
				->setFileUser($fileUser)
				->setVisibleElements($elements)
				->setSignWithoutPassword(!empty($code))
				->setPassword($password)
				->sign();

			return new JSONResponse(
				[
					'success' => true,
					'action' => JSActions::ACTION_SIGNED,
					'message' => $this->l10n->t('File signed'),
					'file' => [
						'uuid' => $libreSignFile->getUuid()
					]
				],
				Http::STATUS_OK
			);
		} catch (LibresignException $e) {
			return new JSONResponse(
				[
					'success' => false,
					'action' => JSActions::ACTION_DO_NOTHING,
					'errors' => [$e->getMessage()]
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		} catch (\Throwable $th) {
			$message = $th->getMessage();
			$action = JSActions::ACTION_DO_NOTHING;
			switch ($message) {
				case 'Password to sign not defined. Create a password to sign':
					$action = JSActions::ACTION_CREATE_SIGNATURE_PASSWORD;
					// no break
				case 'Host violates local access rules.':
				case 'Certificate Password Invalid.':
				case 'Certificate Password is Empty.':
					$message = $this->l10n->t($message);
					break;
				default:
					$this->logger->error($message);
					$message = $this->l10n->t('Internal error. Contact admin.');
			}
		}
		return new JSONResponse(
			[
				'success' => false,
				'action' => $action,
				'errors' => [$message]
			],
			Http::STATUS_UNPROCESSABLE_ENTITY
		);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param integer $fileId
	 * @param integer $fileUserId
	 * @return JSONResponse
	 */
	public function deleteOneSignRequestUsingFileId(int $fileId, int $fileUserId) {
		try {
			$data = [
				'userManager' => $this->userSession->getUser(),
				'file' => [
					'fileId' => $fileId
				]
			];
			$this->signFileService->validateUserManager($data);
			$this->validateHelper->validateExistingFile($data);
			$this->validateHelper->validateIsSignerOfFile($fileUserId, $fileId);
			$this->signFileService->unassociateToUser($fileId, $fileUserId);
		} catch (\Throwable $th) {
			return new JSONResponse(
				[
					'message' => $th->getMessage(),
				],
				Http::STATUS_UNAUTHORIZED
			);
		}
		return new JSONResponse(
			[
				'success' => true,
				'message' => $this->l10n->t('Success')
			],
			Http::STATUS_OK
		);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param integer $fileId
	 * @return JSONResponse
	 */
	public function deleteAllSignRequestUsingFileId(int $fileId) {
		try {
			$data = [
				'userManager' => $this->userSession->getUser(),
				'file' => [
					'fileId' => $fileId
				]
			];
			$this->signFileService->validateUserManager($data);
			$this->validateHelper->validateExistingFile($data);
			$this->signFileService->deleteSignRequest($data);
		} catch (\Throwable $th) {
			return new JSONResponse(
				[
					'message' => $th->getMessage(),
				],
				Http::STATUS_UNAUTHORIZED
			);
		}
		return new JSONResponse(
			[
				'success' => true,
				'message' => $this->l10n->t('Success')
			],
			Http::STATUS_OK
		);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $uuid
	 * @param array $users
	 * @return JSONResponse
	 */
	public function getCodeUsingUuid(string $uuid): JSONResponse {
		return $this->getCode($uuid);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $uuid
	 * @param array $users
	 * @return JSONResponse
	 */
	public function getCodeUsingFileId(string $fileId): JSONResponse {
		return $this->getCode(null, $fileId);
	}

	private function getCode(string $uuid = null, int $fileId = null): JSONResponse {
		$statusCode = null;
		try {
			try {
				$user = $this->userSession->getUser();
				if ($fileId) {
					$fileUser = $this->fileUserMapper->getByFileIdAndUserId($fileId, $user->getUID());
				} else {
					$fileUser = $this->fileUserMapper->getByUuidAndUserId($uuid, $user->getUID());
				}
			} catch (\Throwable $th) {
				throw new LibresignException($this->l10n->t('Invalid data to sign file'), 1);
			}
			$this->validateHelper->canRequestCode();
			$libreSignFile = $this->fileMapper->getById($fileUser->getFileId());
			$this->validateHelper->fileCanBeSigned($libreSignFile);
			$this->signFileService->requestCode($fileUser, $user);
			$success = true;
			$message = $this->l10n->t('The code to sign file was successfully requested.');
		} catch (SmsTransmissionException $e) {
			$success = false;
			$message = $this->l10n->t('Failed to send code.');
			$statusCode = Http::STATUS_UNPROCESSABLE_ENTITY;
		} catch (\Throwable $th) {
			$success = false;
			$message = $th->getMessage();
			$statusCode = Http::STATUS_UNPROCESSABLE_ENTITY;
		}
		return new JSONResponse(
			[
				'success' => $success,
				'message' => [$message],
			],
			$statusCode,
		);
	}
}
