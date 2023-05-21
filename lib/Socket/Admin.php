<?php

namespace OCA\Libresign\Socket;

use Exception;
use OCP\IRequest;

class Admin {
	private string $host;
	public function __construct(
		private IRequest $request
	) {
		$this->host = $this->request->getServerHost();
	}

	public function start(): int {
		$port = $this->getRunningProcessPort();
		if ($port) {
			$this->killZombies();
			// return $port;
		}
		$cmd = 'php ' . __DIR__ . '/server.php';
		$port = 8080;
		// $port = $this->findOpenPort();
		$fullCmd = sprintf(
			'%s %s > /dev/null 2>&1 & echo $!',
			escapeshellcmd($cmd),
			'0.0.0.0:' . $port
		);

		$pid = (string)(int) exec($fullCmd);

		if (!$pid) {
			throw new Exception('Error starting server, received ' . $pid . ', expected int PID');
		}
		return $port;
	}

	/**
	 * Let the OS find an open port for you.
	 *
	 * @return int
	 */
	private function findOpenPort() {
		$sock = socket_create(AF_INET, SOCK_STREAM, 0);

		// Bind the socket to an address/port
		if (!socket_bind($sock, $this->host, 0)) {
			throw new \Exception('Could not bind to address');
		}

		socket_getsockname($sock, $checkAddress, $checkPort);
		socket_close($sock);

		if ($checkPort > 0) {
			return $checkPort;
		}

		throw new \Exception('Failed to find open port');
	}

	private function killZombies(): void {
		$cmd = 'ps -eo pid,command|' .
			'grep "libresign/lib/Socket/server.php"|' .
			'grep -v grep|' .
			'sed -e "s/^[[:space:]]*//"|cut -d" " -f1';
		$pids = trim(exec($cmd));
		$pids = explode("\n", $pids);
		foreach ($pids as $pid) {
			if ($pid) {
				exec('kill ' . $pid);
			}
		}
	}

	private function getRunningProcessPort(): int {
		$cmd = 'ps -eo pid,command|' .
			'grep "libresign/lib/Socket/server.php"|' .
			'grep -v grep';
		$process = trim(exec($cmd));
		preg_match('/:(?<port>\d+$)/', $process, $matches);
		if ($matches) {
			return $matches['port'];
		}
		return 0;
	}
}
