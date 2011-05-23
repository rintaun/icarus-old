<?php
/************************************************************
 * icarus v0.1-alpha -- an IRC framework for PHP            *
 * Author: rintaun - Matthew J. Lanigan <rintaun@gmail.com> *
 *                                                          *
 * Copyright 2011 Matthew J. Lanigan.                       *
 * See LICENSE file for licensing restrictions              *
 ************************************************************/

class IRC
{
	// IRC::parse($data)
	// params:
	// - $data -- an irc command
	//
	// returns an array with four entries
	// - origin -- if not included in the command, this is empty. e.g. "rakaur!rakaur@malkier.net"
	// - command -- this is always present; returned after strtolower. e.g. "kick"
	// - params -- an array, empty if no params are present. e.g. ["#bottestlab", "dKingston"]
	// - freeform -- the freeform parameter if included in the command. e.g. "cuz stuff"
	//
	// the previous examples would be generated from the following irc command:
	// :rakaur!rakaur@malkier.net KICK #bottestlab dKingston :cuz stuff

	static function Parse ($data)
	{
		$irc = "/^(?:\:(\S+)\s+)(\w+)(?:\s+(?!:)(.*?))?(?:\s+:(.+))?$/";
		preg_match($irc, $data, $matches);
		$return = array(
			'origin' => (isset($data[1])) ? $data[1] : "",
			'command' => $data[2],
			'params' => (isset($data[3])) ? explode(" ",$data[3]) : array(),
		);
		if (isset($data[4]))
		{
			$return['params'][] = $data[4];
			$return['freeform'] = TRUE;
		}
		return $return;
	}
}
