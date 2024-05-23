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
use OCA\Libresign\Service\AccountFileService;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\SessionService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Service\SignFileService;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\CORS;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IPreview;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

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
		private ValidateHelper $validateHelper
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[PublicPage]
	#[UseSession]
	public function createToSign(string $uuid, string $email, string $password, ?string $signPassword): JSONResponse {
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

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function signatureGenerate(
		string $signPassword
	): JSONResponse {
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

			return new JSONResponse([], Http::STATUS_OK);
		} catch (\Exception $exception) {
			$this->logger->error($exception->getMessage());
			return new JSONResponse(
				[
					'message' => $exception->getMessage()
				],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function addFiles(array $files): JSONResponse {
		try {
			$this->accountService->addFilesToAccount($files, $this->userSession->getUser());
			return new JSONResponse([], Http::STATUS_OK);
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
					'messages' => [
						$message
					]
				],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function deleteFile(int $nodeId): JSONResponse {
		try {
			$this->accountService->deleteFileFromAccount($nodeId, $this->userSession->getUser());
			return new JSONResponse([], Http::STATUS_OK);
		} catch (\Exception $exception) {
			return new JSONResponse(
				[
					'messages' => [
						$exception->getMessage(),
					],
				],
				Http::STATUS_UNAUTHORIZED,
			);
		}
	}

	/**
	 * Who am I.
	 *
	 * Validates API access data and returns the authenticated user's data.
	 */
	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[PublicPage]
	public function me(): JSONResponse {
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(
				[
					// TRANSLATORS error message when user that wants to access the API does not exists or used an invalid password
					'message' => $this->l10n->t('Invalid user or password')
				],
				Http::STATUS_NOT_FOUND
			);
		}
		return new JSONResponse(
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

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function accountFileListToOwner(array $filter = [], $page = null, $length = null): JSONResponse {
		try {
			$filter['userId'] = $this->userSession->getUser()->getUID();
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

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function accountFileListToApproval(array $filter = [], $page = null, $length = null): JSONResponse {
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

	#[NoAdminRequired]
	#[NoCSRFRequired]
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
					'message' => $th->getMessage(),
				],
				Http::STATUS_NOT_FOUND
			);
		}
		return new JSONResponse(
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

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function deletePfx(): JSONResponse {
		$this->accountService->deletePfx($this->userSession->getUser());
		return new JSONResponse(
			[
				// TRANSLATORS Feedback to user after delete the certificate file that is used to sign documents with success
				'message' => $this->l10n->t('Certificate file deleted with success.')
			],
			Http::STATUS_ACCEPTED
		);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function uploadPfx(): JSONResponse {
		$file = $this->request->getUploadedFile('file');
		try {
			if (empty($file)) {
				throw new LibresignException($this->l10n->t('No certificate file provided'));
			}
			$this->accountService->uploadPfx($file, $this->userSession->getUser());
		} catch (InvalidArgumentException|LibresignException $e) {
			return new JSONResponse(
				[
					'message' => $e->getMessage()
				],
				Http::STATUS_BAD_REQUEST
			);
		}
		return new JSONResponse(
			[
				// TRANSLATORS Feedback to user after upload the certificate file that is used to sign documents with success
				'message' => $this->l10n->t('Certificate file saved with success.')
			],
			Http::STATUS_ACCEPTED
		);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function updatePfxPassword($current, $new): JSONResponse {
		try {
			$this->accountService->updatePfxPassword($this->userSession->getUser(), $current, $new);
		} catch (LibresignException $e) {
			return new JSONResponse(
				[
					'message' => $e->getMessage()
				],
				Http::STATUS_BAD_REQUEST
			);
		}
		return new JSONResponse(
			[
				// TRANSLATORS Feedback to user after change the certificate file that is used to sign documents with success
				'message' => $this->l10n->t('New password to sign documents has been created')
			],
			Http::STATUS_ACCEPTED
		);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function readPfxData(string $password): JSONResponse {
		try {
			$data = $this->accountService->readPfxData($this->userSession->getUser(), $password);
			$array_map_recursive = function ($callback, $array) {
				$func = function ($item) use (&$func, &$callback) {
					return is_array($item) ? array_map($func, $item) : call_user_func($callback, $item);
				};
				return array_map($func, $array);
			};
			$data = $array_map_recursive(function ($text) {
				return mb_convert_encoding($text, 'UTF-8', 'UTF-8');
			}, $data);
		} catch (LibresignException $e) {
			return new JSONResponse(
				[
					'message' => $e->getMessage()
				],
				Http::STATUS_BAD_REQUEST
			);
		}
		return new JSONResponse(
			$data,
			Http::STATUS_ACCEPTED
		);
	}
}
