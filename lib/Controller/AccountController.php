<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use InvalidArgumentException;
use OC\Authentication\Login\Chain;
use OC\Authentication\Login\LoginData;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\AccountFileMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\Pkcs12Handler;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Service\AccountFileService;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\SessionService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Service\SignFileService;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\CORS;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IPreview;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type LibresignCertificatePfxData from ResponseDefinitions
 * @psalm-import-type LibresignFile from ResponseDefinitions
 * @psalm-import-type LibresignPagination from ResponseDefinitions
 */
class AccountController extends AEnvironmentAwareController implements ISignatureUuid {
	use LibresignTrait;
	public function __construct(
		IRequest $request,
		protected IL10N $l10n,
		private IAccountManager $accountManager,
		private AccountService $accountService,
		private AccountFileService $accountFileService,
		private AccountFileMapper $accountFileMapper,
		protected SignFileService $signFileService,
		private SignerElementsService $signerElementsService,
		private Pkcs12Handler $pkcs12Handler,
		private Chain $loginChain,
		private IURLGenerator $urlGenerator,
		private LoggerInterface $logger,
		protected IUserSession $userSession,
		protected SessionService $sessionService,
		private IPreview $preview,
		private ValidateHelper $validateHelper,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Create account to sign a document
	 *
	 * @param string $uuid Sign request uuid to allow account creation
	 * @param string $email email to the new account
	 * @param string $password the password to then new account
	 * @param string|null $signPassword The password to create certificate
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNPROCESSABLE_ENTITY, array{action: 2000|2500, description?: null|string, filename?: string, message: string, pdf?: array{url: string}}|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array{message: string,action: int}, array{}>
	 *
	 * 200: OK
	 * 422: Validation page not accessible if unauthenticated
	 */
	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[PublicPage]
	#[UseSession]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/account/create/{uuid}', requirements: ['apiVersion' => '(v1)'])]
	public function createToSign(string $uuid, string $email, string $password, ?string $signPassword): DataResponse {
		try {
			$data = [
				'uuid' => $uuid,
				'user' => [
					'identify' => [
						'email' => $email,
					]
				],
				'password' => $password,
				'signPassword' => $signPassword
			];
			$this->accountService->validateCreateToSign($data);

			$fileToSign = $this->accountService->getFileByUuid($uuid);
			$signRequest = $this->accountService->getSignRequestByUuid($uuid);

			$this->accountService->createToSign($uuid, $email, $password, $signPassword);
			$data = [
				'message' => $this->l10n->t('Success'),
				'action' => JSActions::ACTION_SIGN,
				'pdf' => [
					'url' => $this->urlGenerator->linkToRoute('libresign.page.getPdfFile', ['uuid' => $uuid])
				],
				'filename' => $fileToSign['fileData']->getName(),
				'description' => $signRequest->getDescription()
			];

			$loginData = new LoginData(
				$this->request,
				trim($email),
				$password
			);
			$this->loginChain->process($loginData);
		} catch (\Throwable $th) {
			return new DataResponse(
				[
					'message' => $th->getMessage(),
					'action' => JSActions::ACTION_DO_NOTHING
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
		return new DataResponse(
			$data,
			Http::STATUS_OK
		);
	}

	/**
	 * Create PFX file using self-signed certificate
	 *
	 * @param string $signPassword The password that will be used to encrypt the certificate file
	 *
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, array{message: string}, array{}>
	 *
	 * 200: Settings saved
	 * 401: Failure to create PFX file
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/account/signature', requirements: ['apiVersion' => '(v1)'])]
	public function signatureGenerate(
		string $signPassword,
	): DataResponse {
		try {
			$identify = $this->userSession->getUser()->getEMailAddress();
			if (!$identify) {
				$identify = $this->userSession->getUser()->getUID()
					. '@'
					. $this->request->getServerHost();
			}
			$data = [
				'user' => [
					'host' => $identify,
					'uid' => 'account:' . $this->userSession->getUser()->getUID(),
					'name' => $this->userSession->getUser()->getDisplayName(),
				],
				'signPassword' => $signPassword,
				'userId' => $this->userSession->getUser()->getUID()
			];
			$this->accountService->validateCertificateData($data);
			$certificate = $this->pkcs12Handler->generateCertificate(
				$data['user'],
				$data['signPassword'],
				$this->userSession->getUser()->getDisplayName()
			);
			$this->pkcs12Handler->savePfx($this->userSession->getUser()->getUID(), $certificate);

			return new DataResponse([], Http::STATUS_OK);
		} catch (\Exception $exception) {
			$this->logger->error($exception->getMessage());
			return new DataResponse(
				[
					'message' => $exception->getMessage()
				],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Who am I
	 *
	 * Validates API access data and returns the authenticated user's data.
	 *
	 * @return DataResponse<Http::STATUS_OK, array{account: array{uid: string, emailAddress: string, displayName: string},settings: array{canRequestSign: bool,hasSignatureFile: bool}}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 404: Invalid user or password
	 */
	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[PublicPage]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/account/me', requirements: ['apiVersion' => '(v1)'])]
	public function me(): DataResponse {
		$user = $this->userSession->getUser();
		if (!$user) {
			return new DataResponse(
				[
					// TRANSLATORS error message when user that wants to access the API does not exists or used an invalid password
					'message' => $this->l10n->t('Invalid user or password')
				],
				Http::STATUS_NOT_FOUND
			);
		}
		return new DataResponse(
			[
				'account' => [
					'uid' => $user->getUID(),
					'emailAddress' => $user->getEMailAddress() ?? '',
					'displayName' => $user->getDisplayName()
				],
				'settings' => $this->accountService->getSettings($this->userSession->getUser())
			],
			Http::STATUS_OK
		);
	}

	/**
	 * Update the account phone number
	 *
	 * @param string|null $phone the phone number to be defined. If null will remove the phone number
	 *
	 * @return DataResponse<Http::STATUS_OK, array{data: array{userId: string, phone: string, message: string}}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Settings saved
	 * 404: Invalid data to update phone number
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'PATCH', url: '/api/{apiVersion}/account/settings', requirements: ['apiVersion' => '(v1)'])]
	public function updateSettings(?string $phone = null): DataResponse {
		try {
			$user = $this->userSession->getUser();
			$userAccount = $this->accountManager->getAccount($user);
			$updatable = [
				IAccountManager::PROPERTY_PHONE => ['value' => $phone],
			];
			foreach ($updatable as $property => $data) {
				$property = $userAccount->getProperty($property);
				if ($data['value'] !== null) {
					$property->setValue($data['value']);
				}
			}
			$this->accountManager->updateAccount($userAccount);
		} catch (\Throwable $th) {
			return new DataResponse(
				[
					'message' => $th->getMessage(),
				],
				Http::STATUS_NOT_FOUND
			);
		}
		return new DataResponse(
			[
				'data' => [
					'userId' => $user->getUID(),
					'phone' => $userAccount->getProperty(IAccountManager::PROPERTY_PHONE)->getValue(),
					// This messages indicates the user's settings saved with sucess
					'message' => $this->l10n->t('Settings saved'),
				],
			],
			Http::STATUS_OK
		);
	}

	/**
	 * Delete PFX file
	 *
	 * @return DataResponse<Http::STATUS_ACCEPTED, array{message: string}, array{}>
	 *
	 * 202: Certificate deleted with success
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'delete', url: '/api/{apiVersion}/account/pfx', requirements: ['apiVersion' => '(v1)'])]
	public function deletePfx(): DataResponse {
		$this->accountService->deletePfx($this->userSession->getUser());
		return new DataResponse(
			[
				// TRANSLATORS Feedback to user after delete the certificate file that is used to sign documents with success
				'message' => $this->l10n->t('Certificate file deleted with success.')
			],
			Http::STATUS_ACCEPTED
		);
	}

	/**
	 * Upload PFX file
	 *
	 * @return DataResponse<Http::STATUS_ACCEPTED, array{message: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 202: Certificate saved with success
	 * 400: No file provided or other problem with provided file
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/account/pfx', requirements: ['apiVersion' => '(v1)'])]
	public function uploadPfx(): DataResponse {
		$file = $this->request->getUploadedFile('file');
		try {
			if (empty($file)) {
				throw new LibresignException($this->l10n->t('No certificate file provided'));
			}
			$this->accountService->uploadPfx($file, $this->userSession->getUser());
		} catch (InvalidArgumentException|LibresignException $e) {
			return new DataResponse(
				[
					'message' => $e->getMessage()
				],
				Http::STATUS_BAD_REQUEST
			);
		}
		return new DataResponse(
			[
				// TRANSLATORS Feedback to user after upload the certificate file that is used to sign documents with success
				'message' => $this->l10n->t('Certificate file saved with success.')
			],
			Http::STATUS_ACCEPTED
		);
	}

	/**
	 * Update PFX file
	 *
	 * Used to change the password of PFX file
	 *
	 * @param string $current Current password
	 * @param string $new New password
	 *
	 * @return DataResponse<Http::STATUS_ACCEPTED, array{message: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 202: Certificate saved with success
	 * 400: No file provided or other problem with provided file
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'PATCH', url: '/api/{apiVersion}/account/pfx', requirements: ['apiVersion' => '(v1)'])]
	public function updatePfxPassword($current, $new): DataResponse {
		try {
			$this->accountService->updatePfxPassword($this->userSession->getUser(), $current, $new);
		} catch (LibresignException $e) {
			return new DataResponse(
				[
					'message' => $e->getMessage()
				],
				Http::STATUS_BAD_REQUEST
			);
		}
		return new DataResponse(
			[
				// TRANSLATORS Feedback to user after change the certificate file that is used to sign documents with success
				'message' => $this->l10n->t('New password to sign documents has been created')
			],
			Http::STATUS_ACCEPTED
		);
	}

	/**
	 * Read content of PFX file
	 *
	 * @param string $password password of PFX file to decrypt the file and return his content
	 *
	 * @return DataResponse<Http::STATUS_ACCEPTED, LibresignCertificatePfxData, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 202: Certificate saved with success
	 * 400: No file provided or other problem with provided file
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/account/pfx/read', requirements: ['apiVersion' => '(v1)'])]
	public function readPfxData(string $password): DataResponse {
		try {
			$data = $this->accountService->readPfxData($this->userSession->getUser(), $password);
		} catch (LibresignException $e) {
			return new DataResponse(
				[
					'message' => $e->getMessage()
				],
				Http::STATUS_BAD_REQUEST
			);
		}
		return new DataResponse(
			$data,
			Http::STATUS_ACCEPTED
		);
	}
}
