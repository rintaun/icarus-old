<?php
/************************************************************
 * icarus v0.1-alpha -- an IRC framework for PHP            *
 * Author: rintaun - Matthew J. Lanigan <rintaun@gmail.com> *
 *                                                          *
 * Copyright 2011 Matthew J. Lanigan.                       *
 * See LICENSE file for licensing restrictions              *
 ************************************************************/

if (!defined('_ICARUS_')) die('This script may not be invoked directly.' . "\n");

abstract class Module extends EventHandler {
	private $sid = "";
	protected $parent;

	final public function __construct($parent, $name, $config)
	{
		$this->parent = $parent;

		$this->_create($name, $config);
	}

	final public function __destruct()
	{
		$this->_destroy();
	}

	abstract function _create($name, $config);
	abstract function _destroy();
}
