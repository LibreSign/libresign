<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service\Worker;

use OCA\Libresign\Service\Worker\LocalWorkerCoordinator;
use OCA\Libresign\Service\Worker\WorkerActivationService;
use OCP\App\IAppManager;
use OCP\Server;
use PHPUnit\Framework\TestCase;

class WorkerActivationServiceTest extends TestCase {
	public function testNextcloudResolvesRenamedWorkerServices(): void {
		Server::get(IAppManager::class)->loadApp('libresign');

		$activation = Server::get(WorkerActivationService::class);
		$coordinator = Server::get(LocalWorkerCoordinator::class);

		$this->assertInstanceOf(WorkerActivationService::class, $activation);
		$this->assertInstanceOf(LocalWorkerCoordinator::class, $coordinator);
		$this->assertTrue($coordinator->ensureCapacity(0));
	}
}
