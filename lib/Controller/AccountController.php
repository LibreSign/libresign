<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
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

	/**
	 * Create account to sign a document
	 *
	 * @param string $uuid Sign request uuid to allow account creation
	 * @param string $email email to the new account
	 * @param string $password the password to then new account
	 * @param ?string $signPassword The password to create certificate
	 * @return JSONResponse<Http::STATUS_OK, array{message: string,action: string, pdf: array{url: string},filename: string,description: string}, array{}>|JSONResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array{message: string,action: string}, array{}>
	 *
	 * 200: OK
	 * 422: Validation page not accessible if unauthenticated
	 */
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

	/**
	 * Create PFX file using self-signed certificate
	 *
	 * @param string $signPassword The password that will be used to encrypt the certificate file
	 *
	 * @return JSONResponse<Http::STATUS_OK, array{}, array{}>|JSONResponse<Http::STATUS_UNAUTHORIZED, array{message: string}, array{}>
	 *
	 * 200: Settings saved
	 * 401: Failure to create PFX file
	 */
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

	/**
	 * Add files to account profile
	 *
	 * @param array{name: string, type: string} $files the list of files to add to profile
	 *
	 * @return JSONResponse<Http::STATUS_OK, array<empty>, array{}>|JSONResponse<Http::STATUS_UNAUTHORIZED, array{messages:array{file: ?string, type: ?string, message: string}}, array{}>
	 *
	 * 200: Certificate saved with success
	 * 401: No file provided or other problem with provided file
	 */
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

	/**
	 * Delete file from account
	 *
	 * @param int $nodeId the nodeId of file to be delete
	 *
	 * @return JSONResponse<Http::STATUS_OK, array{}, array{}>|JSONResponse<Http::STATUS_UNAUTHORIZED, array{messages: array{}}, array{}>
	 *
	 * 200: File deleted with success
	 * 401: Failure to delete file from account
	 */
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
	 * Who am I
	 *
	 * Validates API access data and returns the authenticated user's data.
	 *
	 * @return JSONResponse<Http::STATUS_OK, array{account: array{uuid: string, emailAddress: string, displayName: string},settings: array{}}, array{}>|JSONResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 404: Invalid user or password
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

	/**
	 * List account files of authenticated account
	 *
	 * @param array{approved?: string} $filter Filter params
	 * @param ?int $page the number of page to return
	 * @param ?int $length Total of elements to return
	 * @return JSONResponse<Http::STATUS_ACCEPTED, array{message: string}, array{}>|JSONResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 202: Certificate saved with success
	 * 400: No file provided or other problem with provided file
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function accountFileListToOwner(array $filter = [], ?int $page = null, ?int $length = null): JSONResponse {
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

	/**
	 * List account files that need to be approved
	 *
	 * @param array{approved: string} $filter Filter params
	 * @param ?int $page the number of page to return
	 * @param ?int $length Total of elements to return
	 * @return JSONResponse<Http::STATUS_OK, array{}, array{}>|JSONResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 404: Account not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function accountFileListToApproval(array $filter = [], ?int $page = null, ?int $length = null): JSONResponse {
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
	 * Update the account phone number
	 *
	 * @param ?string $phone the phone number to be defined. If null will remove the phone number
	 *
	 * @return JSONResponse<Http::STATUS_ACCEPTED, array{data: array{userId: string, phone: string, message: string}}, array{}>|JSONResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 202: Settings saved
	 * 404: Invalid data to update phone number
	 */
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

	/**
	 * Delete PFX file
	 *
	 * @return JSONResponse<Http::STATUS_ACCEPTED, array{message: string}, array{}>
	 *
	 * 202: Certificate deleted with success
	 */
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

	/**
	 * Upload PFX file
	 *
	 * @return JSONResponse<Http::STATUS_ACCEPTED, array{message: string}, array{}>|JSONResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 202: Certificate saved with success
	 * 400: No file provided or other problem with provided file
	 */
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

	/**
	 * Update PFX file
	 *
	 * Used to change the password of PFX file
	 *
	 * @param string $current Current password
	 * @param string $new New password
	 *
	 * @return JSONResponse<Http::STATUS_ACCEPTED, array{message: string}, array{}>|JSONResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 202: Certificate saved with success
	 * 400: No file provided or other problem with provided file
	 */
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

	/**
	 * Read content of PFX file
	 *
	 * @param string $password password of PFX file to decrypt the file and return his content
	 *
	 * @return JSONResponse<Http::STATUS_ACCEPTED, array{}, array{}>|JSONResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 202: Certificate saved with success
	 * 400: No file provided or other problem with provided file
	 */
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
