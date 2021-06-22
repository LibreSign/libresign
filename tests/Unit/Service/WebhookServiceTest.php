<?php

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Service\WebhookService;
use OCP\Http\Client\IClientService;

/**
 * @internal
 */
final class WebhookServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	/** @var IClientService */
	private $clientService;

	public function setUp(): void {
		$this->clientService = $this->createMock(IClientService::class);
		$this->service = new WebhookService(
			$this->clientService
		);
	}

	public function testNotifyCallback() {
		$file = $this->createMock(\OCP\Files\File::class);
		$actual = $this->service->notifyCallback('https://test.coop', 'uuid', $file);
		$this->assertInstanceOf('\OCP\Http\Client\IResponse', $actual);
	}
}
