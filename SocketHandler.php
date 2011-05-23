<?php

/************************************************************
 * Bounce v0.1-alpha                                        *
 * Author: rintaun - Matthew J. Lanigan <rintaun@gmail.com> *
 *                                                          *
 * Copyright 2011 Matthew J. Lanigan.                       *
 * See LICENSE file for licensing details.                  *
 ************************************************************
 * src/SocketHandler.php                                    *
 *                                                          *
 * Description: Socket handling subsystem                   *
 ************************************************************/

if (!defined('_BOUNCE_')) die('This script may not be invoked directly.');

define('SH_UNKNOWN', 0);
define('SH_SERVER', 1);
define('SH_CLIENT', 2);
define('SH_LISTENER', 3);

final class SocketHandler extends Singleton
{
	private $sockets = array();
	private $interrupt = FALSE;

	private $sendq = array();
	private $recvq = array();

	protected function __construct()
	{
	}

	public function close($sid)
	{
		if (!is_resource($this->sockets[$sid])) return FALSE;
		socket_close($this->sockets[$sid]['socket']);
		unset($this->sockets[$sid]);
	}

	public function loop()
	{
		while (!$this->interrupt)
		{
			// do hooks such as timers here
			
			// First, build the read array from ALL available sockets.
			$read = array();
			foreach ($this->sockets AS $sid => $entry) $read[$sid] = $entry['socket'];

			// Then build the write array from all the sockets in sendq.
			$write = array();
			foreach ($this->sendq AS $sid => $entry) $write[$sid] = $this->sockets[$entry['sid']]['socket'];
			
			// select doesn't actually check for exceptions, so we'll make our own later
			$except = NULL;

			if (empty($read) && empty($write)) continue;

			if (socket_select($read, $write, $except, 1) < 1) continue;

			// Then do them in reverse of the order we built them in!

			if (!empty($write))
			{
				foreach ($write AS $socket) $sids[] = $this->getSID($socket);
				foreach ($this->sendq AS $entry)
				{
					if (in_array($entry['sid'], $sids))
					{
						$length = strlen($entry['data']);
						$w = 0;
						while ($w < $length)
							$w += socket_write($this->sockets[$entry['sid']]['socket'], $entry['data']);
						array_shift($this->sendq);
					}
					else break;
				}
			}

			if (!empty($read))
			{
				foreach ($read AS $socket) $sids[] = $this->getSID($socket);
				foreach ($sids AS $sid)
				{
					// if it's a listener, then we have a connection.
					if (self::getType($sid) == SH_LISTENER)
					{
						if (($client = @socket_accept($this->sockets[$sid]['socket'])) === FALSE) continue;
						$csid = uniqid('c');
						$address = "";
						$port = 0;
						socket_getpeername($client, $address, $port);

						$this->sockets[$csid] = array(
							'socket' => $client,
							'address' => $address,
							'port' => $port,
							'callback' => call_user_func($this->sockets[$sid]['callback'], $csid)
						);
					}
					else
					{
						$data = socket_read($this->sockets[$sid]['socket'], 65536);
						while (strlen($data) > 0)
							$data = call_user_func($this->sockets[$sid]['callback'], $sid, $data);
					}
				}
			}
		}
	}

	private function getSID($socket)
	{
		foreach ($this->sockets AS $sid => $data)
			if ($data['socket'] == $socket) return $sid;
	}

	public static function getType($sid)
	{
		switch (substr($sid,0,1))
		{
			case 's': return SH_SERVER;
			case 'c': return SH_CLIENT;
			case 'l': return SH_LISTENER;
			default: return SH_UNKNOWN;
		}
	}

	public function interrupt()
	{
		$this->interrupt = TRUE;
	}

	public function createSocket($address, $port, $callback)
	{
		$sid = uniqid('s');
		if (!$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))
		{
			_log(L_ERROR, "Unable to create socket %s (%s:%d).", $sid, $address, $port);
			return FALSE;
		}
		if (!socket_connect($socket, $address, $port))
		{
			_log(L_ERROR, "Could not connect to %s on port %d (socket %s).", $address, $port, $sid);
			return FALSE;
		}
		socket_set_nonblock($socket);

		$this->sockets[$sid] = array(
			'socket' => $socket,
			'address' => $address,
			'port' => $port,
			'callback' => $callback
		);
		return $sid;
	}

	public function createListener($bind, $port, $callback)
	{
		$sid = uniqid('l');
		if (!$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))
		{
			_log(L_ERROR, "Unable to create listener %s (%s:%d).", $sid, $bind, $port);
			return FALSE;
		}
		if (!socket_bind($socket, $bind, $port))
		{
			_log(L_ERROR, "Could not bind listener %s to %s port %d.", $sid, $bind, $port);
			return FALSE;
		}
		if (!socket_listen($socket))
		{
			_log(L_ERROR, "Could no listen on socket %s.", $sid);
			return FALSE;
		}
		socket_set_nonblock($socket);

		$this->sockets[$sid] = array(
			'socket' => $socket,
			'address' => $bind,
			'port' => $port,
			'callback' => $callback
		);
		return $sid;		
	}

	public function updateCallback($sid, $callback)
	{
		if (isset($this->sockets[$sid]))
			$this->sockets[$sid]['callback'] = $callback;
	}

	public function send($sid, $format)
	{
		$args = func_get_args();
		array_shift($args);
		array_shift($args);
		if (!empty($args))
			$message = vsprintf($format, $args);
		else
			$message = $format;

		$this->sendq[] = array('sid' => $sid, 'data' => $message);
	}

	protected function _destroy()
	{
	}
}
