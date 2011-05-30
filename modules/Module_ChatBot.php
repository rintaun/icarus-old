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
	private $db;

	public function _create($name, $config)
	{
		$this->parent->eventAdd('privmsg', array($this, 'recvmsg'), 10);
		$this->parent->eventAdd('privmsgme', array($this, 'recvmsg'), 10);

		
	}

	public function recvmsg($origin, $params)
	{
		if (substr($params[0],0,1) == "#") $target = $params[0];
		else $target = $origin['nick'];

		$this->learn($params[1]);
		$this->parent->privmsg($target, $this->generate(strtok($params[1], " "));
	}

	public function learn($text, $n=6)
	{
	}

	public function generate($begin="")
	{
	}

	public function _destroy()
	{
	}
}
