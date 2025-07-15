<?php

namespace OCA\Libresign\Dav;

use Sabre\DAV\INode;
use Sabre\DAV\PropFind;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;

class SignatureStatusPlugin extends ServerPlugin {
	protected $server;

	public function initialize(Server $server) {
		$this->server = $server;
		$server->on('propFind', [$this, 'propFind']);
	}

	public function propFind(PropFind $propFind, INode $node) {
		$propFind->handle('{http://nextcloud.org/ns}node-name', $node->getName());
	}
}
