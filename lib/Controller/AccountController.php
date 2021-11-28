<?php

namespace OCA\Libresign\Controller;

use OC\Authentication\Login\Chain;
use OC\Authentication\Login\LoginData;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\Pkcs12Handler;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\AccountFileService;
use OCA\Libresign\Service\AccountService;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;

class AccountController extends ApiController {
	/** @var IL10N */
	private $l10n;
	/** @var IAccountManager */
	private $accountManager;
	/** @var AccountService */
	private $accountService;
	/** @var AccountFileService */
	private $accountFileService;
	/** @var pkcs12Handler */
	private $pkcs12Handler;
	/** @var IConfig */
	private $config;
	/** @var Chain */
	private $loginChain;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var IUserSession */
	private $userSession;
	/** @var ValidateHelper */
	private $validateHelper;

	public function __construct(
		IRequest $request,
		IL10N $l10n,
		IAccountManager $accountManager,
		AccountService $accountService,
		AccountFileService $accountFileService,
		Pkcs12Handler $pkcs12Handler,
		IConfig $config,
		Chain $loginChain,
		IURLGenerator $urlGenerator,
		IUserSession $userSession,
		ValidateHelper $validateHelper
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->l10n = $l10n;
		$this->accountManager = $accountManager;
		$this->accountService = $accountService;
		$this->accountFileService = $accountFileService;
		$this->pkcs12Handler = $pkcs12Handler;
		$this->config = $config;
		$this->loginChain = $loginChain;
		$this->urlGenerator = $urlGenerator;
		$this->userSession = $userSession;
		$this->validateHelper = $validateHelper;
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 * @PublicPage
	 * @UseSession
	 * @return JSONResponse
	 */
	public function createToSign(string $uuid, string $email, string $password, ?string $signPassword) {
		try {
			$data = [
				'uuid' => $uuid,
				'email' => $email,
				'password' => $password,
				'signPassword' => $signPassword
			];
			$this->accountService->validateCreateToSign($data);
			if ($signPassword) {
				$this->accountService->validateCertificateData($data);
			}

			$fileToSign = $this->accountService->getFileByUuid($uuid);
			$fileUser = $this->accountService->getFileUserByUuid($uuid);

			$this->accountService->createToSign($uuid, $email, $password, $signPassword);
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
			$this->accountService->validateCertificateData($data);
			$signaturePath = $this->pkcs12Handler->generateCertificate(...array_values($data));

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
	public function addFiles(array $files): JSONResponse {
		try {
			$this->accountService->addFilesToAccount($files, $this->userSession->getUser());
			return new JSONResponse([
				'success' => true
			], Http::STATUS_OK);
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
			return new JSONResponse(
				[
					'success' => false,
					'messages' => [
						$message
					]
				],
				Http::STATUS_UNAUTHORIZED
			);
		}
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
				'account' => [
					'uid' => $user->getUID(),
					'displayName' => $user->getDisplayName()
				],
				'settings' => $this->accountService->getSettings($this->userSession->getUser())
			],
			Http::STATUS_OK
		);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function createSignatureElement(array $elements) {
		try {
			$this->validateHelper->validateVisibleElements($elements, $this->validateHelper::TYPE_VISIBLE_ELEMENT_USER);
			$this->accountService->saveVisibleElements($elements, $this->userSession->getUser());
		} catch (\Throwable $th) {
			return new JSONResponse(
				[
					'success' => false,
					'message' => $th->getMessage()
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
		return new JSONResponse(
			[
				'success' => true,
				'message' => $this->l10n->n(
					'Element created with success',
					'Elements created with success',
					count($elements)
				)
			],
			Http::STATUS_OK
		);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getSignatureElements() {
		$userId = $this->userSession->getUser()->getUID();
		try {
			return new JSONResponse(
				[
					'elements' => $this->accountService->getUserElements($userId)
				],
				Http::STATUS_OK
			);
		} catch (\Throwable $th) {
			return new JSONResponse(
				[
					'message' => $this->l10n->t('Elements not found')
				],
				Http::STATUS_NOT_FOUND
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getSignatureElement($elementId) {
		$userId = $this->userSession->getUser()->getUID();
		try {
			return new JSONResponse(
				$this->accountService->getUserElementByElementId($userId, $elementId),
				Http::STATUS_OK
			);
		} catch (\Throwable $th) {
			return new JSONResponse(
				[
					'message' => $this->l10n->t('Element not found')
				],
				Http::STATUS_NOT_FOUND
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function patchSignatureElement($elementId, string $type = '', array $file = []) {
		try {
			$element['elementId'] = $elementId;
			if ($type) {
				$element['type'] = $type;
			}
			if ($file) {
				$element['file'] = $file;
			}
			$this->validateHelper->validateVisibleElement($element, $this->validateHelper::TYPE_VISIBLE_ELEMENT_USER);
			$this->accountService->saveVisibleElement($element, $this->userSession->getUser());
			return new JSONResponse(
				[
					'success' => true,
					'message' => $this->l10n->t('Element updated with success')
				],
				Http::STATUS_OK
			);
		} catch (\Throwable $th) {
			return new JSONResponse(
				[
					'success' => false,
					'message' => $th->getMessage()
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function deleteSignatureElement($elementId) {
		$userId = $this->userSession->getUser()->getUID();
		try {
			$this->accountService->deleteSignatureElement($userId, $elementId);
		} catch (\Throwable $th) {
			return new JSONResponse(
				[
					'message' => $this->l10n->t('Element not found')
				],
				Http::STATUS_NOT_FOUND
			);
		}
		return new JSONResponse(
			[
				'message' => $this->l10n->t('Visible element deleted')
			],
			Http::STATUS_OK
		);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function accountFileList(array $filter = [], $page = null, $length = null): JSONResponse {
		try {
			$this->validateHelper->userCanApproveValidationDocuments($this->userSession->getUser());
			$return = $this->accountFileService->accountFileList($filter, $page, $length);
			return new JSONResponse($return, Http::STATUS_OK);
		} catch (\Throwable $th) {
			return new JSONResponse(
				[
					'message' => $th->getMessage()
				],
				Http::STATUS_NOT_FOUND
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function updateSettings(?string $phone = null): JSONResponse {
		try {
			$user = $this->userSession->getUser();
			$userAccount = $this->accountManager->getAccount($user);
			$updatable = [
				IAccountManager::PROPERTY_PHONE => ['value' => $phone],
			];
			foreach ($updatable as $property => $data) {
				$property = $userAccount->getProperty($property);
				if (null !== $data['value']) {
					$property->setValue($data['value']);
				}
			}
			$this->accountManager->updateAccount($userAccount);
		} catch (\Throwable $th) {
			return new JSONResponse(
				[
					'success' => false,
					'message' => $th->getMessage(),
				],
				Http::STATUS_NOT_FOUND
			);
		}
		return new JSONResponse(
			[
				'success' => true,
				'data' => [
					'userId' => $user->getUID(),
					'phone' => $userAccount->getProperty(IAccountManager::PROPERTY_PHONE)->getValue(),
					'message' => $this->l10n->t('Settings saved'),
				],
			],
			Http::STATUS_OK
		);
	}
}
