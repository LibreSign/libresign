<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Controller;

use OCA\Libresign\Controller\ExternalSigningIntegrationsController;
use OCA\Libresign\Service\ExperimentalFeaturesService;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Signing\External\IntegrationScopeAccess;
use OCA\Libresign\Service\Signing\ExternalIntegrationAdministration;
use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscDriver;
use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscProviderProbe;
use OCA\Libresign\Service\Signing\Provider\ExternalSigningDriverCatalog;
use OCP\AppFramework\Http;
use OCP\Group\ISubAdmin;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Server;

/** @group DB */
final class ExternalSigningIntegrationsControllerTest extends \OCA\Libresign\Tests\Integration\TestCase {
	private ExternalSigningIntegrationsController $controller;
	private IDBConnection $db;
	/** @var list<string> */
	private array $created = [];

	public function setUp(): void {
		parent::setUp();
		$this->db = Server::get(IDBConnection::class);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('system-admin-fixture');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->with('system-admin-fixture')->willReturn(true);
		$features = $this->createMock(ExperimentalFeaturesService::class);
		$features->method('isEnabled')->willReturn(true);

		$this->controller = new ExternalSigningIntegrationsController(
			$this->createMock(IRequest::class),
			$session,
			$groups,
			$features,
			new ExternalSigningDriverCatalog($features, new CscDriver($this->createMock(IL10N::class))),
			Server::get(ExternalIntegrationAdministration::class),
			new IntegrationScopeAccess(
				Server::get(PolicyService::class), $groups,
				Server::get(IUserManager::class), $session,
				Server::get(ISubAdmin::class),
			),
			new CscProviderProbe($this->createMock(IClientService::class), $this->createMock(IConfig::class)),
			Server::get(\Psr\Log\LoggerInterface::class),
		);
	}

	public function tearDown(): void {
		try {
			foreach ($this->created as $id) {
				$qb = $this->db->getQueryBuilder();
				$qb->delete('libresign_signing_integration')
					->where($qb->expr()->eq('public_id', $qb->createNamedParameter($id)))
					->executeStatement();
			}
		} finally {
			parent::tearDown();
		}
	}

	/**
	 * A subadmin delegated Client A must not list or mutate Client B connections,
	 * even when they know the opaque ID. The database access remains scoped.
	 */
	public function testDelegatedGroupAdministratorCannotAccessOtherClientConnections(): void {
		$first = $this->create('Client A', 'a', 'sensitive-a');
		$second = $this->create('Client B', 'b', 'sensitive-b');
		$administration = Server::get(ExternalIntegrationAdministration::class);
		$firstRecord = $administration->get($first['id']);
		$secondRecord = $administration->get($second['id']);
		// Existing system-scoped fixtures are not mutable from a delegated group.
		// Create genuine group-scoped connections for the two clients.
		// Use real driver metadata through the admin OCS boundary. Forging
		// incomplete test fields would bypass the actual validation contract.
		$createA = $this->controller->create(
			'csc-v1', 'Scoped Client A', ['baseUrl' => 'https://example.org'], [], 'group', 'client-a',
		);
		$createB = $this->controller->create(
			'csc-v1', 'Scoped Client B', ['baseUrl' => 'https://example.org'], [], 'group', 'client-b',
		);
		self::assertSame(Http::STATUS_CREATED, $createA->getStatus());
		self::assertSame(Http::STATUS_CREATED, $createB->getStatus());
		$createdA = $administration->get($createA->getData()['id']);
		$createdB = $administration->get($createB->getData()['id']);
		$this->created[] = $createdA->id;
		$this->created[] = $createdB->id;

		$actor = $this->createMock(IUser::class);
		$actor->method('getUID')->willReturn('subadmin-a');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($actor);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->willReturn(false);
		$subAdmin = $this->createMock(ISubAdmin::class);
		$subAdmin->method('isSubAdmin')->willReturn(true);
		$group = $this->createMock(IGroup::class);
		$group->method('getGID')->willReturn('client-a');
		$subAdmin->method('getSubAdminsGroups')->willReturn([$group]);
		$policies = $this->createMock(PolicyService::class);
		$policies->method('getGroupPolicy')->willReturnCallback(
			fn (string $key, string $scope): ?PolicyLayer => $scope === 'client-a'
				? (new PolicyLayer())->setValue(true)->setVisibleToChild(true)->setAllowChildOverride(true)
				: null,
		);
		$features = $this->createMock(ExperimentalFeaturesService::class);
		$features->method('isEnabled')->willReturn(true);
		$delegated = new ExternalSigningIntegrationsController(
			$this->createMock(IRequest::class), $session, $groups, $features,
			new ExternalSigningDriverCatalog($features, new CscDriver($this->createMock(IL10N::class))),
			$administration,
			new IntegrationScopeAccess(
				$policies, $groups, Server::get(IUserManager::class), $session, $subAdmin,
			),
			new CscProviderProbe($this->createMock(IClientService::class), $this->createMock(IConfig::class)),
			Server::get(\Psr\Log\LoggerInterface::class),
		);

		self::assertSame(Http::STATUS_OK, $delegated->index(0, 50, 'group', 'client-a')->getStatus());
		$visible = $delegated->index(0, 50, 'group', 'client-a')->getData()['items'];
		$visibleIds = array_column($visible, 'id');
		self::assertContains($createdA->id, $visibleIds);
		self::assertNotContains($createdB->id, $visibleIds);
		self::assertSame(Http::STATUS_FORBIDDEN, $delegated->index(0, 50, 'group', 'client-b')->getStatus());
		self::assertSame(Http::STATUS_NOT_FOUND, $delegated->show($createdB->id)->getStatus());
		self::assertSame(Http::STATUS_NOT_FOUND, $delegated->update($createdB->id, 1, 'hijacked')->getStatus());
		self::assertSame(Http::STATUS_NOT_FOUND, $delegated->destroy($createdB->id, 1)->getStatus());
		self::assertSame(Http::STATUS_NOT_FOUND, $delegated->show($firstRecord->id)->getStatus());
		self::assertSame(Http::STATUS_NOT_FOUND, $delegated->show($secondRecord->id)->getStatus());
		self::assertSame(Http::STATUS_OK, $delegated->show($createdA->id)->getStatus());
	}

	/**
	 * Exercise the HTTP-shaped controller with the actual catalog, DB,
	 * encryption layer and optimistic revisions, not just service mocks.
	 */
	public function testMultiInstanceCrudSecretRedactionAndConcurrentUpdate(): void {
		$first = $this->create('Primary', 'one', 'synthetic-sensitive-token');
		$second = $this->create('Secondary', 'two');

		self::assertNotSame($first['id'], $second['id']);
		self::assertSame('csc-v1', $first['driverId']);
		self::assertFalse($first['enabled']);
		self::assertSame([['key' => 'accessToken', 'isConfigured' => true]], $first['secretFields']);
		self::assertSame([['key' => 'accessToken', 'isConfigured' => false]], $second['secretFields']);
		self::assertStringNotContainsString('synthetic-sensitive-token', json_encode($first, JSON_THROW_ON_ERROR));

		$list = $this->controller->index();
		self::assertSame(Http::STATUS_OK, $list->getStatus());
		$ids = array_column($list->getData()['items'], 'id');
		self::assertContains($first['id'], $ids);
		self::assertContains($second['id'], $ids);
		self::assertStringNotContainsString('synthetic-sensitive-token', json_encode($list->getData(), JSON_THROW_ON_ERROR));

		$read = $this->controller->show($first['id']);
		self::assertSame(Http::STATUS_OK, $read->getStatus());
		self::assertSame($first['secretFields'], $read->getData()['secretFields']);

		$updated = $this->controller->update($first['id'], $first['revision'], 'Renamed');
		self::assertSame(Http::STATUS_OK, $updated->getStatus());
		self::assertSame('Renamed', $updated->getData()['name']);
		self::assertSame($first['secretFields'], $updated->getData()['secretFields']);
		self::assertSame($first['revision'] + 1, $updated->getData()['revision']);

		$stale = $this->controller->update($first['id'], $first['revision'], 'Overwritten', null, ['accessToken' => 'stale-secret']);
		self::assertSame(Http::STATUS_CONFLICT, $stale->getStatus());
		self::assertSame('Renamed', $this->controller->show($first['id'])->getData()['name']);
		self::assertStringNotContainsString('stale-secret', json_encode($stale->getData(), JSON_THROW_ON_ERROR));

		$cleared = $this->controller->update(
			$first['id'], $updated->getData()['revision'], null, null, ['accessToken' => null],
		);
		self::assertSame(Http::STATUS_OK, $cleared->getStatus());
		self::assertSame([['key' => 'accessToken', 'isConfigured' => false]], $cleared->getData()['secretFields']);

		$enabled = $this->controller->enable($first['id'], $cleared->getData()['revision']);
		self::assertSame(Http::STATUS_OK, $enabled->getStatus());
		self::assertTrue($enabled->getData()['enabled']);

		$disabled = $this->controller->disable($first['id'], $enabled->getData()['revision']);
		self::assertSame(Http::STATUS_OK, $disabled->getStatus());
		self::assertFalse($disabled->getData()['enabled']);

		$deleted = $this->controller->destroy($first['id'], $disabled->getData()['revision']);
		self::assertSame(Http::STATUS_OK, $deleted->getStatus());
		self::assertSame(['deleted' => true], $deleted->getData());
		self::assertSame(Http::STATUS_NOT_FOUND, $this->controller->show($first['id'])->getStatus());
		self::assertSame(Http::STATUS_OK, $this->controller->show($second['id'])->getStatus());
	}

	public function testUnreadableSecretDoesNotBreakReadDisableOrRecovery(): void {
		$created = $this->create('Broken', 'broken', 'synthetic-secret');
		$qb = $this->db->getQueryBuilder();
		$qb->update('libresign_signing_integration')
			->set('secrets_enc', $qb->createNamedParameter('unrecoverable-ciphertext'))
			->where($qb->expr()->eq('public_id', $qb->createNamedParameter($created['id'])))
			->executeStatement();
		$read = $this->controller->show($created['id']);
		self::assertSame(Http::STATUS_OK, $read->getStatus());
		self::assertSame([['key' => 'accessToken', 'isConfigured' => false]], $read->getData()['secretFields']);
		$disabled = $this->controller->disable($created['id'], $created['revision']);
		self::assertSame(Http::STATUS_OK, $disabled->getStatus());
		$updated = $this->controller->update($created['id'], $disabled->getData()['revision'],
			null, null, ['accessToken' => 'replacement-synthetic']);
		self::assertSame(Http::STATUS_OK, $updated->getStatus());
		self::assertSame([['key' => 'accessToken', 'isConfigured' => true]], $updated->getData()['secretFields']);
		self::assertSame(Http::STATUS_CONFLICT,
			$this->controller->update($created['id'], $created['revision'], 'stale')->getStatus());
	}

	public function testInvalidPublicSecretAndUnsafeEndpointAreRejected(): void {
		$response = $this->controller->create(
			'csc-v1', 'Invalid', ['baseUrl' => 'http://127.0.0.1'],
			['accessToken' => 'must-not-appear'],
		);
		self::assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		self::assertStringNotContainsString('must-not-appear', json_encode($response->getData(), JSON_THROW_ON_ERROR));
	}

	/** @return array<string, mixed> */
	private function create(string $name, string $suffix, ?string $secret = null): array {
		$secrets = $secret === null ? [] : ['accessToken' => $secret];
		$response = $this->controller->create('csc-v1', $name, ['baseUrl' => 'https://' . $suffix . '.example.org'], $secrets);
		self::assertSame(Http::STATUS_CREATED, $response->getStatus(), json_encode($response->getData(), JSON_THROW_ON_ERROR));
		$data = $response->getData();
		$this->created[] = $data['id'];
		return $data;
	}
}
