<?php

namespace OCA\Libresign\Controller;

use OC\Authentication\Login\Chain;
use OC\Authentication\Login\LoginData;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\MailService;
use OCA\Libresign\Service\SignFileService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;

class AccountController extends ApiController {
	/** @var IL10N */
	private $l10n;
	/** @var AccountService */
	private $account;
	/** @var Chain */
	private $loginChain;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var IUserSession */
	private $userSession;
	/** @var SignFileService */
	private $signFile;
	/** @var MailService */
	private $mail;

	public function __construct(
		IRequest $request,
		IL10N $l10n,
		AccountService $account,
		Chain $loginChain,
		IURLGenerator $urlGenerator,
		IUserSession $userSession,
		SignFileService $signFile,
		MailService $mail
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->l10n = $l10n;
		$this->account = $account;
		$this->loginChain = $loginChain;
		$this->urlGenerator = $urlGenerator;
		$this->userSession = $userSession;
		$this->signFile = $signFile;
		$this->mail = $mail;
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 * @PublicPage
	 * @UseSession
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
			$this->account->validateCertificateData($data);

			$fileToSign = $this->account->getFileByUuid($uuid);
			$fileUser = $this->account->getFileUserByUuid($uuid);

			$this->account->createToSign($uuid, $email, $password, $signPassword);
			$data = [
				'success' => true,
				'message' => $this->l10n->t('Success'),
				'action' => JSActions::ACTION_SIGN,
				'pdf' => [
					'url' => $this->urlGenerator->linkToRoute('libresign.page.getPdfUser', ['uuid' => $uuid])
				],
				'filename' => $fileToSign['fileData']->getName(),
				'description' => $fileUser->getDescription()
			];

			$loginData = new LoginData(
				$this->request,
				trim($email),
				$password
			);
			$this->loginChain->process($loginData);
		} catch (\Throwable $th) {
			return new JSONResponse(
				[
					'success' => false,
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

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function signatureGenerate(
		string $signPassword
	): JSONResponse {
		try {
			$data = [
				'email' => $this->userSession->getUser()->getEMailAddress(),
				'signPassword' => $signPassword,
				'userId' => $this->userSession->getUser()->getUID()
			];
			$this->account->validateCertificateData($data);
			$signaturePath = $this->account->generateCertificate(...array_values($data));

			return new JSONResponse([
				'success' => true,
				'signature' => $signaturePath->getPath()
			], Http::STATUS_OK);
		} catch (\Exception $exception) {
			return new JSONResponse(
				[
					'success' => false,
					'message' => $exception->getMessage()
				],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function profileAttachFiles(array $files): JSONResponse {
		try {
			$this->account->addFilesToUserProfile($files, $this->userSession->getUser());
			return new JSONResponse([
				'success' => true
			], Http::STATUS_OK);
		} catch (\Exception $exception) {
			$exceptionData = json_decode($exception->getMessage());
			return new JSONResponse(
				[
					'success' => false,
					'messages' => [
						[
							'file' => $exceptionData->file,
							'type' => $exceptionData->type,
							'message' => $exceptionData->message
						]
					]
				],
				Http::STATUS_UNAUTHORIZED
			);
		}
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
	public function register(array $file, array $users, string $name, ?string $callback = null) {
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
	public function update(string $uuid, array $users) {
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
	public function removeSignature(string $uuid, array $users) {
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
	 * Who am I.
	 *
	 * Validates API access data and returns the authenticated user's data.
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 * @PublicPage
	 * @return JSONResponse
	 */
	public function me() {
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(
				[
					'message' => $this->l10n->t('Invalid user or password')
				],
				Http::STATUS_NOT_FOUND
			);
		}
		return new JSONResponse(
			[
				'uid' => $user->getUID()
			],
			Http::STATUS_OK
		);
	}
}
