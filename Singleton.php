<?php
/************************************************************
 * icarus v0.1-alpha -- an IRC framework for PHP            *
 * Author: rintaun - Matthew J. Lanigan <rintaun@gmail.com> *
 *                                                          *
 * Copyright 2011 Matthew J. Lanigan.                       *
 * See LICENSE file for licensing restrictions              *
 ************************************************************/

if (!defined('_ICARUS_')) die('This script may not be invoked directly.' . "\n");

abstract class Singleton
{
	protected static $_instances;

	final protected function __construct() {}

	abstract protected function _create();
	abstract protected function _destroy();

	final public static function getInstance()
	{
		$c = get_called_class();
		if (!isset(self::$_instances[$c]))
		{
			self::$_instances[$c] = new $c;
			call_user_func_array(array(self::$_instances[$c], "_create"), func_get_args());
		}
		return self::$_instances[$c];
	}

	final public function __destruct()
	{
		$c = get_called_class();
		$this->_destroy();
		if (isset(self::$_instances[$c]))
			unset(self::$_instances[$c]);
	}

	final protected function __clone() { }
}
