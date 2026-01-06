<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Listener;

use OCA\Libresign\Helper\ValidateHelper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Cache\CacheEntryRemovedEvent;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\IDBConnection;

/**
 * @template-implements IEventListener<Event|BeforeNodeDeletedEvent|CacheEntryRemovedEvent>
 */
class BeforeNodeDeletedListener implements IEventListener {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof BeforeNodeDeletedEvent) {
			$node = $event->getNode();
			if (!$node instanceof File && !$node instanceof Folder) {
				return;
			}
			if ($node instanceof File && !in_array($node->getMimeType(), ValidateHelper::VALID_MIMETIPE)) {
				return;
			}

			$this->deleteAllByNodeId($node->getId());
			return;
		}

		if ($event instanceof CacheEntryRemovedEvent) {
			$this->deleteAllByNodeId($event->getFileId());
		}
	}

	private function deleteAllByNodeId(int $nodeId): void {
		$fullOuterJoin = $this->db->getQueryBuilder();
		$fullOuterJoin->select($fullOuterJoin->expr()->literal(1));

		$qb = $this->db->getQueryBuilder();
		$qb
			->selectAlias('current.id', 'file_id')
			->selectAlias('parent.id', 'parent_id')
			->selectAlias('children.id', 'child_id')
			->selectAlias('sibling.id', 'sibling_id')
			->selectAlias('sf.id', 'signed_file_id')
			->selectAlias('ue.id', 'user_element_id')
			->selectAlias('fe.file_id', 'file_element_file_id')
			->from($qb->createFunction('(' . $fullOuterJoin->getSQL() . ')'), 'foj')
			->leftJoin('foj', 'libresign_file', 'current', $qb->expr()->eq('current.node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)))
			->leftJoin('current', 'libresign_file', 'parent', $qb->expr()->eq('parent.id', 'current.parent_file_id'))
			->leftJoin('current', 'libresign_file', 'children', $qb->expr()->eq('children.parent_file_id', 'current.id'))
			->leftJoin('parent', 'libresign_file', 'sibling', $qb->expr()->andX(
				$qb->expr()->eq('sibling.parent_file_id', 'parent.id'),
				$qb->expr()->neq('sibling.id', 'current.id')
			))
			->leftJoin('foj', 'libresign_file', 'sf', $qb->expr()->eq('sf.signed_node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)))
			->leftJoin('foj', 'libresign_user_element', 'ue', $qb->expr()->eq('ue.node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)))
			->leftJoin('foj', 'libresign_file_element', 'fe', $qb->expr()->eq('fe.file_id', 'current.id'));

		$cursor = $qb->executeQuery();

		$deletedFiles = [];
		$deletedUserElements = false;
		$deletedFileElements = [];

		while ($row = $cursor->fetch()) {
			if (!empty($row['user_element_id']) && !$deletedUserElements) {
				$deletedUserElements = true;
				$this->deleteUserElement($nodeId);
			}

			if (!empty($row['file_element_file_id']) && !isset($deletedFileElements[$row['file_element_file_id']])) {
				$deletedFileElements[(int)$row['file_element_file_id']] = true;
				$this->deleteFileElement((int)$row['file_element_file_id']);
			}

			foreach (['child_id', 'sibling_id', 'file_id', 'parent_id', 'signed_file_id'] as $key) {
				if (!empty($row[$key]) && !isset($deletedFiles[$row[$key]])) {
					$deletedFiles[(int)$row[$key]] = true;
					$this->deleteLibreSignFile((int)$row[$key]);
				}
			}
		}
		$cursor->closeCursor();
	}

	private function deleteUserElement(int $nodeId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('libresign_user_element')
			->where($qb->expr()->eq('node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	private function deleteFileElement(int $fileId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('libresign_file_element')
			->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	private function deleteLibreSignFile(int $fileId): void {
		$this->deleteIdentifyMethods($fileId);
		$this->deleteSignRequests($fileId);
		$this->deleteIdDocs($fileId);
		$this->deleteFile($fileId);
	}

	private function deleteIdentifyMethods(int $fileId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('libresign_sign_request')
			->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
		$cursor = $qb->executeQuery();

		while ($row = $cursor->fetch()) {
			$delete = $this->db->getQueryBuilder();
			$delete->delete('libresign_identify_method')
				->where($delete->expr()->eq('sign_request_id', $delete->createNamedParameter($row['id'], IQueryBuilder::PARAM_INT)))
				->executeStatement();
		}
		$cursor->closeCursor();
	}

	private function deleteSignRequests(int $fileId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('libresign_sign_request')
			->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	private function deleteIdDocs(int $fileId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('libresign_id_docs')
			->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	private function deleteFile(int $fileId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('libresign_file')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}
}
