<?php
/************************************************************
 * icarus v0.1-alpha -- an IRC framework for PHP            *
 * Author: rintaun - Matthew J. Lanigan <rintaun@gmail.com> *
 *                                                          *
 * Copyright 2011 Matthew J. Lanigan.                       *
 * See LICENSE file for licensing restrictions              *
 ************************************************************/

if (!defined('_ICARUS_')) die('This script may not be invoked directly.' . "\n");

define('L_DEBUG',   0x01);
define('L_WARNING', 0x02);
define('L_NOTICE',  0x04);
define('L_INFO',    0x08);
define('L_ERROR',   0x10);
define('L_FATAL',   0x20);

define('LOG_NODEBUG', 0xFE);
define('LOG_ALL',     0xFF);

final class Logger extends Singleton
{

	private $logdata = array();
	private $fd = NULL;
	private $logfile = "";
	private $loglevel = LOG_ALL;

	private $forked = FALSE;

        protected function _create($filename = "icarus.log")
        {
		if (!is_null($filename))
		{
			$this->logfile = $filename;
			$this->fd = fopen($this->logfile, 'a+');
		}
        }

	public function setLogfile($filename)
	{
		if (!empty($filename))
		{
			$this->logfile = $filename;
			$this->fd = fopen($this->logfile, 'a+');
		}
	}

	public function __get($name)
	{
		if ($name == "log")
			return $this->logdata;
	}

	public function log($level, $format, $args=NULL)
	{
		$time = time();

		if ((is_array($args)) && (!empty($args)))
			$message = vsprintf($format, $args);
		else
			$message = $format;

		$this->logdata[] = array(
			'time' => $time,
			'level' => $level,
			'message' => $message
		);

		$logentry = sprintf("[%s] %s", date("H:i", $time), $message);

		if ($this->loglevel & $level)
		{
			if ($this->forked != TRUE)
				echo $logentry."\n";
			if (is_resource($this->fd))
				fwrite($this->fd, $logentry);
		}
	}

	public function fork()
	{
		$this->forked = TRUE;
	}

        protected function _destroy()
        {
		fwrite($this->fd,"\n");
		fclose($this->fd);
		unset($this);
        }
}
