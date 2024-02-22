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
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\CORS;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IPreview;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class AccountController extends ApiController implements ISignatureUuid {
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
					'url' => $this->urlGenerator->linkToRoute('libresign.page.getPdfAccountFile', ['uuid' => $uuid])
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
			$data = [
				'user' => [
					'identify' => $this->userSession->getUser()->getUID(),
					'name' => $this->userSession->getUser()->getDisplayName(),
				],
				'signPassword' => $signPassword,
				'userId' => $this->userSession->getUser()->getUID()
			];
			$this->accountService->validateCertificateData($data);
			$this->pkcs12Handler->generateCertificate(
				$data['user'],
				$data['signPassword'],
				$this->userSession->getUser()->getDisplayName()
			);

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
	#[PublicPage]
	public function createSignatureElement(array $elements): JSONResponse {
		try {
			$this->validateHelper->validateVisibleElements($elements, $this->validateHelper::TYPE_VISIBLE_ELEMENT_USER);
			$this->accountService->saveVisibleElements(
				elements: $elements,
				sessionId: $this->sessionService->getSessionId(),
				user: $this->userSession->getUser(),
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
				'message' => $this->l10n->n(
					'Element created with success',
					'Elements created with success',
					count($elements)
				),
				'elements' =>
					(
						$this->userSession->getUser() instanceof IUser
						? $this->signerElementsService->getUserElements($this->userSession->getUser()->getUID())
						: $this->signerElementsService->getElementsFromSessionAsArray()
					),
			],
			Http::STATUS_OK
		);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getSignatureElements(): JSONResponse {
		$userId = $this->userSession->getUser()?->getUID();
		try {
			return new JSONResponse(
				[
					'elements' =>
						(
							$userId
							? $this->signerElementsService->getUserElements($userId)
							: $this->signerElementsService->getElementsFromSessionAsArray()
						)
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

	#[NoAdminRequired]
	#[PublicPage]
	#[NoCSRFRequired]
	public function getSignatureElementPreview(int $fileId) {
		try {
			$node = $this->accountService->getFileByNodeIdAndSessionId(
				$fileId,
				$this->sessionService->getSessionId()
			);
		} catch (DoesNotExistException $th) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
		$preview = $this->preview->getPreview(
			file: $node,
			width: SignerElementsService::ELEMENT_SIGN_WIDTH,
			height: SignerElementsService::ELEMENT_SIGN_HEIGHT,
		);
		$response = new FileDisplayResponse($preview, Http::STATUS_OK, [
			'Content-Type' => $preview->getMimeType(),
		]);
		return $response;
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getSignatureElement(int $elementId): JSONResponse {
		$userId = $this->userSession->getUser()->getUID();
		try {
			return new JSONResponse(
				$this->signerElementsService->getUserElementByElementId($userId, $elementId),
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

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function patchSignatureElement($elementId, string $type = '', array $file = []): JSONResponse {
		try {
			$element['elementId'] = $elementId;
			if ($type) {
				$element['type'] = $type;
			}
			if ($file) {
				$element['file'] = $file;
			}
			$this->validateHelper->validateVisibleElement($element, $this->validateHelper::TYPE_VISIBLE_ELEMENT_USER);
			$this->accountService->saveVisibleElement($element, $this->sessionService->getSessionId(), $this->userSession->getUser());
			return new JSONResponse(
				[
					'message' => $this->l10n->t('Element updated with success')
				],
				Http::STATUS_OK
			);
		} catch (\Throwable $th) {
			return new JSONResponse(
				[
					'message' => $th->getMessage()
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function deleteSignatureElement(int $elementId): JSONResponse {
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
}
