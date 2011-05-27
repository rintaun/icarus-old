<?php
/************************************************************
 * icarus v1.0-beta -- an IRC framework for PHP            *
 * Author: rintaun - Matthew J. Lanigan <rintaun@gmail.com> *
 *                                                          *
 * Copyright 2011 Matthew J. Lanigan.                       *
 * See LICENSE file for licensing restrictions              *
 ************************************************************/

class Module_IRCBot extends Module {
	private $prefix;

	public function _create($name, $config)
	{
		$this->prefix = $config['prefix'];

		$this->parent->eventAdd('privmsg', array($this, 'parseCommands'), -1);
		$this->parent->eventAdd('privmsgme', array($this, 'parseCommands'), -1);
	}

	public function parseCommands($origin, $params)
	{
		// :nick!user@host PRIVMSG target :message

		if (substr($params[1], 0, 1) == $this->prefix)
		{
			$cmdpar = explode(" ", substr($params[1], 1));
			$command = array_shift($cmdpar);

			if (method_exists($this, 'cmd_' . $command))
				call_user_func(array($this, 'cmd_' . $command), $origin, $params[0], $cmdpar);

			return FALSE;
		}
	}

	public function cmd_raw($origin, $target, $params)
	{
		$params = implode(" ", $params);
		$this->parent->send("%s", $params);
	}

	public function cmd_eval($origin, $target, $params)
	{
		if (strtoupper($params[0]) == "--QUIET")
		{
			$quiet = TRUE;
			array_shift($params);
		}

		$params = implode(" ", $params);

		if ($quiet != TRUE)
		{
			ob_start();
			eval($params);
			$result = ob_get_contents();
			ob_end_clean();
	
			$result = explode("\n", $result);
			foreach ($result AS $line);
			{
				$line = trim($line);
				if (!empty($line))
					$this->parent->privmsg((substr($target,0,1) == "#") ? $target : $origin['nick'], $line);
			}
		}
		else
			eval($params);
	}

	public function cmd_join($origin, $target, $params)
	{
		if (isset($params[1]))
			$this->parent->join($params[0], $params[1]);
		else if (isset($params[0]))
			$this->parent->join($params[0]);
	}

	public function cmd_part($origin, $target, $params)
	{
		if (isset($params[1]))
		{
			$target = array_shift($params);
			$message = implode(" ", $params);
			$this->parent->part($target, $message);
		}
		else if (isset($params[0]))
			$this->parent->part($params[0]);
	}

	public function _destroy()
	{
	}
}
