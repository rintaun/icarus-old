<?php
/************************************************************
 * icarus v1.0-beta -- an IRC framework for PHP            *
 * Author: rintaun - Matthew J. Lanigan <rintaun@gmail.com> *
 *                                                          *
 * Copyright 2011 Matthew J. Lanigan.                       *
 * See LICENSE file for licensing restrictions              *
 ************************************************************/

declare(ticks=1);
ini_set('memory_limit', '16M');

define('_ICARUS_', TRUE);

$location = __FILE__;

require_once("inc/includes.php");

final class Icarus extends Singleton
{
	private $running = FALSE;
	private $servers = array();
	public $clients = array();

	protected function _create()
	{
		// as soon as we start, we want to initialize Configurator
		// and parse the config file. MUST HAVE CONFIGS
		Configurator::getInstance();
	}

	public function start()
	{
		$c = Configurator::getInstance();
		$l = Logger::getInstance();

		if (isset($c->config['logfile']))
			$l->setLogfile($c->config['logfile']);

		if ((!isset($c->config['debugmode'])) || ($c->config['debugmode'] == "false"))
			$l->debug(FALSE);
		elseif (is_numeric($c->config['debugmode']))
			$l->debug($c->config['debugmode']);
		elseif ($c->config['debugmode'] == "true")
			$l->debug(3);
		else
			$l->debug(FALSE);

		if (isset($c->config['die']))
			_die("Icarus: read the config file!");

		$this->loadClients();
		$this->loadServers();

		$this->running = TRUE;

		$SH = SocketHandler::getInstance();
		$SH->loop();
	}

	private function loadClients()
	{
		$c = Configurator::getInstance();

		if ((isset($c->config['client'])) && (is_array($c->config['client'])))
			foreach ($c->config['client'] AS $key => $entry)
			{
				$keyinfo = explode(":", $key);

				$type = 'Client_' . $keyinfo[0];
				$name = (isset($keyinfo[1])) ? $keyinfo[1] : "";

				if (file_exists($GLOBALS['clientsdir'] . $type . '.php'))
				{
					require_once($GLOBALS['clientsdir'] . $type . '.php');
					new $type($name, $c->config['client'][$key]);
				}
				else
					_log(L_WARNING, 'Loader: could not find %s', $type);
			} 
	}

	private function loadServers()
	{
		$c = Configurator::getInstance();

		if ((isset($c->config['server'])) && (is_array($c->config['server'])))
			foreach ($c->config['server'] AS $key => $entry)
			{
				$keyinfo = explode(":", $key);

				$type = 'Server_' . $keyinfo[0];
				$name = (isset($keyinfo[1])) ? $keyinfo[1] : "";

				if (file_exists($GLOBALS['serversdir'] . $type . '.php'))
				{
					require_once($GLOBALS['serversdir'] . $type . '.php');
					$server = new $type($name, $c->config['client'][$key]);
				}
				else
					_log(L_WARNING, 'Loader: could not find %s', $type);
			}

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
