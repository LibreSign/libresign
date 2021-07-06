<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\SignFileService;
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
	/** @var IUserSession */
	private $userSession;
	/** @var FileUserMapper */
	private $fileUserMapper;
	/** @var FileMapper */
	private $fileMapper;
	/** @var SignFileService */
	protected $signFile;
	/** @var LoggerInterface */
	private $logger;

	public function __construct(
		IRequest $request,
		IL10N $l10n,
		FileUserMapper $fileUserMapper,
		FileMapper $fileMapper,
		IUserSession $userSession,
		ValidateHelper $validateHelper,
		SignFileService $signFile,
		LoggerInterface $logger
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->l10n = $l10n;
		$this->fileUserMapper = $fileUserMapper;
		$this->fileMapper = $fileMapper;
		$this->userSession = $userSession;
		$this->validateHelper = $validateHelper;
		$this->signFile = $signFile;
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
	public function requestSign(array $file, array $users, string $name, ?string $callback = null) {
		$user = $this->userSession->getUser();
		$data = [
			'file' => $file,
			'name' => $name,
			'users' => $users,
			'callback' => $callback,
			'userManager' => $user
		];
		try {
			$this->signFile->validate($data);
			$return = $this->signFile->save($data);
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
	public function updateSign(array $users, ?string $uuid = null, ?array $file = []) {
		$user = $this->userSession->getUser();
		$data = [
			'uuid' => $uuid,
			'file' => $file,
			'users' => $users,
			'userManager' => $user
		];
		try {
			$this->signFile->validateUserManager($data);
			$this->signFile->validateExistingFile($data);
			$return = $this->signFile->save($data);
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
	 * @param string $fileId
	 * @param string $password
	 * @return JSONResponse
	 */
	public function signUsingFileid(string $fileId, string $password): JSONResponse {
		return $this->sign($password, $fileId);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $uuid
	 * @param string $password
	 * @return JSONResponse
	 */
	public function signUsingUuid(string $uuid, string $password): JSONResponse {
		return $this->sign($password, null, $uuid);
	}

	public function sign(string $password, string $file_id = null, string $uuid = null): JSONResponse {
		try {
			try {
				$user = $this->userSession->getUser();
				if ($file_id) {
					$fileUser = $this->fileUserMapper->getByFileIdAndUserId($file_id, $user->getUID());
				} else {
					$fileUser = $this->fileUserMapper->getByUuidAndUserId($uuid, $user->getUID());
				}
			} catch (\Throwable $th) {
				throw new LibresignException($this->l10n->t('Invalid data to sign file'), 1);
			}
			if ($fileUser->getSigned()) {
				throw new LibresignException($this->l10n->t('File already signed by you'), 1);
			}
			$libreSignFile = $this->fileMapper->getById($fileUser->getFileId());
			$signedFile = $this->signFile->sign($libreSignFile, $fileUser, $password);

			$signers = $this->fileUserMapper->getByFileId($fileUser->getFileId());
			$total = array_reduce($signers, function ($carry, $signer) {
				$carry += $signer->getSigned() ? 1 : 0;
				return $carry;
			});
			if (count($signers) === $total) {
				$callbackUrl = $libreSignFile->getCallback();
				if ($callbackUrl) {
					$this->signFile->notifyCallback(
						$callbackUrl,
						$libreSignFile->getUuid(),
						$signedFile
					);
				}
			}

			return new JSONResponse(
				[
					'success' => true,
					'action' => JSActions::ACTION_SIGNED,
					'message' => $this->l10n->t('File signed')
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
			return new JSONResponse(
				[
					'success' => false,
					'action' => $action,
					'errors' => [$message]
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param integer $fileId
	 * @param integer $signatureId
	 * @return JSONResponse
	 */
	public function deleteOneSignRequestUsingFileId(int $fileId, int $signatureId) {
		try {
			$data = [
				'userManager' => $this->userSession->getUser(),
				'file' => [
					'fileId' => $fileId
				]
			];
			$this->signFile->validateUserManager($data);
			$this->signFile->validateExistingFile($data);
			$this->validateHelper->validateIsSignerOfFile($signatureId, $fileId);
			$this->signFile->unassociateToUser($fileId, $signatureId);
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
			$this->signFile->validateUserManager($data);
			$this->signFile->validateExistingFile($data);
			$this->signFile->deleteSignRequest($data);
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
}
