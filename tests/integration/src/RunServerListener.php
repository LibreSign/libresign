<?php

namespace LibreCode\Server;

use Behat\Testwork\EventDispatcher\Event\AfterSuiteTested;
use Behat\Testwork\EventDispatcher\Event\BeforeSuiteTeardown;
use Behat\Testwork\EventDispatcher\Event\BeforeSuiteTested;
use Features\Exceptions\ServerException;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RunServerListener implements EventSubscriberInterface {
	private $pid;
	/** @var string */
	private static $host = '127.0.0.1';
	/** @var int */
	private static $port = 0;
	/** @var ?int */
	private $verbose = null;
	/** @var string */
	private $rootDir;

	public function __construct($verbose, $rootDir)
	{
		$this->verbose = $verbose;
		$this->rootDir = $rootDir;
	}

	public static function getSubscribedEvents()
	{
		return array(
			BeforeSuiteTested::BEFORE   => 'beforeSuite',
			BeforeSuiteTeardown::AFTER  => 'afterSuite'
		);
	}

	public function beforeSuite(BeforeSuiteTested $event) {
		$this->killZombies();
		if( $this->isRunning() ) {
			return;
		}

		if( self::$port == 0 ) {
			self::$port = $this->findOpenPort();
		}

		$script = escapeshellarg($this->rootDir);

		$cmd = 'php -S ' . self::$host .':' . self::$port . ' -t ' . $script;

		if (is_numeric($this->verbose)) {
			$verbose = '';
		} else {
			$verbose = '2>&1';
		}

		$fullCmd = sprintf('%s > /dev/null %s & echo $!',
			escapeshellcmd($cmd),
			$verbose
		);

		$this->pid = exec($fullCmd);

		if( !ctype_digit($this->pid) ) {
			throw new ServerException('Error starting server, received ' . $this->pid . ', expected int PID');
		}

		for( $i = 0; $i <= 20; $i++ ) {
			usleep(100000);

			$open = @fsockopen(self::$host, self::$port);
			if( is_resource($open) ) {
				fclose($open);
				break;
			}
		}

		if( !$this->isRunning() ) {
			throw new ServerException('Failed to start server. Is something already running on port ' . self::$port . '?');
		}

		register_shutdown_function(function () {
			if( $this->isRunning() ) {
				$this->stop();
			}
		});
	}

	/**
	 * Is the Web Server currently running?
	 *
	 * @return bool
	 */
	public function isRunning() {
		if( !$this->pid ) {
			return false;
		}

		$result = shell_exec(sprintf('ps %d', $this->pid));

		return count(explode("\n", $result)) > 2;
	}

	/**
	 * Stop the Web Server
	 */
	public function stop() {
		if( $this->pid ) {
			exec(sprintf('kill %d',
				$this->pid));
		}
		$this->killZombies();

		$this->pid = 0;
	}

	public function killZombies() {
		$pids = trim(shell_exec('ps -eo pid,command|grep "php -S ' . self::$host . '"|grep -v grep|sed -e "s/^[[:space:]]*//"|cut -d" " -f1'));
		$pids = explode("\n", $pids);
		foreach ($pids as $pid) {
			if ($pid) {
				exec('kill ' . $pid);
			}
		}
	}

	/**
	 * Get the HTTP root of the webserver
	 *  e.g.: http://127.0.0.1:8123
	 *
	 * @return string
	 */
	public static function getServerRoot() {
		return 'http://' . self::$host . ':' . self::$port . '/';
	}

	/**
	 * Get the port the network server is to be ran on.
	 *
	 * @return int
	 */
	public function getPort() {
		return self::$port;
	}

	/**
	 * Let the OS find an open port for you.
	 *
	 * @return int
	 */
	private function findOpenPort() {
		$sock = socket_create(AF_INET, SOCK_STREAM, 0);

		// Bind the socket to an address/port
		if( !socket_bind($sock, self::$host, 0) ) {
			throw new RuntimeException('Could not bind to address');
		}

		socket_getsockname($sock, $checkAddress, $checkPort);
		socket_close($sock);

		if( $checkPort > 0 ) {
			return $checkPort;
		}

		throw new RuntimeException('Failed to find open port');
	}

	public static function afterSuite(AfterSuiteTested $event) {
		$oi = 1;
	}
}
