<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\MailService;
use OCA\Libresign\Service\WebhookService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

class WebhookController extends ApiController {
	/** @var IUserSession */
	private $userSession;
	/** @var IL10N */
	private $l10n;
	/** @var WebhookService */
	private $webhook;
	/** @var MailService */
	private $mail;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IL10N $l10n,
		WebhookService $webhook,
		MailService $mail
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->userSession = $userSession;
		$this->l10n = $l10n;
		$this->webhook = $webhook;
		$this->mail = $mail;
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
			$this->webhook->validate($data);
			$return = $this->webhook->save($data);
			unset($return['users']);
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
			$this->webhook->validateUserManager($data);
			$this->webhook->validateFileUuid($data);
			$this->webhook->validateUsers($data);
			$return = $this->webhook->save($data);
			unset($return['users']);
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
			$this->webhook->validateUserManager($data);
			$this->webhook->validateFileUuid($data);
			$this->webhook->validateUsers($data);
			$this->webhook->canDeleteSignRequest($data);
			$deletedUsers = $this->webhook->deleteSignRequest($data);
			foreach ($deletedUsers as $user) {
				$this->mail->notifyUnsignedUser($user);
			}
		} catch (\Throwable $th) {
			$message = $th->getMessage();
			if (preg_match('/Did expect one result but found none when executing/', $message)) {
				$message = $this->l10n->t('UUID not found');
			}
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
