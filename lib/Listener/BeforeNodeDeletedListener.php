<?php

namespace OCA\Libresign\Listener;

use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Service\SignFileService;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Files\File;
use OCP\IDBConnection;

class BeforeNodeDeletedListener implements IEventListener {
	/** @var FileMapper */
	private $fileMapper;
	/** @var SignFileService */
	private $signFileService;
	/** @var IDBConnection */
	private $db;

	public function __construct(
		FileMapper $fileMapper,
		SignFileService $signFileService,
		IDBConnection $db
	) {
		$this->fileMapper = $fileMapper;
		$this->signFileService = $signFileService;
		$this->db = $db;
	}

	public function handle(Event $event): void {
		if (!$event instanceof BeforeNodeDeletedEvent) {
			return;
		}
		if (!$event->getNode() instanceof File) {
			return;
		}
		$nodeId = $event->getNode()->getId();
		$type = $this->fileMapper->getFileType($nodeId);
		if ($type === 'not_libresign_file') {
			return;
		}
		switch ($type) {
			case 'signed_file':
				$file = $this->fileMapper->getByFileId($nodeId);
				$nodeId = $file->getNodeId();
				$type = 'file';
				// no break
			case 'file':
				$this->signFileService->deleteSignRequest(['file' => ['fileId' => $nodeId]]);
				break;
			case 'user_element':
			case 'file_element':
				$this->deleteByType($nodeId, $type);
		}
	}

	private function deleteByType(int $nodeId, string $type): void {
		$field = $type === 'file' ? 'node_id' : 'file_id';
		$qb = $this->db->getQueryBuilder();
		$qb->delete('libresign_' . $type)
			->where($qb->expr()->eq($field, $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}
}
