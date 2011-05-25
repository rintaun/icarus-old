<?php
/************************************************************
 * icarus v0.1-alpha -- an IRC framework for PHP            *
 * Author: rintaun - Matthew J. Lanigan <rintaun@gmail.com> *
 *                                                          *
 * Copyright 2011 Matthew J. Lanigan.                       *
 * See LICENSE file for licensing restrictions              *
 ************************************************************/

ini_set('memory_limit', '512M');

define('_ICARUS_', TRUE);

require_once("inc/includes.php");

final class Icarus extends Singleton
{
	private $running = FALSE;

	protected function _create()
	{
		Configurator::getInstance();
	}

	public function start()
	{
		$this->running = TRUE;

		$SH = SocketHandler::getInstance();
		$SH->loop();
	}

	public function end()
	{
		$SH = SocketHandler::getInstance();
		$SH->interrupt();

		$this->running = FALSE;
	}

	public function isRunning()
	{
		return $this->running;
	}
	
	protected function _destroy()
	{
	}
}

// and now we need the core program, heh!

require_once("inc/core.php");
