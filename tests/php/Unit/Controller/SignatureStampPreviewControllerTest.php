<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\SignatureStampPreviewController;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\SignatureText\SignatureTextPolicy;
use OCA\Libresign\Service\SignatureStampPreview\SignatureStampPreviewNativeService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SignatureStampPreviewControllerTest extends TestCase {
	private SignatureStampPreviewController $controller;
	private SignatureStampPreviewNativeService&MockObject $signatureStampPreviewNativeService;
	private PolicyService&MockObject $policyService;

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->signatureStampPreviewNativeService = $this->createMock(SignatureStampPreviewNativeService::class);
		$this->policyService = $this->createMock(PolicyService::class);

		$this->controller = new SignatureStampPreviewController(
			$request,
			$this->signatureStampPreviewNativeService,
			$this->policyService,
		);
	}

	public function testPreviewPdfReturnsForbiddenWhenPolicyIsNotEditable(): void {
		$this->policyService
			->expects($this->once())
			->method('resolve')
			->with(SignatureTextPolicy::KEY)
			->willReturn(
				(new ResolvedPolicy())
					->setVisible(true)
					->setEditableByCurrentActor(false)
			);

		$response = $this->controller->previewPdf(template: 'Denied');

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testPreviewPdfReturnsPdfWhenPolicyIsEditable(): void {
		$this->policyService
			->expects($this->once())
			->method('resolve')
			->with(SignatureTextPolicy::KEY)
			->willReturn(
				(new ResolvedPolicy())
					->setVisible(true)
					->setEditableByCurrentActor(true)
			);

		$this->signatureStampPreviewNativeService
			->expects($this->once())
			->method('renderPreviewPdf')
			->willReturn('%PDF-1.4');

		$response = $this->controller->previewPdf(template: 'Allowed');

		$this->assertInstanceOf(DataDownloadResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

}
