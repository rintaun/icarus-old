<?php
/************************************************************
 * icarus v0.1-alpha -- an IRC framework for PHP            *
 * Author: rintaun - Matthew J. Lanigan <rintaun@gmail.com> *
 *                                                          *
 * Copyright 2011 Matthew J. Lanigan.                       *
 * See LICENSE file for licensing restrictions              *
 ************************************************************/

if (!defined('_ICARUS_')) die('This script may not be invoked directly.' . "\n");

class Client_IRC extends Client {
	private $name;
	private $config;

	private $readq = "";

	private $registering;

	private $mynick;
	private $myuser;
	private $myname;

	public function _create($name, $config)
	{
		$this->name = $name;
		$this->config = $config;

		$this->registering = TRUE;

		$this->mynick = $config['nick'];
		$this->myuser = $config['username'];
		$this->myname = $config['realname'];

		$this->nick($config['nick']);
		$this->user($config['username'], $config['realname']);
	}

	public function _destroy()
	{
	}

	public function parse ($data)
	{
		$irc = "/^(?:\:(\S+)\s+)?(\w+)(?:\s+(?!:)(.*?))?(?:\s+:(.+))?$/";

		$data = explode("\n", $data);
		foreach ($data AS $key => $line)
		{
			if (!isset($data[$key+1]))
			{
				$this->readq .= $line;
				break;
			}

			if (!empty($this->readq))
			{
				$line = $this->readq . $line;
				$this->readq = "";
			}

			$line = trim($line);

			_log(L_DEBUG, $line);

			preg_match($irc, $line, $matches);
			$return = array(
				'origin' => (isset($matches[1])) ? $matches[1] : "",
				'command' => strtolower($matches[2]),
				'params' => (!empty($matches[3])) ? explode(" ",$matches[3]) : array(),
			);
			if (isset($matches[4]))
			{	
				$return['params'][] = $matches[4];
				$return['freeform'] = TRUE;
			}

			if ((!empty($return['origin'])) && (preg_match('/(?P<nick>[^!]+)!(?P<user>[^@]+)@(?P<host>.+)/', $return['origin'], $matches)))
			{
				unset($matches[1]);
				unset($matches[2]);
				unset($matches[3]);
				$return['origin'] = $matches;
			}

			if (is_numeric($return['command']))
				if (method_exists($this, 'numeric_' . $return['command']))
					call_user_func(array($this, 'numeric_' . $return['command']), $return['origin'], $return['params']);

			if (method_exists($this, 'cmd_' . $return['command']))
				call_user_func(array($this, 'cmd_' . $return['command']), $return['origin'], $return['params']);
		}
	}

	private function numeric_001()
	{
		$this->registering = false;

		if (is_array($this->config['channel']))
			foreach ($this->config['channel'] AS $channel => $params)
			{
				if (isset($params['key']))
					$this->send("JOIN %s :%s", $channel, $params['key']);
				else
					$this->send("JOIN %s", $channel);
			}

		$this->eventPost('connected');
	}

	private function cmd_ping($origin, $params)
	{
		if (isset($params[0]))
			$this->send("PONG :%s", $params[0]);
		else
			$this->send("PONG");
	}

	private function cmd_privmsg($origin, $params)
	{
		if ($params[0] == $this->mynick)
			$this->eventPost('privmsgme', $origin, $params);
		else
			$this->eventPost('privmsg', $origin, $params);
	}

	private function cmd_part($origin, $params)
	{
		if ((!isset($this->channels[$params[0]])) || (!is_array($this->channels[$params[0]])))
		{
			_log(L_WARNING, "%s: Received part for %s, but I didn't know I was there!", get_called_class(), $params[0]);
			return;
		}

		if ($origin['nick'] == $this->mynick)
		{
			$this->eventPost('mypart', $origin, $params);
			unset($this->channels[$params[0]]);
		}
		else
		{
			foreach ($this->channels[$params[0]] AS $key => $user)
			{
				if ($user['nick'] == $origin['nick'])
				{
					$this->eventPost('part', $origin, $params);
					unset($this->channels[$params[0]][$key]);
				}
			}
		}
	}

	private function cmd_join($origin, $params)
	{
		if ($origin['nick'] == $this->mynick)
		{
			$this->channels[$params[0]] = array();
			$this->eventPost('myjoin', $origin, $params);
			return;
		}
		else if ((!isset($this->channels[$params[0]])) || (!is_array($this->channels[$params[0]])))
		{
			_log(L_WARNING, "%s: Received join for %s, but I didn't know I was there!", get_called_class(), $params[0]);
		}
		else
		{
			$this->channels[$params[0]][] = $origin;
			$this->eventPost('join', $origin, $params);
		}
	}

	public function isChanUser($chan, $nick)
	{
		if ((!isset($this->channels[$chan])) || (!is_array($this->channels[$chan])))
			return FALSE;

		foreach ($this->channels[$chan] AS $key => $user)
		{
			if ($user['nick'] == $nick)
				return TRUE;
		}

		return FALSE;
	}

	public function privmsg($target, $text)
	{
		$this->send("PRIVMSG %s :%s", $target, $text);
	}

	public function join ($target, $key="")
	{
		$this->send("JOIN %s :%s", $target, $key);
	}

	public function part($target, $reason="")
	{
		$this->send("PART %s :%s", $target, $reason);
	}

	public function quit($reason="")
	{
		$this->send("QUIT :%s", $reason);
	}

	public function nick($nickname)
	{
		$this->send("NICK %s", $nickname);
	}

	private function user($username, $realname)
	{
		$this->send("USER %s * * :%s", $username, $realname);
	}

	public function send($format)
	{
                if (strlen($format) == 0) return;

                $args = func_get_args();
		$args[0] .= "\n";
		
                call_user_func_array(array($this, 'write'), $args);

		$args[0] = "-> " . $args[0];
		array_unshift($args, L_DEBUG);
		call_user_func_array('_log', $args);
	}
}
