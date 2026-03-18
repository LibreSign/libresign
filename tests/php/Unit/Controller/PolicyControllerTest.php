<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\PolicyController;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PolicyControllerTest extends TestCase {
	private IRequest&MockObject $request;
	private IL10N&MockObject $l10n;
	private PolicyService&MockObject $policyService;
	private PolicyController $controller;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->policyService = $this->createMock(PolicyService::class);

		$this->controller = new PolicyController(
			$this->request,
			$this->l10n,
			$this->policyService,
		);
	}

	public function testEffectiveReturnsResolvedSignatureFlowPolicy(): void {
		$resolvedPolicy = (new ResolvedPolicy())
			->setPolicyKey('signature_flow')
			->setEffectiveValue('ordered_numeric')
			->setSourceScope('group')
			->setVisible(true)
			->setEditableByCurrentActor(false)
			->setAllowedValues(['ordered_numeric'])
			->setCanSaveAsUserDefault(false)
			->setCanUseAsRequestOverride(false)
			->setPreferenceWasCleared(false)
			->setBlockedBy('group');

		$this->policyService
			->expects($this->once())
			->method('resolveKnownPolicies')
			->willReturn([
				'signature_flow' => $resolvedPolicy,
			]);

		$response = $this->controller->effective();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([
			'policies' => [
				'signature_flow' => [
					'policyKey' => 'signature_flow',
					'effectiveValue' => 'ordered_numeric',
					'sourceScope' => 'group',
					'visible' => true,
					'editableByCurrentActor' => false,
					'allowedValues' => ['ordered_numeric'],
					'canSaveAsUserDefault' => false,
					'canUseAsRequestOverride' => false,
					'preferenceWasCleared' => false,
					'blockedBy' => 'group',
				],
			],
		], $response->getData());
	}

	public function testSetSystemReturnsSavedResolvedPolicy(): void {
		$resolvedPolicy = (new ResolvedPolicy())
			->setPolicyKey('signature_flow')
			->setEffectiveValue('ordered_numeric')
			->setSourceScope('system')
			->setVisible(true)
			->setEditableByCurrentActor(true)
			->setAllowedValues(['none', 'parallel', 'ordered_numeric'])
			->setCanSaveAsUserDefault(true)
			->setCanUseAsRequestOverride(false)
			->setPreferenceWasCleared(false)
			->setBlockedBy(null);

		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Settings saved')
			->willReturn('Settings saved');

		$this->policyService
			->expects($this->once())
			->method('saveSystem')
			->with('signature_flow', 'ordered_numeric')
			->willReturn($resolvedPolicy);

		$response = $this->controller->setSystem('signature_flow', 'ordered_numeric');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([
			'message' => 'Settings saved',
			'policy' => [
				'policyKey' => 'signature_flow',
				'effectiveValue' => 'ordered_numeric',
				'sourceScope' => 'system',
				'visible' => true,
				'editableByCurrentActor' => true,
				'allowedValues' => ['none', 'parallel', 'ordered_numeric'],
				'canSaveAsUserDefault' => true,
				'canUseAsRequestOverride' => false,
				'preferenceWasCleared' => false,
				'blockedBy' => null,
			],
		], $response->getData());
	}

	public function testSetSystemReturnsBadRequestWhenPolicyValueIsInvalid(): void {
		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Invalid value for signature_flow')
			->willReturn('Invalid value for signature_flow');

		$this->policyService
			->expects($this->once())
			->method('saveSystem')
			->with('signature_flow', 'banana')
			->willThrowException(new \InvalidArgumentException('Invalid value for signature_flow'));

		$response = $this->controller->setSystem('signature_flow', 'banana');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame([
			'error' => 'Invalid value for signature_flow',
		], $response->getData());
	}

	public function testSetUserPreferenceReturnsSavedResolvedPolicy(): void {
		$resolvedPolicy = (new ResolvedPolicy())
			->setPolicyKey('signature_flow')
			->setEffectiveValue('parallel')
			->setSourceScope('user')
			->setVisible(true)
			->setEditableByCurrentActor(true)
			->setAllowedValues(['parallel', 'ordered_numeric'])
			->setCanSaveAsUserDefault(true)
			->setCanUseAsRequestOverride(true)
			->setPreferenceWasCleared(false)
			->setBlockedBy(null);

		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Settings saved')
			->willReturn('Settings saved');

		$this->policyService
			->expects($this->once())
			->method('saveUserPreference')
			->with('signature_flow', 'parallel')
			->willReturn($resolvedPolicy);

		$response = $this->controller->setUserPreference('signature_flow', 'parallel');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('user', $response->getData()['policy']['sourceScope']);
	}
}
