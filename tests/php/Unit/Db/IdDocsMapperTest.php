<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Db;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCP\Server;

/**
 * @group DB
 */
final class IdDocsMapperTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private FileMapper $fileMapper;
	private IdDocsMapper $idDocsMapper;
	private SignRequestMapper $signRequestMapper;

	public function setUp(): void {
		parent::setUp();
		$this->fileMapper = Server::get(FileMapper::class);
		$this->idDocsMapper = Server::get(IdDocsMapper::class);
		$this->signRequestMapper = Server::get(SignRequestMapper::class);
	}

	public function testListByUserIdReturnsDocumentsWhenAccountIsMissingFromUsersTable(): void {
		$ldapUserId = 'ldap-user-without-oc-users-row';

		$file = new File();
		$file->setNodeId(80808);
		$file->setUserId($ldapUserId);
		$file->setUuid('c3333333-3333-4333-8333-333333333333');
		$file->setCreatedAt(new \DateTime('now', new \DateTimeZone('UTC')));
		$file->setName('passport.pdf');
		$file->setStatus(FileStatus::ABLE_TO_SIGN->value);
		$insertedFile = $this->fileMapper->insert($file);

		$signRequest = new SignRequest();
		$signRequest->setFileId($insertedFile->getId());
		$signRequest->setDisplayName('LDAP User');
		$signRequest->setUuid('d4444444-4444-4444-8444-444444444444');
		$signRequest->setCreatedAt(new \DateTime('now', new \DateTimeZone('UTC')));
		$insertedSignRequest = $this->signRequestMapper->insert($signRequest);

		$this->idDocsMapper->save(
			$insertedFile->getId(),
			$insertedSignRequest->getId(),
			$ldapUserId,
			'IDENTIFICATION',
		);

		$result = $this->idDocsMapper->list(
			['userId' => $ldapUserId],
			page: 1,
			length: 10,
		);

		$this->assertCount(1, $result['data']);
		$this->assertSame($insertedFile->getUuid(), $result['data'][0]['file']['uuid']);
		$this->assertSame('LDAP User', $result['data'][0]['account']['displayName']);
	}
}
