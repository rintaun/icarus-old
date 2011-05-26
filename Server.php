<?php
/************************************************************
 * icarus v0.1-alpha -- an IRC framework for PHP            *
 * Author: rintaun - Matthew J. Lanigan <rintaun@gmail.com> *
 *                                                          *
 * Copyright 2011 Matthew J. Lanigan.                       *
 * See LICENSE file for licensing restrictions              *
 ************************************************************/

if (!defined('_ICARUS_')) die('This script may not be invoked directly.' . "\n");

abstract class Server extends EventHandler {
	private $sid = "";
	private $config;

	final public function __construct($name, $config)
	{
		$this->config = $config;

		if ((!isset($config['listen'])) || (!isset($config['port'])))
			_die("Server %s: Didn't get a listening address or port value!", $name);

		$SH = SocketHandler::getInstance();
		// $this->sid = $SH->createListener(etc. etc. etc.)

		$this->loadModules();

		$this->_create($name, $config);
	}

	final public function __destruct()
	{
		$this->_destroy();
	}

	public function loadModules()
	{
		if ((isset($this->config['module'])) && (is_array($this->config['module'])))
			foreach ($this->config['module'] AS $key => $entry)
			{
				$keyinfo = explode(":", $key);

				$type = 'Module_' . $keyinfo[0];
				$name = $keyinfo[1];

				if (file_exists('modules/' . $type . '.php'))
				{
					require_once('modules/' . $type . '.php');
					new $type($this, $name, $this->config['modules'][$key]);
				}
				else
					_log(L_WARNING, '%s: could not find %s', get_called_class(), $type);
			}
	}

	public function connect($sid)
	{
		// todo: implement this.
	}

	public function read($sid, $data)
	{
		$this->parse($data);
	}

	public function write($format)
	{
		if (strlen($format) == 0) return;

		$SH = SocketHandler::getInstance();

		$args = func_get_args();
		array_unshift($args, $this->sid);

		call_user_func_array(array($SH, 'send'), $args);
	}

	abstract function parse($data);
	abstract function _create($name, $config);
	abstract function _destroy();
}
