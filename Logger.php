<?php
/************************************************************
 * icarus v1.0-beta -- an IRC framework for PHP            *
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

	public $log = array();
	private $fd = NULL;
	private $logfile = "";
	private $loglevel = LOG_NODEBUG;

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
		if ((!empty($filename)) && ($filename != $this->logfile))
		{
			if (is_resource($this->fd)) fclose($this->fd);

			$this->logfile = $filename;
			$this->fd = fopen($this->logfile, 'a+');
		}
	}

	public function log($level, $format, $args=NULL)
	{
		$time = time();
		
		$format = trim($format);

		if ((is_array($args)) && (!empty($args)))
			$message = vsprintf($format, $args);
		else
			$message = $format;

		$this->log[] = array(
			'time' => $time,
			'level' => $level,
			'message' => $message
		);

		$logentry = sprintf("[%s] %s\n", date("H:i", $time), $message);

		if ($this->loglevel & $level)
		{
			if ($this->forked != TRUE)
				echo $logentry;
			if (is_resource($this->fd))
				fwrite($this->fd, $logentry);
		}
	}

	public function fork()
	{
		$this->forked = TRUE;
	}

	public function debug($onoff)
	{
		switch ($onoff)
		{
			case TRUE:
				$this->loglevel = $this->loglevel | L_DEBUG;
				break;
			case FALSE:
				$this->loglevel = $this->loglevel & ~L_DEBUG;
				break;
		}
	}

        protected function _destroy()
        {
		fwrite($this->fd,"\n");
		fclose($this->fd);
		unset($this);
        }
}
