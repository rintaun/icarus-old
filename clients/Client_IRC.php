<?php
/************************************************************
 * icarus v0.1-alpha -- an IRC framework for PHP            *
 * Author: rintaun - Matthew J. Lanigan <rintaun@gmail.com> *
 *                                                          *
 * Copyright 2011 Matthew J. Lanigan.                       *
 * See LICENSE file for licensing restrictions              *
 ************************************************************/

if (!defined('_ICARUS_')) die('This script may not be invoked directly.' . "\n");

class Client_IRC extends Client{
	private $name;
	private $config;

	private $readq = "";

	private $registering;

	public function _create($name, $config)
	{
		$this->name = $name;
		$this->config = $config;

		$this->registering = TRUE;

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

			if (is_numeric($return['command']))
				if (method_exists($this, 'numeric_' . $return['command']))
					call_user_func(array($this, 'numeric_' . $return['command']), $return);

			if (method_exists($this, 'cmd_' . $return['command']))
				call_user_func(array($this, 'cmd_' . $return['command']), $return);
		}
	}

	private function onConnect()
	{
		if (is_array($this->config['channel']))
			foreach ($this->config['channel'] AS $channel => $params)
			{
				if (isset($params['key']))
					$this->send("JOIN %s :%s", $channel, $params['key']);
				else
					$this->send("JOIN %s", $channel);
			}
	}

	private function numeric_001()
	{
		$this->registering = false;
		$this->onConnect();
	}

	private function cmd_ping($d)
	{
		if (isset($d['params'][0]))
			$this->send("PONG :%s", $d['params'][0]);
		else
			$this->send("PONG");
	}

	private function nick($nickname)
	{
		$this->send("NICK %s", $nickname);
	}

	private function user($username, $realname)
	{
		$this->send("USER %s * * :%s", $username, $realname);
	}

	private function send($format)
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
