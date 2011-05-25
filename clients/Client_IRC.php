<?php
/************************************************************
 * icarus v0.1-alpha -- an IRC framework for PHP            *
 * Author: rintaun - Matthew J. Lanigan <rintaun@gmail.com> *
 *                                                          *
 * Copyright 2011 Matthew J. Lanigan.                       *
 * See LICENSE file for licensing restrictions              *
 ************************************************************/

if (!defined('_ICARUS_')) die('This script may not be invoked directly.' . "\n");

class Client_IRC extends Client{
	private function _create()
	{
	}

	private function _destroy()
	{
	}

	private function parse ($data)
	{
		$irc = "/^(?:\:(\S+)\s+)?(\w+)(?:\s+(?!:)(.*?))?(?:\s+:(.+))?$/";
		preg_match($irc, $data, $matches);
		$return = array(
			'origin' => (isset($matches[1])) ? $matches[1] : "",
			'command' => $matches[2],
			'params' => (isset($matches[3])) ? explode(" ",$matches[3]) : array(),
		);
		if (isset($matches[4]))
		{	
			$return['params'][] = $matches[4];
			$return['freeform'] = TRUE;
		}
		return $return;
	}
}
