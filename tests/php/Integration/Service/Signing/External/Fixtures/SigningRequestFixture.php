<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service\Signing\External\Fixtures;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Tests\Integration\TestCase;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Server;

/** Test-owned file and native signing request. The base TestCase owns user cleanup. */
final readonly class SigningRequestFixture {
	private function __construct(
		public FileEntity $file,
		public SignRequest $request,
		public File $source,
	) {
	}

	public static function create(TestCase $test): self {
		$pdf = file_get_contents(dirname(__DIR__, 5) . '/fixtures/pdfs/small_valid.pdf');
		TestCase::assertIsString($pdf);
		$user = $test->createAccount('csc-api-' . bin2hex(random_bytes(5)), 'temporary-password');
		$source = Server::get(IRootFolder::class)->getUserFolder($user->getUID())->newFile('source.pdf', $pdf);
		$file = new FileEntity();
		$file->setUuid(bin2hex(random_bytes(16)));
		$file->setName('source.pdf');
		$file->setNodeId($source->getId());
		$file->setUserId($user->getUID());
		$file->setStatus(FileStatus::ABLE_TO_SIGN->value);
		$file->setCreatedAt(new \DateTime('now', new \DateTimeZone('UTC')));
		$file = Server::get(FileMapper::class)->insert($file);

		$request = new SignRequest();
		$request->setUuid(bin2hex(random_bytes(16)));
		$request->setFileId($file->getId());
		$request->setStatusEnum(SignRequestStatus::ABLE_TO_SIGN);
		$request->setMetadata(['signingMethodRequirement' => 'external_certificate']);
		$request->setDisplayName('Synthetic authorized signer');
		$request->setCreatedAt(new \DateTime('now', new \DateTimeZone('UTC')));
		$request = Server::get(SignRequestMapper::class)->insert($request);
		return new self($file, $request, $source);
	}
}
