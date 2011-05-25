<?php
/************************************************************
 * icarus v0.1-alpha -- an IRC framework for PHP            *
 * Author: rintaun - Matthew J. Lanigan <rintaun@gmail.com> *
 *                                                          *
 * Copyright 2011 Matthew J. Lanigan.                       *
 * See LICENSE file for licensing restrictions              *
 ************************************************************/

if (!defined('_ICARUS_')) die('This script may not be invoked directly.' . "\n");

define('SH_UNKNOWN', 0);
define('SH_SERVER', 1);
define('SH_CLIENT', 2);

final class SocketHandler extends Singleton
{
	private $sockets = array();
	private $interrupt = FALSE;

	private $sendq = array();
	private $recvq = array();

	protected function _create()
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

			if (empty($read) && empty($write)) _die("I find your lack of sockets... disturbing.");

			if (socket_select($read, $write, $except, 1) < 1) continue;

			// Then do them in reverse of the order we built them in!

			if (!empty($write))
			{
				foreach ($write AS $socket)
				{
					$sid = $this->getSID($socket);

					// if there is no entry in the sendq for this, then something got screwed up.
					if (isset($this->sendq[$sid]))
						$length = strlen($this->sendq[$sid]);
					else
						continue;

					// if the length is 0, something else got screwed up.
					if ($length == 0)
					{
						unset($this->sendq[$sid]);
						continue;
					}

					// try to write. $w is how many bytes are written.
					$w = socket_write($socket, $this->sendq[$sid]);
				
					// if this is greater than 0, but less than the total, cut off the part that was written
					// and wait until we can write again. otherwise, unset the sendq entry
					if (($w < $length) && ($w > 0))
						$this->sendq[$sid] = substr($this->sendq[$sid], $w);
					else if ($w == $length)
						unset ($this->sendq[$sid]);
						
				}

				unset($sid);
			}

			if (!empty($read))
			{
				foreach ($read AS $socket)
				{
					// get the socket id
					$sid = $this->getSID($socket);

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
							'parent' => call_user_func(array($this->sockets[$sid]['parent'], 'connect'), $csid)
						);
					}
					else
					{
						$data = socket_read($this->sockets[$sid]['socket'], 65536);
						call_user_func(array($this->sockets[$sid]['parent'], 'read'), $sid, $data);
					}

					unset($sid);
				}
			}
		}
	}

	private function getSID($socket)
	{
		foreach ($this->sockets AS $sid => $data)
			if ($data['socket'] === $socket) return $sid;
	}

	public static function getType($sid)
	{
		switch (substr($sid,0,1))
		{
			case 's': return SH_SERVER;
			case 'c': return SH_CLIENT;
			default: return SH_UNKNOWN;
		}
	}

	public function interrupt()
	{
		$this->interrupt = TRUE;
	}

	public function createSocket($address, $port, $parent)
	{
		// if $object isn't a Client, things will get screwed up later on 
		if (!($parent instanceof Client)) return FALSE;

		$sid = uniqid('c');
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
			'parent' => $parent
		);
		return $sid;
	}

	public function createListener($bind, $port, $object)
	{
		// if $object isn't a Server, things will get screwed up later on 
		if (!($parent instanceof Server)) return FALSE;

		$sid = uniqid('s');
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
			'parent' => $parent
		);
		return $sid;		
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

		$this->sendq[$sid] .= $message;
	}

	protected function _destroy()
	{
		// at some point, we should probably go through and close all of our sockets
		// but well, I'm a little lazy right now.
	}
}
