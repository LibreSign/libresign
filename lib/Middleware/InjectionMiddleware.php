<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Middleware;

use OC\AppFramework\Http as AppFrameworkHttp;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Controller\AEnvironmentAwareController;
use OCA\Libresign\Controller\AEnvironmentPageAwareController;
use OCA\Libresign\Controller\ISignatureUuid;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Middleware\Attribute\CanSignRequestUuid;
use OCA\Libresign\Middleware\Attribute\PrivateValidation;
use OCA\Libresign\Middleware\Attribute\RequireManager;
use OCA\Libresign\Middleware\Attribute\RequireSetupOk;
use OCA\Libresign\Middleware\Attribute\RequireSigner;
use OCA\Libresign\Middleware\Attribute\RequireSignRequestUuid;
use OCA\Libresign\Service\SignFileService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Util;

class InjectionMiddleware extends Middleware {
	public function __construct(
		private IRequest $request,
		private ISession $session,
		private IUserSession $userSession,
		private ValidateHelper $validateHelper,
		private SignRequestMapper $signRequestMapper,
		private CertificateEngineFactory $certificateEngineFactory,
		private FileMapper $fileMapper,
		private IInitialState $initialState,
		private SignFileService $signFileService,
		private IL10N $l10n,
		private IAppConfig $appConfig,
		private IURLGenerator $urlGenerator,
		protected ?string $userId,
	) {
		$this->request = $request;
	}

	/**
	 * @param Controller $controller
	 * @param string $methodName
	 * @throws \Exception
	 */
	#[\Override]
	public function beforeController(Controller $controller, string $methodName) {
		if ($controller instanceof AEnvironmentAwareController) {
			$apiVersion = $this->request->getParam('apiVersion');
			/** @var AEnvironmentAwareController $controller */
			$controller->setAPIVersion((int)substr((string)$apiVersion, 1));
		}

		$reflectionMethod = new \ReflectionMethod($controller, $methodName);

		if (!empty($reflectionMethod->getAttributes(RequireManager::class))) {
			$this->getLoggedIn();
		}

		if (!empty($reflectionMethod->getAttributes(RequireSigner::class))) {
			$this->requireSigner();
		}

		$this->requireSetupOk($reflectionMethod);

		$this->privateValidation($reflectionMethod);

		$this->handleUuid($controller, $reflectionMethod);
	}

	private function privateValidation(\ReflectionMethod $reflectionMethod): void {
		if (empty($reflectionMethod->getAttributes(PrivateValidation::class))) {
			return;
		}
		if ($this->userSession->isLoggedIn()) {
			return;
		}
		$isValidationUrlPrivate = (bool)$this->appConfig->getValueBool(Application::APP_ID, 'make_validation_url_private', false);
		if (!$isValidationUrlPrivate) {
			return;
		}
		$redirectUrl = $this->request->getRawPathInfo();

		throw new LibresignException(json_encode([
			'action' => JSActions::ACTION_REDIRECT,
			'errors' => [$this->l10n->t('You are not logged in. Please log in.')],
			'redirect' => $this->urlGenerator->linkToRoute('core.login.showLoginForm', [
				'redirect_url' => $redirectUrl,
			]),
		]), Http::STATUS_UNAUTHORIZED);
	}

	private function handleUuid(Controller $controller, \ReflectionMethod $reflectionMethod): void {
		if (!$controller instanceof ISignatureUuid) {
			return;
		}

		$uuid = $this->getUuidFromRequest();

		if (!empty($reflectionMethod->getAttributes(CanSignRequestUuid::class))) {
			/** @var AEnvironmentPageAwareController $controller */
			$controller->validateRenewSigner(
				uuid: $uuid,
			);
			/** @var AEnvironmentPageAwareController $controller */
			$controller->loadNextcloudFileFromSignRequestUuid(
				uuid: $uuid,
			);
		}

		if (!empty($attribute = $reflectionMethod->getAttributes(RequireSignRequestUuid::class))) {
			$attribute = $reflectionMethod->getAttributes(RequireSignRequestUuid::class);
			$attribute = current($attribute);
			/** @var RequireSignRequestUuid $intance */
			$intance = $attribute->newInstance();
			$user = $this->userSession->getUser();
			if (!($intance->skipIfAuthenticated() && $user instanceof IUser)) {
				/** @var AEnvironmentPageAwareController $controller */
				$controller->validateSignRequestUuid(
					uuid: $uuid,
				);
				/** @var AEnvironmentPageAwareController $controller */
				$controller->loadNextcloudFileFromSignRequestUuid(
					uuid: $uuid,
				);
			}
		}
	}

	private function getUuidFromRequest(): ?string {
		return $this->request->getParam('uuid', $this->request->getHeader('libresign-sign-request-uuid'));
	}

	private function getLoggedIn(): void {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			throw new \Exception($this->l10n->t('You are not allowed to request signing'), Http::STATUS_UNPROCESSABLE_ENTITY);
		}
		$this->validateHelper->canRequestSign($user);
	}

	private function requireSigner(): void {
		$uuid = $this->getUuidFromRequest();

		try {
			$user = $this->userSession->getUser();
			$this->validateHelper->validateSigner($uuid, $user);
		} catch (LibresignException $e) {
			throw new LibresignException($e->getMessage());
		}
	}

	private function requireSetupOk(\ReflectionMethod $reflectionMethod): void {
		$attribute = $reflectionMethod->getAttributes(RequireSetupOk::class);
		if (empty($attribute)) {
			return;
		}
		$attribute = current($attribute);
		if (!$this->certificateEngineFactory->getEngine()->isSetupOk()) {
			/** @var RequireSetupOk $requirement */
			$requireSetupOk = $attribute->newInstance();
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_INCOMPLETE_SETUP,
				'template' => $requireSetupOk->getTemplate(),
			]));
		}
	}

	/**
	 * @param Controller $controller
	 * @param string $methodName
	 * @param \Exception $exception
	 * @throws \Exception
	 * @return Response
	 */
	#[\Override]
	public function afterException($controller, $methodName, \Exception $exception): Response {
		if (str_contains($this->request->getHeader('Accept'), 'html')) {
			$template = 'external';
			if ($this->isJson($exception->getMessage())) {
				$settings = json_decode($exception->getMessage(), true);
				if (isset($settings['action']) && $settings['action'] === JSActions::ACTION_REDIRECT && isset($settings['redirect'])) {
					if (isset($settings['errors'])) {
						$this->session->set('loginMessages', [
							[], $settings['errors'],
						]);
					}
					return new RedirectResponse($settings['redirect']);
				}
				foreach ($settings as $key => $value) {
					if ($key === 'template') {
						$template = $value;
						continue;
					}
					$this->initialState->provideInitialState($key, $value);
				}
			} else {
				$this->initialState->provideInitialState('error', ['message' => $exception->getMessage()]);
			}

			Util::addScript(Application::APP_ID, 'libresign-' . $template);
			$response = new TemplateResponse(
				appName: Application::APP_ID,
				templateName: $template,
				renderAs: $this->getRenderAsFromTemplate($template),
				status: $this->getStatusCodeFromException($exception)
			);

			$policy = new ContentSecurityPolicy();
			$policy->allowEvalScript(true);
			$policy->addAllowedFrameDomain('\'self\'');
			$response->setContentSecurityPolicy($policy);
			return $response;
		}
		if ($exception instanceof LibresignException) {
			if ($this->isJson($exception->getMessage())) {
				$body = json_decode($exception->getMessage());
			} else {
				$body = [
					'message' => $exception->getMessage(),
				];
			}
			if ($controller instanceof \OCP\AppFramework\OCSController) {
				$format = $this->request->getParam('format');

				// if none is given try the first Accept header
				if ($format === null) {
					$headers = $this->request->getHeader('Accept');
					$format = $controller->getResponderByHTTPHeader($headers, 'json');
				}

				$response = new DataResponse(
					data: $body,
					statusCode: $this->getStatusCodeFromException($exception)
				);
				if ($format !== null) {
					$response = $controller->buildResponse($response, $format);
				} else {
					$response = $controller->buildResponse($response);
				}
			} else {
				$response = new JSONResponse(
					data: $body,
					statusCode: $this->getStatusCodeFromException($exception)
				);
			}
			return $response;
		}

		throw $exception;
	}

	private function getRenderAsFromTemplate(string $template): string {
		if ($template === 'external') {
			return TemplateResponse::RENDER_AS_BASE;
		}
		return TemplateResponse::RENDER_AS_USER;
	}

	private function getStatusCodeFromException(\Exception $exception): int {
		if ($exception->getCode() === 0) {
			return AppFrameworkHttp::STATUS_UNPROCESSABLE_ENTITY;
		}
		return (int)$exception->getCode();
	}

	protected function isJson(string $string): bool {
		json_decode($string);
		return json_last_error() === JSON_ERROR_NONE;
	}
}
