<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\JLibresignHandler;
use OCA\Libresign\Handler\PkcsHandler;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\MailService;
use OCA\Libresign\Service\SignFileService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class SignFileController extends ApiController {
	use HandleParamsTrait;

	/** @var IL10N */
	private $l10n;
	/** @var IUserSession */
	private $userSession;
	/** @var FileUserMapper */
	private $fileUserMapper;
	/** @var FileMapper */
	private $fileMapper;
	/** @var IRootFolder */
	private $root;
	/** @var PkcsHandler */
	private $pkcsHandler;
	/** @var SignFileService */
	private $signFile;
	/** @var AccountService */
	private $account;
	/** @var MailService */
	private $mail;
	/** @var LoggerInterface */
	private $logger;
	/** @var JLibresignHandler */
	private $libresignHandler;
	/** @var IConfig */
	private $config;

	public function __construct(
		IRequest $request,
		IL10N $l10n,
		FileUserMapper $fileUserMapper,
		FileMapper $fileMapper,
		IRootFolder $root,
		PkcsHandler $pkcsHandler,
		IUserSession $userSession,
		AccountService $account,
		SignFileService $signFile,
		JLibresignHandler $libresignHandler,
		MailService $mail,
		LoggerInterface $logger,
		IConfig $config
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->l10n = $l10n;
		$this->fileUserMapper = $fileUserMapper;
		$this->fileMapper = $fileMapper;
		$this->root = $root;
		$this->pkcsHandler = $pkcsHandler;
		$this->userSession = $userSession;
		$this->account = $account;
		$this->signFile = $signFile;
		$this->libresignHandler = $libresignHandler;
		$this->mail = $mail;
		$this->logger = $logger;
		$this->config = $config;
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
	 * @CORS
	 * @NoCSRFRequired
	 * @return JSONResponse
	 */
	public function updateSign(string $uuid, array $users) {
		$user = $this->userSession->getUser();
		$data = [
			'uuid' => $uuid,
			'users' => $users,
			'userManager' => $user
		];
		try {
			$this->signFile->validateUserManager($data);
			$this->signFile->validateFileUuid($data);
			$this->signFile->validateUsers($data);
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
	 * @CORS
	 * @NoCSRFRequired
	 * @return JSONResponse
	 */
	public function removeSign(string $uuid, array $users) {
		$user = $this->userSession->getUser();
		$data = [
			'uuid' => $uuid,
			'users' => $users,
			'userManager' => $user
		];
		try {
			$this->signFile->validateUserManager($data);
			$this->signFile->validateFileUuid($data);
			$this->signFile->validateUsers($data);
			$this->signFile->canDeleteSignRequest($data);
			$deletedUsers = $this->signFile->deleteSignRequest($data);
			foreach ($deletedUsers as $user) {
				$this->mail->notifyUnsignedUser($user);
			}
		} catch (\Throwable $th) {
			$message = $th->getMessage();
			return new JSONResponse(
				[
					'message' => $message,
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
		return new JSONResponse(
			[
				'message' => $this->l10n->t('Success')
			],
			Http::STATUS_OK
		);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @todo remove NoCSRFRequired
	 * @deprecated
	 */
	public function signDeprecated(
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

			$fileSigned = $this->signFile->signDeprecated($inputFilePath, $outputFolderPath, $certificatePath, $password);

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
	public function signUsingFileid(string $fileId, string $password): JSONResponse {
		return $this->sign($password, $fileId);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
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
			$fileData = $this->fileMapper->getById($fileUser->getFileId());
			$signedFile = $this->signFile->sign($fileData, $fileUser, $password);

			$fileToSign = $this->signFile->getFileToSing($fileData);
			$certificatePath = $this->pkcsHandler->getPfx($fileUser->getUserId());
			list(, $signedContent) = $this->libresignHandler->signExistingFile($fileToSign, $certificatePath, $password);
			$fileToSign->putContent($signedContent);
			$fileUser->setSigned(time());
			$this->fileUserMapper->update($fileUser);

			$signers = $this->fileUserMapper->getByFileId($fileUser->getFileId());
			$total = array_reduce($signers, function ($carry, $signer) {
				$carry += $signer->getSigned() ? 1 : 0;
				return $carry;
			});
			if (count($signers) === $total) {
				$callbackUrl = $fileData->getCallback();
				if ($callbackUrl) {
					$this->signFile->notifyCallback(
						$callbackUrl,
						$fileData->getUuid(),
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
			$this->logger->error($message);
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
}
