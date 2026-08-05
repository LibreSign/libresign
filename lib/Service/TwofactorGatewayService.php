<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Exception\LibresignException;
use OCP\App\IAppManager;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

final class TwofactorGatewayService {
	private const APP_ID = 'twofactor_gateway';
	private const INTEGRATION_SERVICE_ID = 'OCA\\TwoFactorGateway\\Service\\GatewayDirectIntegrationService';

	public function __construct(
		private ContainerInterface $container,
		private IAppManager $appManager,
		private LoggerInterface $logger,
	) {
	}

	public function isEnabled(): bool {
		return $this->appManager->isEnabledForAnyone(self::APP_ID);
	}

	/**
	 * @throws LibresignException
	 */
	public function ensureAvailable(string $gatewayName): void {
		$this->callIntegrationMethod('ensureAvailable', [$gatewayName]);
	}

	public function isGatewayComplete(string $gatewayName): bool {
		if (!$this->isEnabled()) {
			return false;
		}

		try {
			$isComplete = $this->callIntegrationMethod('isGatewayComplete', [$gatewayName]);
		} catch (\Exception $exception) {
			$this->logger->warning('Unable to determine twofactor gateway completeness.', [
				'gateway' => $gatewayName,
				'exception' => $exception,
			]);
			return false;
		}

		if (!is_bool($isComplete)) {
			$this->logger->warning('Twofactor gateway integration service returned an invalid completeness flag.', [
				'gateway' => $gatewayName,
				'returnedType' => get_debug_type($isComplete),
			]);
			return false;
		}

		return $isComplete;
	}

	/**
	 * @throws LibresignException
	 */
	public function send(string $gatewayName, string $identifier, string $message): void {
		$this->callIntegrationMethod('send', [$gatewayName, $identifier, $message]);
	}

	/**
	 * @throws LibresignException
	 */
	private function callIntegrationMethod(string $method, array $arguments = []): mixed {
		$integrationService = $this->resolveIntegrationService();
		if (!is_callable([$integrationService, $method])) {
			throw new \UnexpectedValueException(sprintf(
				'Twofactor gateway integration service does not expose %s().',
				$method,
			));
		}

		return call_user_func([$integrationService, $method], ...$arguments);
	}

	/**
	 * @throws LibresignException
	 */
	private function resolveIntegrationService(): object {
		if (!$this->isEnabled()) {
			throw new LibresignException('App Two-Factor Gateway is not enabled.');
		}

		try {
			$integrationService = $this->container->get(self::INTEGRATION_SERVICE_ID);
		} catch (NotFoundExceptionInterface $exception) {
			throw new LibresignException('App Two-Factor Gateway is not installed.', 0, $exception);
		}

		if (!is_object($integrationService)) {
			throw new \UnexpectedValueException('Twofactor gateway integration service is not an object.');
		}

		return $integrationService;
	}
}
