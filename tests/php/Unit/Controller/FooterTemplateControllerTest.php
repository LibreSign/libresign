<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\FooterTemplateController;
use OCA\Libresign\Service\FooterService;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\IRequest;

final class FooterTemplateControllerTest extends TestCase {
	public function testInvalidTemplateCannotReplaceGlobalPolicy(): void {
		$service = $this->createMock(FooterService::class);
		$service->method('isPreviewAllowed')->willReturn(true);
		$service->expects($this->once())->method('renderPreviewPdf')
			->willThrowException(new \RuntimeException('Template rejected'));
		$service->expects($this->never())->method('saveTemplate');

		$controller = new FooterTemplateController($this->createMock(IRequest::class), $service);
		$response = $controller->saveFooterTemplate('invalid');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testResetPreviewsDefaultBeforeUpdatingGlobalPolicy(): void {
		$service = $this->createMock(FooterService::class);
		$service->method('isPreviewAllowed')->willReturn(true);
		$service->method('getDefaultTemplate')->willReturn('Safe default footer');
		$service->expects($this->once())->method('renderPreviewPdf')
			->with('Safe default footer', 595, 50)
			->willReturn('%PDF-1.7');
		$service->expects($this->once())->method('saveTemplate')
			->with('', 595, 50);

		$controller = new FooterTemplateController($this->createMock(IRequest::class), $service);
		$response = $controller->saveFooterTemplate('');

		$this->assertInstanceOf(DataDownloadResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}
}
