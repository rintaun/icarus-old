<?php
/************************************************************
 * icarus v0.1-alpha -- an IRC framework for PHP            *
 * Author: rintaun - Matthew J. Lanigan <rintaun@gmail.com> *
 *                                                          *
 * Copyright 2011 Matthew J. Lanigan.                       *
 * See LICENSE file for licensing restrictions              *
 ************************************************************/

if (!defined('_ICARUS_')) die('This script may not be invoked directly.' . "\n");

abstract class Client {
	private $sid = "";

	final public function __construct($address, $port)
	{
		$SH = SocketHandler::getInstance();
		$this->sid = $SH->createSocket($address, $port, $this);

		$this->_create();
	}

	final public function __destruct()
	{
		$this->_destroy();
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
	abstract function _create();
	abstract function _destroy();
}
