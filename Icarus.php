<?php
/************************************************************
 * icarus v0.1-alpha -- an IRC framework for PHP            *
 * Author: rintaun - Matthew J. Lanigan <rintaun@gmail.com> *
 *                                                          *
 * Copyright 2011 Matthew J. Lanigan.                       *
 * See LICENSE file for licensing restrictions              *
 ************************************************************/

declare(ticks=1);
ini_set('memory_limit', '16M');

define('_ICARUS_', TRUE);

require_once("inc/includes.php");

final class Icarus extends Singleton
{
	private $running = FALSE;

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

		if ((isset($c->config['debugmode'])) && ($c->config['debugmode'] == "true"))
			$l->debug(TRUE);
		else
			$l->debug(FALSE);

		// we have clients?
		if (is_array($c->config['client']))
			foreach ($c->config['client'] AS $key => $entry)
			{
				$keyinfo = explode(":", $key);

				$type = 'Client_' . $keyinfo[0];
				$name = $keyinfo[1];

				if (file_exists('clients/' . $type . '.php'))
				{
					require_once('clients/' . $type . '.php');
					new $type($name, $c->config['client'][$key]);
				}
				else
					_log(L_WARNING, 'Loader: could not find %s', $type);
			} 


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
