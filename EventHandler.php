<?php
/************************************************************
 * icarus v0.1-alpha -- an IRC framework for PHP            *
 * Author: rintaun - Matthew J. Lanigan <rintaun@gmail.com> *
 *                                                          *
 * Copyright 2011 Matthew J. Lanigan.                       *
 * See LICENSE file for licensing restrictions              *
 ************************************************************
 * Portions adapted or inspired by from Praxis IRC Services *
 *  Copyright (c) 2004 Eric Will <rakaur@malkier.net>       *
 *  Copyright (c) 2003-2004 shrike development team.        *
 ************************************************************/

//if (!defined('_ICARUS_')) die('This script may not be invoked directly.' . "\n");

abstract class EventHandler
{
	private $eventheap = array();

	public function eventAdd($event, $callback, $priority=0)
	{
		$eid = uniqid('e');

		$this->eventheap[$event][$eid] = array(
			'priority' => $priority,
			'callback' => $callback
		);

		_log(L_DEBUG, "%s->eventAdd(): (%s) Adding new callback for event %s at %d priority", get_called_class(), $eid, $event, $priority);

		return $eid;
	}

	public function eventDelete($event, $callback)
	{
		foreach ($this->eventheap[$event] AS $eid => $entry)
		{
			if ($callback == $entry['callback'])
			{
				unset($this->eventheap[$eid]);
				_log(L_DEBUG, "%s->eventDelete(): Deleted eventid %s", get_called_class(), $eif);
				return;
			}
		}
		_log(L_DEBUG, "%s->eventDelete(): Unable to delete eventid %s: not found", get_called_class(), $eid);
	}

	public function eventDeleteID($eid)
	{
		if (isset($this->eventheap[$eid]))
		{
			unset($this->eventheap[$eid]);
			_log(L_DEBUG, "%s->eventDeleteID(): Deleted eventid %s", get_called_class(), $eid);
			return;
		}
		_log(L_DEBUG, "%s->eventDeleteID(): Unable to delete eventid %s: not found", get_called_class(), $eid);
	}

	public function eventPost($event)
	{
		$args = func_get_args();
		array_shift($args);

		if (!isset($this->eventheap[$event]))
		{
			_log(L_DEBUG, "%s->eventPost(): No events registered for %s", get_called_class(), $event);
			return;
		}

		$this->eventSort($event);

		_log(L_DEBUG,"%s->eventPost(): Posting event %s", get_called_class(), $event);

		foreach ($this->eventheap[$event] AS $eid => $entry)
		{
			if ((call_user_func_array($entry['callback'], $args)) === FALSE) break;
		}
	}

	private function eventSort($event)
	{
		if (!isset($this->eventheap[$event]))
		{
			_log(L_DEBUG, "%s->eventSort(): No events registered for %s", get_called_class(), $event);
			return;
		}

		foreach ($this->eventheap[$event] AS $eid => $entry)
			if (!is_array($entry))
			{
				_log(L_WARNING, "%s->eventSort(): Removing invalid event from eventheap", get_called_class());
				unset($this->eventheap[$key]);
			}

		uasort($this->eventheap[$event], array($this, 'eventCmp'));
	}

	private function eventCmp($a, $b)
	{
		if ($a['priority'] == $b['priority'])
			return 0;
		return ($a['priority'] < $b['priority']) ? -1 : 1;
	}
}
