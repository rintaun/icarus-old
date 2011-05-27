<?php
/************************************************************
 * icarus v1.0-beta -- an IRC framework for PHP            *
 * Author: rintaun - Matthew J. Lanigan <rintaun@gmail.com> *
 *                                                          *
 * Copyright 2011 Matthew J. Lanigan.                       *
 * See LICENSE file for licensing restrictions              *
 ************************************************************/

class Module_ChatBot extends Module {
	private $prefix;

	public function _create($name, $config)
	{
		$this->parent->eventAdd('privmsg', array($this, 'recvmsg'), 10);
		$this->parent->eventAdd('privmsgme', array($this, 'recvmsg'), 10);
	}

	public function recvmsg($origin, $params)
	{
	}

	public function _destroy()
	{
	}
}
