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

	public function respmsg($origin, $target, $params)
	{
		if (substr($target,0,1) != "#") $target = $origin['nick'];

		$this->learn($params);
		$this->parent->privmsg($target, $this->generateResponse($params));
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

	private function buildRightQuery(array $base, $level=0)
	{
		$query = sprintf('SELECT l%d.left AS l%dleft, l%d.right AS l%dright, l%d.uses AS l%duses, * FROM nodes l%d',
					$level, $level, $level, $level, $level, $level, $level);		

		$gram = array_shift($base);
		if ((is_array($base)) && (!empty($base)))
		{
			$query .= sprintf(' JOIN (%s) ON l%dright=l%dleft', $this->buildRightQuery($base, ($level+1)), $level, ($level+1));
		}

		$query .= sprintf(' WHERE l%dleft="%d"', $level, $this->getGramID($gram));
		return $query;
	}

	public function getRightTable($base)
	{
		$query = $this->buildRightQuery($base);
		//_log(L_DEBUG3, "Module_ChatBot->getRightTable(): Generated query: %s", $query);

		$order = count($base) - 1;

		$result = $this->db->query($query);
		while ($row = $result->fetchArray())
		{
			$table[$row["l{$order}right"]] = 1;
		}
		return $table;
	}

	private function buildLeftQuery(array $base, $level=0)
	{
		$query = sprintf('SELECT l%d.left AS l%dleft, l%d.right AS l%dright, l%d.uses AS l%duses, * FROM nodes l%d',
					$level, $level, $level, $level, $level, $level, $level);		

		$gram = array_shift($base);
		if ((is_array($base)) && (!empty($base)))
		{
			$query .= sprintf(' JOIN (%s) ON l%dright=l%dleft', $this->buildRightQuery($base, ($level+1)), $level, ($level+1));
		}

		$query .= sprintf(' WHERE l%dright="%d"', $level, $this->getGramID($gram));
		return $query;
	}

	public function getLeftTable($base)
	{
		$query = $this->buildLeftQuery($base);
		//_log(L_DEBUG3, "Module_ChatBot->getLeftTable(): Generated query: %s", $query);

		$order = count($base) - 1;

		$result = $this->db->query($query);
		while ($row = $result->fetchArray())
		{
			$table[$row["l{$order}left"]] = 1;
		}
		return $table;
	}

	public function getNodes($gram)
	{
		$gid = $this->getGramID($gram);

		$query = 'SELECT * FROM nodes WHERE left="' . $gid . '" OR right = "' . $gid . '"';
		$result = $this->db->query($query);
		while ($row = $result->fetchArray())
			$nodes[] = $row;

		return $nodes;
	}

	public function getGram($gid)
	{
		$query = 'SELECT gram FROM grams WHERE id="' . $gid . '"';
		$result = $this->db->query($query);
		$row = $result->fetchArray();
		return $row['gram'];
	}

	public function getGramID($gram)
	{
		$query = 'SELECT id FROM grams WHERE gram="' . $gram . '"';
		$result = $this->db->query($query);
		$row = $result->fetchArray();
		return $row['id'];
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

		while (1)
		{
			$table = $this->getLeftTable($base);
			$next = array_rand($table);
			if ($next == CB_MARKOV_START) break;

			$gram = $this->getGram($next);
			array_unshift($grams, $gram);
			array_unshift($base, $gram);

			if (count($base) > $order) array_pop($base);
		}

		$base = $bak;
		while (1)
		{
			$table = $this->getRightTable($base);
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
