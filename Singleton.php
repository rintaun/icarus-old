<?php
/************************************************************
 * icarus v0.1-alpha -- an IRC framework for PHP            *
 * Author: rintaun - Matthew J. Lanigan <rintaun@gmail.com> *
 *                                                          *
 * Copyright 2011 Matthew J. Lanigan.                       *
 * See LICENSE file for licensing restrictions              *
 ************************************************************/

if (!defined('_BOUNCE_')) die('This script may not be invoked directly.');

abstract class Singleton
{
	protected static $_instances;

	abstract protected function __construct();
	abstract protected function _destroy();

	final public static function getInstance()
	{
		$c = get_called_class();
		if (!isset(self::$_instances[$c]))
		{
			self::$_instances[$c] = new $c;
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
