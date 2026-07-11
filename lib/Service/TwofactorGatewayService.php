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
	private const FACTORY_SERVICE_ID = 'OCA\\TwoFactorGateway\\Provider\\Gateway\\Factory';

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
		$this->resolveGateway($gatewayName);
	}

	public function isGatewayComplete(string $gatewayName): bool {
		if (!$this->isEnabled()) {
			return false;
		}

		try {
			$gateway = $this->resolveGateway($gatewayName);
		} catch (\Exception $exception) {
			$this->logger->warning('Unable to load twofactor gateway provider.', [
				'gateway' => $gatewayName,
				'exception' => $exception,
			]);
			return false;
		}

		if (!is_callable([$gateway, 'isComplete'])) {
			$this->logger->warning('Twofactor gateway provider does not expose isComplete().', [
				'gateway' => $gatewayName,
			]);
			return false;
		}

		try {
			$isComplete = call_user_func([$gateway, 'isComplete']);
		} catch (\Exception $exception) {
			$this->logger->warning('Twofactor gateway provider failed during completeness check.', [
				'gateway' => $gatewayName,
				'exception' => $exception,
			]);
			return false;
		}

		if (!is_bool($isComplete)) {
			$this->logger->warning('Twofactor gateway provider returned an invalid completeness flag.', [
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
		$gateway = $this->resolveGateway($gatewayName);
		if (!is_callable([$gateway, 'send'])) {
			throw new \UnexpectedValueException(sprintf(
				'Twofactor gateway provider "%s" does not expose send().',
				$gatewayName,
			));
		}

		call_user_func([$gateway, 'send'], $identifier, $message);
	}

	/**
	 * @throws LibresignException
	 */
	private function resolveGateway(string $gatewayName): object {
		if (!$this->isEnabled()) {
			throw new LibresignException('App Two-Factor Gateway is not enabled.');
		}

		try {
			$factory = $this->container->get(self::FACTORY_SERVICE_ID);
		} catch (NotFoundExceptionInterface $exception) {
			throw new LibresignException('App Two-Factor Gateway is not installed.', 0, $exception);
		}

		if (!is_object($factory)) {
			throw new \UnexpectedValueException('Twofactor gateway factory is not an object.');
		}

		if (!is_callable([$factory, 'get'])) {
			throw new \UnexpectedValueException('Twofactor gateway factory does not expose get().');
		}

		$gateway = call_user_func([$factory, 'get'], $gatewayName);
		if (!is_object($gateway)) {
			throw new \UnexpectedValueException(sprintf(
				'Twofactor gateway "%s" did not resolve to an object.',
				$gatewayName,
			));
		}

		return $gateway;
	}
}