<?php

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Dav;

use OC;
use OCA\DAV\Connector\Sabre\File;
use OCA\Libresign\Service\FileService;
use Sabre\DAV\INode;
use Sabre\DAV\PropFind;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;

class SignatureStatusPlugin extends ServerPlugin {
	protected $server;

	public function initialize(Server $server): void {
		$this->server = $server;
		$server->on('propFind', [$this, 'propFind']);
	}

	public function propFind(PropFind $propFind, INode $node): void {
		if ($node instanceof File) {
			$fileService = OC::$server->get(FileService::class);
			$nodeId = $node->getId();

			if ($fileService->isLibresignFile($nodeId)) {
				$fileService->setFileByType('FileId', $nodeId);

				$propFind->handle('{http://nextcloud.org/ns}signature-status', $fileService->getStatus());
				$propFind->handle('{http://nextcloud.org/ns}signed-node-id', $fileService->getSignedNodeId());
			}
		}
	}
}
