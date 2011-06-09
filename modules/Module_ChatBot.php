<?php
/************************************************************
 * icarus v1.0-beta -- an IRC framework for PHP            *
 * Author: rintaun - Matthew J. Lanigan <rintaun@gmail.com> *
 *                                                          *
 * Copyright 2011 Matthew J. Lanigan.                       *
 * See LICENSE file for licensing restrictions              *
 ************************************************************/

define('CB_MARKOV_START', -2);
define('CB_MARKOV_END', -1);

class Module_ChatBot extends Module {
	private $prefix;
	private $db;

	public function _create($name, $config)
	{
		$this->name = $name;

		$this->parent->eventAdd('chatbot', array($this, 'respmsg'), 10);
		$this->parent->eventAdd('privmsg', array($this, 'recvmsg'), 10);
		$this->parent->eventAdd('privmsgme', array($this, 'recvmsg'), 10);

		$this->db = new SQLite3($GLOBALS['vardir'] . 'Module_ChatBot_' . $this->name);

		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS grams ('	. "\n" .
			'  id INTEGER PRIMARY KEY,'		. "\n" .
			'  order INTEGER DEFAULT 1,'		. "\n" .
			'  gram TEXT NOT NULL'			. "\n" .
			');'
		);
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS nodes ('	. "\n" .
			'  gram_id INTEGER PRIMARY KEY,'	. "\n" .
			'  prev TEXT NOT NULL,'			. "\n" .
			'  next TEXT NOT NULL'			. "\n" .
			');'
		);
	}

	public function respmsg($origin, $target, $params)
	{
		if (substr($target,0,1) != "#") $target = $origin['nick'];

		$response = $this->generateResponse($params, 5);
		if ($response === FALSE) return;
		$this->parent->privmsg($target, $response);
	}

	public function recvmsg($origin, $params)
	{
		if (substr($params[0],0,1) == "#") $target = $params[0];
		else $target = $origin['nick'];

		$this->learn($params[1]);
	}

	public function gramSplit($text)
	{
		$text = preg_replace("/([,.!?:;()\\/])/", " \\1 ", $text);
		$text = str_replace("  ", " ", $text);

		$text = explode(" ", $this->db->escapeString($text));

		return $text;
	}

	public function learn($text, $n=6)
	{
		$text = $this->gramSplit($text);

		foreach ($text AS $key => $gram)
		{
			if (empty($gram)) continue;

			$this->db->exec('INSERT OR IGNORE INTO grams VALUES (NULL, "' . $gram . '")');
			$result = $this->db->query('SELECT * FROM grams WHERE gram="' . $gram . '"');
			$id = $result->fetchArray(SQLITE3_ASSOC);
			$id = $id['id'];

			if ($key == 0) $left = CB_MARKOV_START;
			else $left = $lastID;

			$node = @$this->db->exec('INSERT INTO nodes VALUES (' . $left . ', ' . $id . ', 1)');
			if ($node === FALSE)
			{
				$query = 'UPDATE nodes SET uses=uses+1 WHERE left="' . $left . '" AND right="' . $id . '"';
				$this->db->exec($query);
			}
			$lastID = $id;
		}

		$node = @$this->db->exec('INSERT INTO nodes VALUES (' . $lastID . ', ' . CB_MARKOV_END . ', 1)');
		if ($node === FALSE)
			$this->db->exec('UPDATE nodes SET uses=uses+1 WHERE left="' . $lastID . '" AND right="' . CB_MARKOV_END . '"');
	}

	public function getGID($gram)
	{
		$query = "SELECT id FROM grams WHERE gram='{$gram}'";
		$result = $this->db->exec($query);

		if (!($result === FALSE))
		{
			$row = $result->fetchArray();
			return $row['id'];
		}

		return FALSE;
	}

	public function getNext($gram, $n=NULL)
	{
		if (!is_numeric($gram)) $gram = $this->getGID($gram);
		if ($gram === FALSE) return FALSE;

		$query = "SELECT next FROM nodes WHERE gram_id='{$gram}'";
		$result = $this->db->exec($query);

		if (!($result === FALSE))
		{
			while ($row = $result->fetchArray())
				$rows[] = $row;
			if ($n === NULL) $n = array_rand($rows);
			return $rows[$n]['next'];
		}

		return FALSE;
	}

	public function getPrev($gram, $n=NULL)
	{
		if (!is_numeric($gram)) $gram = $this->getGID($gram);
		if ($gram === FALSE) return FALSE;

		$query = "SELECT prev FROM nodes WHERE gram_id='{$gram}'";
		$result = $this->db->exec($query);

		if (!($result === FALSE))
		{
			while ($row = $result->fetchArray())
				$rows[] = $row;
			if ($n === NULL) $n = array_rand($rows);
			return $rows[$n]['prev'];
		}

		return FALSE;
	}

	public function generateResponse($text, $order=1)
	{
		_log(L_DEBUG3, "Module_ChatBot->generateResponse(): Input: %s", $text);

		$grams = $this->gramSplit($text);

		foreach ($grams AS $key => $value)
			if (strlen($value) < 5) unset($grams[$key]);

		$base = array($grams[array_rand($grams)]);
		$bak = $base;
		$grams = $base;

		$tries = 0;

		while (1)
		{
			$table = $this->getLeftTable($base);
			if (empty($table))
			{
				array_shift($grams);
				array_shift($base);
				if (++$tries > 10) return FALSE;
				continue;
			}
			$next = array_rand($table);
			if ($next == CB_MARKOV_START) break;

			$gram = $this->getGram($next);
			array_unshift($grams, $gram);
			array_unshift($base, $gram);

			if (count($base) > $order) array_pop($base);
		}

		$tries = 0;
		$base = $bak;
		while (1)
		{
			$table = $this->getRightTable($base);
			if (empty($table))
			{
				array_pop($grams);
				array_pop($base);
				if (++$tries > 10) return FALSE;
				continue;
			}
			$next = array_rand($table);
			if ($next == CB_MARKOV_END) break;

			$gram = $this->getGram($next);
			array_push($grams, $gram);
			array_push($base, $gram);

			if (count($base) > $order) array_shift($base);
		}

		$response = implode(" ", $grams);
		$response = preg_replace("/\s([,.!?:;)\\/])/", "\\1", $response);
		$response = str_replace(" ( ", " (", $response);

		_log(L_DEBUG3, "Module_ChatBot->generateResponse(): Response: %s", $response);

		return trim($response);
	}

/*
	public function getNodes($id)
	{
		$rows = array();
		$query = 'SELECT * FROM nodes WHERE left="' . $id . '" OR right= "' . $id . '"';
		$result = $this->db->query($query);
		while ($row = $result->fetchArray())
		{
			if ($row['left'] == $id) $row['id'] = $row['left'];
			elseif ($row['right'] == $id) $row['id'] = $row['right'];
			if (!empty($row)) $rows[] = $row;
		}
		return $rows;
	}
*/

	public function getNodesRightOf($id)
	{
		$rows = array();
		$result = $this->db->query('SELECT * FROM nodes WHERE left="' . $id . '"');
		while ($row = $result->fetchArray())
			if (!empty($row)) $rows[] = $row;
		return $rows;
	}

	public function getNodesLeftOf($id)
	{
		$rows = array();
		$result = $this->db->query('SELECT * FROM nodes WHERE right= "' . $id . '"');
		while ($row = $result->fetchArray())
			if (!empty($row)) $rows[] = $row;
		return $rows;
	}

	public function generateRandom()
	{
	}

	public function _destroy()
	{
	}

	public function selectgrams()
	{
		$result = $this->db->query('SELECT * FROM grams');
		while ($row = $result->fetchArray())
			$rows[] = $row;

		return $rows;
	}

	public function selectnodes()
	{
		$result = $this->db->query('SELECT * FROM nodes');
		while ($row = $result->fetchArray())
			$rows[] = $row;

		return $rows;
	}
}

