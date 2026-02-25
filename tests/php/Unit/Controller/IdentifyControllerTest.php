<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Collaboration\Collaborators\AccountPhonePlugin;
use OCA\Libresign\Collaboration\Collaborators\ContactPhonePlugin;
use OCA\Libresign\Collaboration\Collaborators\ManualPhonePlugin;
use OCA\Libresign\Collaboration\Collaborators\SignerPlugin;
use OCA\Libresign\Controller\IdentifyController;
use OCA\Libresign\Service\Identify\ResultEnricher;
use OCA\Libresign\Service\Identify\ResultFilter;
use OCA\Libresign\Service\Identify\ResultFormatter;
use OCA\Libresign\Service\Identify\SearchNormalizer;
use OCA\Libresign\Service\Identify\ShareTypeResolver;
use OCA\Libresign\Service\Identify\SignerSearchContext;
use OCP\Collaboration\Collaborators\ISearch;
use OCP\IRequest;
use OCP\Share\IShare;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FakeCollaboratorSearch implements ISearch {
	public array $pluginList = [];
	public array $lastSearchCall = [];

	public function search($search, array $shareTypes, $lookup, $limit, $offset): array {
		$this->lastSearchCall = [
			'search' => $search,
			'shareTypes' => $shareTypes,
			'lookup' => $lookup,
			'limit' => $limit,
			'offset' => $offset,
		];
		return [['exact' => [], 'wide' => []], false];
	}

	public function registerPlugin(array $pluginInfo): void {
	}
}

class IdentifyControllerTest extends TestCase {
	private IRequest&MockObject $request;
	private FakeCollaboratorSearch $collaboratorSearch;
	private ShareTypeResolver&MockObject $shareTypeResolver;
	private SearchNormalizer&MockObject $searchNormalizer;
	private SignerSearchContext&MockObject $signerSearchContext;
	private ResultFilter&MockObject $resultFilter;
	private ResultFormatter&MockObject $resultFormatter;
	private ResultEnricher&MockObject $resultEnricher;
	private IdentifyController $controller;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->collaboratorSearch = new FakeCollaboratorSearch();
		$this->shareTypeResolver = $this->createMock(ShareTypeResolver::class);
		$this->searchNormalizer = $this->createMock(SearchNormalizer::class);
		$this->signerSearchContext = $this->createMock(SignerSearchContext::class);
		$this->resultFilter = $this->createMock(ResultFilter::class);
		$this->resultFormatter = $this->createMock(ResultFormatter::class);
		$this->resultEnricher = $this->createMock(ResultEnricher::class);

		$this->controller = new IdentifyController(
			$this->request,
			$this->collaboratorSearch,
			$this->shareTypeResolver,
			$this->searchNormalizer,
			$this->signerSearchContext,
			$this->resultFilter,
			$this->resultFormatter,
			$this->resultEnricher,
		);

		$this->searchNormalizer
			->method('normalize')
			->willReturnCallback(fn (string $search): string => $search);

		$this->resultFilter
			->method('unify')
			->willReturnCallback(fn (array $value): array => $value);
		$this->resultFilter
			->method('excludeEmpty')
			->willReturnCallback(fn (array $value): array => $value);
		$this->resultFilter
			->method('excludeNotAllowed')
			->willReturnCallback(fn (array $value): array => $value);

		$this->resultFormatter
			->method('formatForNcSelect')
			->willReturn([]);
		$this->resultFormatter
			->method('replaceShareTypeWithMethod')
			->willReturnCallback(fn (array $value): array => $value);

		$this->resultEnricher
			->method('addHerselfAccount')
			->willReturnCallback(fn (array $value): array => $value);
		$this->resultEnricher
			->method('addHerselfEmail')
			->willReturnCallback(fn (array $value): array => $value);
		$this->resultEnricher
			->method('addEmailNotificationPreference')
			->willReturnCallback(fn (array $value): array => $value);
	}

	public function testSearchWithWhatsappMethodDoesNotRequestAccountOrEmailShareTypes(): void {
		$expectedShareTypes = [
			SignerPlugin::TYPE_SIGNER,
			AccountPhonePlugin::TYPE_SIGNER_ACCOUNT_PHONE,
			ContactPhonePlugin::TYPE_SIGNER_CONTACT_PHONE,
			ManualPhonePlugin::TYPE_SIGNER_MANUAL_PHONE,
		];

		$this->shareTypeResolver
			->expects($this->once())
			->method('resolve')
			->with('whatsapp')
			->willReturn($expectedShareTypes);

		$response = $this->controller->search('a', 'whatsapp');
		$shareTypes = $this->collaboratorSearch->lastSearchCall['shareTypes'];

		$this->assertSame($expectedShareTypes, $shareTypes);
		$this->assertSame([], $response->getData());
	}

	public function testSearchWithEmailMethodRequestsEmailShareTypeAndNotUserShareType(): void {
		$expectedShareTypes = [
			SignerPlugin::TYPE_SIGNER,
			IShare::TYPE_EMAIL,
		];

		$this->shareTypeResolver
			->expects($this->once())
			->method('resolve')
			->with('email')
			->willReturn($expectedShareTypes);

		$response = $this->controller->search('a', 'email');
		$shareTypes = $this->collaboratorSearch->lastSearchCall['shareTypes'];

		$this->assertSame($expectedShareTypes, $shareTypes);
		$this->assertSame([], $response->getData());
	}
}
