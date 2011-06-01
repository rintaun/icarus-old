<?php
/************************************************************
 * icarus v1.0-beta -- an IRC framework for PHP            *
 * Author: rintaun - Matthew J. Lanigan <rintaun@gmail.com> *
 *                                                          *
 * Copyright 2011 Matthew J. Lanigan.                       *
 * See LICENSE file for licensing restrictions              *
 ************************************************************/

if (!defined('_ICARUS_')) die('This script may not be invoked directly.' . "\n");

$GLOBALS['version'] = "1.0-beta";
$GLOBALS['fork'] = FALSE;


_log(L_INFO, 'Icarus v%s starting...', $GLOBALS['version']);

function signal_handler ($signo)
{
	switch ($signo)
	{
		case SIGINT:
			_log(L_INFO, "Received SIGINT. Stopping...");
			$SH = SocketHandler::getInstance();
			$SH->interrupt();
			exit();
			break;
		case SIGTERM:
			_log(L_INFO, "Received SIGTERM. Stopping...");
			$SH = SocketHandler::getInstance();
			$SH->interrupt();
			exit();
			break;
		case SIGHUP:
			_log(L_INFO, "Received SIGHUP. Rehashing.");
			$config = Configurator::getInstance();
			$config->rehash();
			break;
		default:
	}
}

pcntl_signal(SIGINT, 'signal_handler');
pcntl_signal(SIGTERM, 'signal_handler');
pcntl_signal(SIGHUP, 'signal_handler');

register_shutdown_function('_exit');

$ARGS = getopt('dc:l:f');
if (is_array($ARGS))
{
        foreach($ARGS AS $arg => $value)
        {
                switch($arg)
                {
			case 'd':
				_log(L_INFO, 'Starting in debug mode.');
				$GLOBALS['debug'] = true;
				break;
			case 'c':
				_log(L_INFO, 'Using configuration file %s.', $value);
				$GLOBALS['configfile'] = $value;
				break;
			case 'l':
				_log(L_INFO, 'Using log file %s.', $value);
				$GLOBALS['logfile'] = $value;
				break;
                        case 'f':
				$GLOBALS['fork'] = true;
				break;
                }
	}
}

if ($GLOBALS['fork'] === true)
{
	_log(L_DEBUG, 'Forking into the background...');
	$pid = pcntl_fork();
	if ($pid == -1)
		_log(L_FATAL, 'Failed to fork into the background. Exiting.');
	else if ($pid)
	{
		_log(L_DEBUG, 'Forked successfully. Exiting parent.');
		exit;
	}
	else
	{
		// we're in the child!
		$logger = Logger::getInstance();
		$logger->fork();
	}

}

$icarus = Icarus::getInstance();
$icarus->start();
