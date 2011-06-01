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

		$this->parent->eventAdd('privmsg', array($this, 'recvmsg'), 10);
		$this->parent->eventAdd('privmsgme', array($this, 'recvmsg'), 10);

		$this->db = new SQLite3($GLOBALS['vardir'] . 'Module_ChatBot_' . $this->name);

		$this->db->exec('CREATE TABLE IF NOT EXISTS grams (
					id INTEGER PRIMARY KEY,
					gram TEXT NOT NULL UNIQUE)');
		$this->db->exec('CREATE TABLE IF NOT EXISTS nodes (
					left INTEGER,
					right INTEGER,
					uses INTEGER NOT NULL DEFAULT 1,
					PRIMARY KEY (left, right),
					FOREIGN KEY (left)  REFERENCES grams (id) ON DELETE CASCADE,
					FOREIGN KEY (right) REFERENCES grams (id) ON DELETE CASCADE)');
		$this->db->exec('INSERT OR IGNORE INTO grams VALUES
					(-2, "<start>")');
		$this->db->exec('INSERT OR IGNORE INTO grams VALUES
					(-1, "<end>")');
	}

	public function recvmsg($origin, $params)
	{
		if (substr($params[0],0,1) == "#") $target = $params[0];
		else $target = $origin['nick'];

		$this->learn($params[1]);

		$response = $this->generateResponse($params[1]);
		if ($response == $params[1]) return;

		$this->parent->privmsg($target, $response);
	}

	public function learn($text, $n=6)
	{
		$text = preg_replace("/([,.!?:;()\\/])/", " \\1 ", $text);
		$text = str_replace("  ", " ", $text);

		$text = explode(" ", $this->db->escapeString($text));
		$end = FALSE;

		foreach ($text AS $key => $word)
		{
			if (empty($word)) continue;

			$gram = $word;
			for ($i = 1; $i < $n; $i++)
			{
				if (!isset($text[$key + $i]))
				{
					$end = TRUE;
					break(2);
				}
				$gram .= " " . $text[$key + $i];
			}

			$this->db->exec('INSERT OR IGNORE INTO grams VALUES (NULL, "' . $gram . '")');
			$result = $this->db->query('SELECT * FROM grams WHERE gram="' . $gram . '"');
			$id = $result->fetchArray(SQLITE3_ASSOC);
			$id = $id['id'];

			if ($key == 0) $right = CB_MARKOV_START;
			else $right = $lastID;

			$node = @$this->db->exec('INSERT INTO nodes VALUES (' . $right . ', ' . $id . ', 1)');
			if ($node === FALSE)
			{
				$query = 'UPDATE nodes SET uses=uses+1 WHERE left="' . $right . '" AND right="' . $id . '"';
				var_dump($query);
				$this->db->exec($query);
			}
			$lastID = $id;
		}

		$node = @$this->db->exec('INSERT INTO nodes VALUES (' . $lastID . ', ' . CB_MARKOV_END . ', 1)');
		if ($node === FALSE)
			$this->db->exec('UPDATE nodes SET uses=uses+1 WHERE left="' . $lastID . '" AND right="' . CB_MARKOV_END . '"');
	}

	public function generateResponse($text)
	{
		_log(L_DEBUG3, "Module_ChatBot->generateResponse(): Input: %s", $text);
		$text = preg_replace("/([,.!?:;()\\/])/", " \\1 ", $text);
		$text = str_replace("  ", " ", $text);

		$words = explode(" ", $text);

		foreach ($words AS $key => $value)
			if (strlen($value) < 5) unset($words[$key]);

		$base = $words[array_rand($words)];
		_log(L_DEBUG3, "Module_ChatBot->generateResponse(): Base: %s", $base);

		$result = $this->db->query('SELECT * FROM grams WHERE gram LIKE "% ' . $base . ' %"
						OR gram LIKE "' . $base . ' %"
						OR gram LIKE "% ' . $base . '"');
		while ($row = $result->fetchArray())
			if (!empty($row)) $rows[] = $row;

		$respbase = $rows[array_rand($rows)];
		_log(L_DEBUG3, "Module_ChatBot->generateResponse(): Response Base: %d: %s", $respbase['id'], $respbase['gram']);

		$allnodes = $this->getNodes($respbase['id']);

		$node = $allnodes[array_rand($allnodes)];
		_log(L_DEBUG3, "Module_ChatBot->generateResponse(): Base Node: %d", $node['id']);

		$id = $node['id'];
		$bid = $id;

		$nodes = array($node);

		while ($id != CB_MARKOV_START)
		{
			_log(L_DEBUG3, "Module_ChatBot->generateResponse(): Moving to Left: id: %d", $id);
			$leftnodes = $this->getNodesLeftOf($id);
			if (empty($leftnodes)) _die("Module_ChatBot->generateResponse(); Got empty leftnodes: %d", $id);
			$node = $leftnodes[array_rand($leftnodes)];
			$id = $node['left'];
			array_unshift($nodes, $node);
		}

		$id = $bid;
		while ($id != CB_MARKOV_END)
		{
			_log(L_DEBUG3, "Module_ChatBot->generateResponse(): Moving to Right: id: %d", $id);
			$rightnodes = $this->getNodesRightOf($id);
			$node = $rightnodes[array_rand($rightnodes)];
			$id = $node['right'];
			array_push($nodes, $node);
		}

		foreach ($nodes AS $entry)
		{
			if ($entry['left'] == CB_MARKOV_START) continue;

			$result = $this->db->query('SELECT * FROM grams WHERE id="' . $entry['left'] . '"');
			$row = $result->fetchArray();

			if ($entry['right'] == CB_MARKOV_END)
				$response[] = $row['gram'];
			else
				$response[] = strtok($row['gram'], " ");
		}

		$response = implode(" ", $response);
		$response = preg_replace("/\s([,.!?:;)\\/])/", "\\1", $response);
		$response = str_replace(" ( ", " (", $response);

		_log(L_DEBUG3, "Module_ChatBot->generateResponse(): Response: %s", $response);

		return trim($response);
	}

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
