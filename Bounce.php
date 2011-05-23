<?php

/************************************************************
 * Bounce v0.1-alpha                                        *
 * Author: rintaun - Matthew J. Lanigan <rintaun@gmail.com> *
 *                                                          *
 * Copyright 2011 Matthew J. Lanigan.                       *
 * See LICENSE file for licensing details.                  *
 ************************************************************
 * src/Bounce.php                                           *
 *                                                          *
 * Description: Bounce core                                 *
 ************************************************************/

ini_set('memory_limit', '512M');

if (!defined('_BOUNCE_')) die('This script may not be invoked directly.');

require_once("Singleton.php");
require_once("Configurator.php");
require_once("Logger.php");
require_once("SocketHandler.php");
require_once("Server.php");

final class Bounce extends Singleton
{

	protected function __construct()
	{
		Configurator::getInstance();
	}

	public function start()
	{
		$SH = SocketHandler::getInstance();
		$BS = Server::getInstance();
		$SH->loop();
	}

	public function end()
	{
		$SH = SocketHandler::getInstance();
		$SH->interrupt();
	}

	protected function _destroy()
	{
	}
}

function _exit()
{
	_log(L_INFO, "Shutting down...");
	$bounce = Bounce::getInstance();
	$bounce->end();
}
