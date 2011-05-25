<?php
/************************************************************
 * icarus v0.1-alpha -- an IRC framework for PHP            *
 * Author: rintaun - Matthew J. Lanigan <rintaun@gmail.com> *
 *                                                          *
 * Copyright 2011 Matthew J. Lanigan.                       *
 * See LICENSE file for licensing restrictions              *
 ************************************************************/

if (!defined('_ICARUS_')) die('This script may not be invoked directly.' . "\n");

final class Configurator extends Singleton
{
	private $configfile = "";
	private $config = array();

	private $fd = NULL;

	protected function _create($filename = NULL)
	{
		$logger = Logger::getInstance(NULL);

		if (!is_null($filename)) $this->configfile = $filename;
		else $this->configfile = getcwd() . '/etc/icarus.conf';

		if (!file_exists($this->configfile)) _die("Fatal Error: Configuration file (%s) not found.", $this->configfile);

		$this->config = $this->parse($this->configfile);
	}

	/*  parse()
	 *  rewritten from the original!
	 *  Copyright (c) 2004 Matt Lanigan <rintaun@projectxero.net>
	 *  Copyright (c) 2004 Eric Will <rakaur@malkier.net>
	 *  Copyright (c) 2003-2004 shrike development team.
	 *  Copyright (c) 1999-2004 csircd development team.
	 */
	private function parse($filename)
	{
		$data = file_get_contents($filename);

		$linenumber = 1;
		$sectnum = 0;

		$curfile = array(
			'filename' => trim($filename)
		);

		for ($c=0; $c < strlen($data); $c++)
		{
			switch ($data{$c})
			{
				case '#':
					while (($data{$c} != "\n") && (isset($data{$c})))
						$c++;

					if (!isset($data{$c}))
					{
						$c--;
						continue;
					}
					break;
				case ';':
					if (empty($curentry))
					{
						$this->_warning('%s (line %d): Ignoring extra semicolon', $filename, $linenumber);
						break;
					}
					if (!strcmp($curentry['varname'], "include"))
					{
						if (empty($curentry['vardata']))
						{
							$this->_warning('%s (line %d): Ignoring "include": No filename given', $filename, $linenumber);
							unset($curentry);
							continue;
						}

						if (strlen($curentry['vardata']) > 255)
							$curentry['vardata'] = substr($curentry['vardata'], 0, 254);
						
						$include = $this->parse($curentry['vardata']);
						if (!$include)
							$this->_warning('%s (line %d): Unable to load "include" file: %s', $filename, $linenumber, $curentry['vardata']);
						else
						{
							if (!empty($cursection))
								$lastfile = &$cursection['entries'];
							else
								$lastfile = &$curfile;

							foreach ($include AS $name => $entry)
								$lastfile[] = $entry;
						}
						unset($curentry);
						continue;
					}
					
					$curentry['varname'] = trim($curentry['varname']);
					$curentry['vardata'] = trim($curentry['vardata']);
					$curentry['filename'] = $curfile['filename'];
					if (!isset($cursection))
						$curfile[] = $curentry;
					else
						$cursection['entries'][] = $curentry;

					$lastentry = &$curentry;
					unset($curentry);
					break;
				case '{':
					if (empty($curentry))
					{
						$this->_warning("%s (line %d): No name for section start", $filename, $linenumber);
						continue;
					}
					else if (isset($curentry['entries']))
					{
						$this->_warning("%s (line %d): Ignoring extra section start", $filename, $linenumber);
						continue;
					}

					if ((isset($cursection)) && ((!isset($cursection['sectnum'])) || (!isset($curentry['sectnum'])) ||
						($cursection['sectnum'] != $curentry['sectnum'])))
							$curentry['prevlev'] = $cursection;

					$curentry['sectlinenum'] = $linenumber;
					$cursection = $curentry;
					$cursection['sectnum'] = ++$sectnum;
					$cursection['entries'] = array();

					unset($curentry);
					break;
				case '}':
					if (isset($curentry))
					{
						$this->_error("%s (line %d): Missing semicolon before close brace", $filename, $linenumber);
						unset($curentry);
						unset($curfile);
						return NULL;
					}
					else if (empty($cursection))
					{
						$this->_warning("%s (line %d): Ignoring extra close brace", $filename, $linenumber);
						continue;
					}

					$curentry = $cursection;
					if (!empty($cursection['prevlev']))
						$cursection = $cursection['prevlev'];
					else
						unset($cursection);
					break;
				case '/':
					if ($data{$c+1} == '/')
					{
						$c += 2;
						while (($data{$c} != "\n") && (isset($data{$c})))
							$c++;

						if (!isset($data{$c}))
							$c--;

						continue;							
					}
					else if ($data{$c+1} == '*')
					{
						$commentstart = $linenumber;
						for ($c += 2; isset($data{$c}); $c++)
						{
							if (($data{$c} == '*') && ($data{$c+1} == '/'))
							{
								$c++;
								break;
							}
							else if ($data{$c} == "\n")
								$linenumber++;
						}

						if (!isset($data{$c}))
						{
							$this->_error("%s (line %d): Comment starting on this line does not end", $filename, $commentstart);
							unset($curentry);
							unset($curfile);
							return NULL;
						}
					}
					break;
				case '"':
					$start = ++$c;
					for (; (isset($data{$c})) && ($data{$c} != '"') && ($data{$c} != "\n"); $c++)
					{
						if ($data{$c} == "\\")
						{
							$c++;
							continue;
						}
					}

					if ((!isset($data{$c})) || ($data{$c} == "\n"))
					{
						$this->_error("%s (line %d): Unterminated quote found", $filename, $linenumber);
						unset($curentry);
						unset($curfile);
						return NULL;
					}

					if (isset($curentry))
					{
						if (!empty($curentry['vardata']))
							$curentry['vardata'] .= substr($data, $start, ($c - $start));
						else
							$curentry['vardata'] = substr($data, $start, ($c - $start));
					}
					else
					{
						$curentry = array();
						$curentry['varname'] = substr($data, $start, ($c - $start));
						$curentry['varlinenum'] = $linenumber;
					}
					break;
				case "\n":
					$linenumber++;
					break;
				case ' ':
				case "\t":
				case '=':
				case "\r":
					break;
				default:
					if (($data{$c} == '*') && ($data{$c+1} == '/'))
					{
						$this->_warning("%s (line %d): Ignoring extra end comment", $filename, $linenumber);
						$c += 2;
						continue;
					}

					$start = $c;
					for (; isset($data{$c}); $c++)
					{
						if (($data{$c} == "\n") || ($data{$c} == ';'))
							break;
						if ((empty($curentry['varname'])) && (($data{$c} == ' ') || ($data{$c} == '=')))
							break;
					}

					if (!isset($data{$c}))
					{
						if (!empty($curentry))
							$this->_error("%s (line %d): Unexpected EOF for variable", $filename, $linenumber);
						else if (!empty($cursection))
							$this->_error("%s (line %d): Unexpected EOF for section", $filename, $linenumber);
						else
							$this->_error("%s (line %d): Unexpected EOF", $filename, $linenumber);

						unset($curentry);
						unset($curfile);
						return NULL;
					}

					if (isset($curentry))
					{
						if (!empty($curentry['vardata']))
							$curentry['vardata'] .= substr($data, $start, ($c - $start));
						else
							$curentry['vardata'] = substr($data, $start, ($c - $start));
					}
					else
					{
						$curentry = array();
						$curentry['varname'] = substr($data, $start, ($c - $start));
						$curentry['varlinenum'] = $linenumber;
					}
					
					if (($data{$c} == ';') || ($data{$c} == "\n"))
						$c--;
					break;
			} /* switch */
			
		} /* for */

		if (!empty($curentry))
		{
			$this->_error("%s (line %d): Unexpected EOF for variable", $filename, $curentry['varlinenum']);
			unset($curentry);
			unset($curfile);
			return NULL;
		}
		else if (isset($cursection))
		{
			$this->_error("%s (line %d): Unexpected EOF for section", $filename, $curentry['sectlinenum']);
			unset($curentry);
			unset($curfile);
			return NULL;
		}
	
		return $this->_build($curfile);
	}

	private function _build($data)
	{
		$build = array();

		foreach ($data AS $key => $value)
		{
			if (in_array($key, array('filename', 'varlinenum', 'sectlinenum', 'sectnum'))) continue;

			if (!empty($value['varname']))
			{
				if (isset($value['entries']))
				{
					if (!empty($value['vardata']))
						$build[$value['varname']][$value['vardata']] = $this->_build($value['entries']);
					else
						$build[$value['varname']][] = $this->_build($value['entries']);
				}
				else if (!empty($value['vardata']))
					$build[$value['varname']] = $value['vardata'];
			}
		}

		return $build;
	}
	private function _warning($format)
	{
		$args = func_get_args();
		array_shift($args);

		_log(L_WARNING, "Configurator: %s", vsprintf($format, $args));
	}

	private function _error($format)
	{
		$args = func_get_args();
		array_shift($args);

		_log(L_ERROR, "Configurator: %s", vsprintf($format, $args));
	}

	public function rehash()
	{
		_log(L_INFO, "Rehashing %s", $this->configfile);
		$this->config = $this->parse($this->configfile);
	}

	private function save()
	{
		_log(L_DEBUG, "Configurator->save(): not implemented yet");
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
