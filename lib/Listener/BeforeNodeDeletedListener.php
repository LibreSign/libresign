<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
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
