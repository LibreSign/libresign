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
	 * @OA\Post(
	 *     path="/api/0.1/webhook/register",
	 *     @OA\RequestBody(
	 *         description="Register file to sign and all users to sign the file",
	 *         required=true
	 *     ),
	 *     @OA\Response(response="200", description="An example resource")
	 * )
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
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
			foreach ($return['users'] as $key => $user) {
				$this->mail->notifyUnsignedUser($user);
			}
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
}
