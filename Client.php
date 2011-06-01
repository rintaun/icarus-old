<?php
/************************************************************
 * icarus v1.0-beta -- an IRC framework for PHP            *
 * Author: rintaun - Matthew J. Lanigan <rintaun@gmail.com> *
 *                                                          *
 * Copyright 2011 Matthew J. Lanigan.                       *
 * See LICENSE file for licensing restrictions              *
 ************************************************************/

if (!defined('_ICARUS_')) die('This script may not be invoked directly.' . "\n");

abstract class Client extends EventHandler {
	private $sid = "";
	private $config;
	public $modules = array();

	final public function __construct($name, $config)
	{
		$this->config = $config;

		if ((!isset($config['server'])) || (!isset($config['port'])))
			_die("Client %s: Didnt get a server or port value!", $name);

		$SH = SocketHandler::getInstance();
		$this->sid = $SH->createSocket($config['server'], $config['port'], $this);

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
				$name = (isset($keyinfo[1])) ? $keyinfo[1] : "";

				if (file_exists($GLOBALS['modulesdir'] . $type . '.php'))
				{
					require_once($GLOBALS['modulesdir'] . $type . '.php');
					$this->modules[] = new $type($this, $name, $this->config['module'][$key]);
				}
				else
					_log(L_WARNING, '%s: could not find %s', get_called_class(), $type);
			}
	}

	public function read($sid, $data)
	{
		// $sid is, admittedly, pretty irrelevant at the moment,
		// but it could come in handy later.

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
