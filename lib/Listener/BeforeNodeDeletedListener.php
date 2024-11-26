<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Listener;

use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\RequestSignatureService;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Cache\CacheEntryRemovedEvent;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Files\File;
use OCP\IDBConnection;

/**
 * @template-implements IEventListener<Event|BeforeNodeDeletedEvent|CacheEntryRemovedEvent>
 */
class BeforeNodeDeletedListener implements IEventListener {
	public function __construct(
		private FileMapper $fileMapper,
		private RequestSignatureService $requestSignatureService,
		private IDBConnection $db,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof BeforeNodeDeletedEvent) {
			$node = $event->getNode();
			if (!$node instanceof File) {
				return;
			}
			if (!in_array($node->getMimeType(), ValidateHelper::VALID_MIMETIPE)) {
				return;
			}
			$nodeId = $node->getId();
			$this->delete($nodeId);
			return;
		}
		if ($event instanceof CacheEntryRemovedEvent) {
			$this->delete($event->getFileId());
		}
		return;
	}

	private function delete(int $nodeId): void {
		$type = $this->fileMapper->getFileType($nodeId);
		if ($type === 'not_libresign_file') {
			return;
		}
		switch ($type) {
			case 'signed_file':
				$file = $this->fileMapper->getByFileId($nodeId);
				$nodeId = $file->getNodeId();
				$this->requestSignatureService->deleteRequestSignature(['file' => ['fileId' => $nodeId]]);
				break;
			case 'file':
				$libresignFile = $this->fileMapper->getByFileId($nodeId);
				$this->requestSignatureService->deleteRequestSignature(['file' => ['fileId' => $nodeId]]);
				$this->fileMapper->delete($libresignFile);
				break;
			case 'user_element':
			case 'file_element':
				$field = $type === 'file' ? 'node_id' : 'file_id';
				$qb = $this->db->getQueryBuilder();
				$qb->delete('libresign_' . $type)
					->where($qb->expr()->eq($field, $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)))
					->executeStatement();
		}
	}
}
