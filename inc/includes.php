<?php
/************************************************************
 * icarus v1.0-beta -- an IRC framework for PHP            *
 * Author: rintaun - Matthew J. Lanigan <rintaun@gmail.com> *
 *                                                          *
 * Copyright 2011 Matthew J. Lanigan.                       *
 * See LICENSE file for licensing restrictions              *
 ************************************************************/

if (!defined('_ICARUS_')) die('This script may not be invoked directly.' . "\n");

// yeah... I wanted to include them in alphabetical order,
// but meh, this one DEFINITELY needs to come first.
require_once("EventHandler.php");
require_once("Singleton.php");

require_once("Configurator.php");
require_once("Logger.php");
require_once("SocketHandler.php");

require_once("Client.php");
require_once("Server.php");
require_once("Module.php");

require_once("Icarus.php");



function _exit()
{
        _log(L_INFO, "Shutting down...");
        $icarus = Icarus::getInstance();
        if ($icarus->isRunning()) $icarus->end();
	exit;
}

function _die($format)
{
        $logger = Logger::getInstance();
        $args = func_get_args();
        array_shift($args);

        $logger->log(L_FATAL, "Fatal Error: ".$format, $args);
	exit;
}

function _log($level, $format)
{
        $logger = Logger::getInstance();
        $args = func_get_args();
        array_shift($args);
        array_shift($args);

        $logger->log($level, $format, $args);
}
