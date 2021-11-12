<?php
namespace app\models\tools;

class Log
{
	public const LEVEL_DEBUG = 0;
	public const LEVEL_INFO = 1;
	public const LEVEL_WARNING = 2;
	public const LEVEL_ERROR = 3;

	/**
	 * @var mixed
	 */
	private $_fileHandle = null;

	/**
	 * @var mixed
	 */
	private $_logLevel = self::LEVEL_ERROR;

	/**
	 * @param $filePath
	 */
	public function __construct($filePath, $level = self::LEVEL_WARNING)
	{
		if (file_exists($filePath) && filesize($filePath) > 5242880)
		{
			if (file_exists($filePath . ".old"))
			{
				unlink($filePath . ".old");
			}
			rename($filePath, $filePath . ".old");
		}
		$this->_fileHandle = fopen($filePath, 'a');
		$this->_logLevel = $level;
		$this->writeAndFlush('Starting Logging with Level ' . $this->getLogLevelDisplay(), 'LOGLEVEL');
	}

	public function __destruct()
	{
		fclose($this->_fileHandle);
	}

	public function getLogLevelDisplay()
	{
		switch ($this->_logLevel)
		{
			case 0:return "Debug";
			case 1:return "Info";
			case 2:return "Warning";
			case 3:return "Error";
			default:return "Unknown";
		}
	}
	/**
	 * @param $message
	 */
	public function debug($message)
	{
		if ($this->_logLevel === self::LEVEL_DEBUG)
		{
			$this->writeAndFlush($message, 'DEBUG');
		}
	}

	/**
	 * @param $message
	 */
	public function info($message)
	{
		if ($this->_logLevel <= self::LEVEL_INFO)
		{
			$this->writeAndFlush($message, 'INFO');
		}
	}

	/**
	 * @param $message
	 */
	public function warning($message)
	{
		if ($this->_logLevel <= self::LEVEL_WARNING)
		{
			$this->writeAndFlush($message, 'WARNING');
		}
	}

	/**
	 * @param $string
	 */
	public function error($message)
	{
		$this->writeAndFlush($message, 'ERROR');
	}

	/**
	 * @param $message
	 * @param $levelText
	 */
	public function writeAndFlush($message, $levelText)
	{
		fwrite($this->_fileHandle, (new \DateTime())->format('m/d/Y H:i:s') . ' ' . $levelText . ' ' . $message . "\n");
		fflush($this->_fileHandle);
	}
}
