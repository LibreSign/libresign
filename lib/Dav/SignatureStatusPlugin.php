<?php

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Dav;

use OCA\DAV\Connector\Sabre\Directory;
use OCA\DAV\Connector\Sabre\File;
use OCA\Libresign\Service\FileService;
use Sabre\DAV\INode;
use Sabre\DAV\PropFind;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;

class SignatureStatusPlugin extends ServerPlugin {
	public function __construct(
		private readonly FileService $fileService,
	) {
	}

	#[\Override]
	public function initialize(Server $server): void {
		$server->on('propFind', $this->propFind(...));
	}

	public function propFind(PropFind $propFind, INode $node): void {
		if (!$node instanceof File && !$node instanceof Directory) {
			return;
		}

		$nodeId = $node->getId();

		if (!$this->fileService->isLibresignFile($nodeId)) {
			return;
		}

		try {
			$this->fileService->setFileByNodeId($nodeId);
		} catch (\Throwable) {
			// Avoid breaking WebDAV property lookup when the node mapping is invalid.
			return;
		}

		$propFind->handle('{http://nextcloud.org/ns}libresign-signature-status', $this->fileService->getStatus());
		$propFind->handle('{http://nextcloud.org/ns}libresign-signed-node-id', $this->fileService->getSignedNodeId());
	}
}
