<?php

/************************************************************
 * Bounce v0.1-alpha                                        *
 * Author: rintaun - Matthew J. Lanigan <rintaun@gmail.com> *
 *                                                          *
 * Copyright 2011 Matthew J. Lanigan.                       *
 * See LICENSE file for licensing details.                  *
 ************************************************************
 * src/Configurator.php                                     *
 *                                                          *
 * Description: Configuration parser                        *
 ************************************************************/

if (!defined('_BOUNCE_')) die('This script may not be invoked directly.');

require_once("Singleton.php");

final class Configurator extends Singleton
{
	private $configfile = "";
	private $config = array( // define defaults
		'logfile' => 'bounce.log',
		'loglevel' => L_ALL,
	);

	private $fd = NULL;

	protected function __construct()
	{
		if (isset($GLOBALS['confoverride'])) $this->configfile = $GLOBALS['confoverride'];
		else $this->configfile = 'etc/bounce.conf';

		$this->parse();
	}

	public function parse()
	{
		$config = file_get_contents($this->configfile);
		$this->parse_section($config);

		// some variables, such as loglevel, may require special processing
		if (!is_int($this->config['loglevel']))
			$this->config['loglevel'] = eval("return ".$this->config['loglevel'].";");
	}

	private function parse_section($data, &$parent=NULL)
	{
		if ($parent == NULL) $parent = &$this->config;

		// first pull out all the sections
		$pattern = '/\<(([^>:]+):([^>]+))\>(.*?)(\<\/\\2\>)/s';
		preg_match_all($pattern, $data, $subs, PREG_SET_ORDER);
		
		// then if there are subsections, parse them and remove them from $data
		if (is_array($subs))
			foreach ($subs AS $sub)
				if (!empty($sub[0]))
				{
					$parent[trim($sub[1])] = array();
					$data = str_replace($sub[0], "", $data);
					$this->parse_section($sub[4], $parent[trim($sub[1])]);
				}

		$pattern = '/([^=]+)=([^;]+);/';
		preg_match_all($pattern, $data, $vars);

		foreach ($vars[1] AS $key => $var)
		{
			$parent[trim($var)] = trim($vars[2][$key]);
		}
	}

	public function rehash()
	{
	}

	private function save()
	{
	}

	public function __get($name)
	{
		if (isset($this->config[$name]))
			return $this->config[$name];
	}

	protected function _destroy()
	{
		$this->save();
	}	
}
