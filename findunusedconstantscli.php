<?php
/**
 * A single file CLI library to help find unused Joomla language constants.
 *
 * @author 	   Gunjan Patel (gunjan.ce2009@gmail.com)
 * @copyright  Copyright (C) 2014 Gunjan Patel. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

if (!defined('_JEXEC'))
{
	// Initialize Joomla framework
	define('_JEXEC', 1);
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load system defines
if (file_exists(dirname(__FILE__) . '/defines.php'))
{
	require_once dirname(__FILE__) . '/defines.php';
}

if (!defined('JPATH_BASE'))
{
	define('JPATH_BASE', dirname(__FILE__));
}

if (!defined('_JDEFINES'))
{
	require_once JPATH_BASE . '/includes/defines.php';
}

// Get the framework.
require_once JPATH_BASE . '/includes/framework.php';
require_once JPATH_BASE . '/libraries/joomla/filesystem/file.php';
require_once JPATH_BASE . '/libraries/joomla/filesystem/folder.php';
require_once JPATH_BASE . '/libraries/joomla/language/language.php';

/**
 * Put an application online
 *
 * @package  Joomla.Shell
 *
 * @since    1.0
 */
class FindUnusedConstants extends JApplicationCli
{
	/**
	 * File path
	 *
	 * @var  string
	 */
	private $files;

	/**
	 * Entry point for the script
	 *
	 * @return  void
	 *
	 * @throws Exception
	 */
	public function doExecute()
	{
		// Check if help is needed.
		if ($this->input->get('h') || $this->input->get('help'))
		{
			$this->help();

			return;
		}

		try
		{
			$this->files = $this->input->getPath('file');

			if (!$this->files)
			{
				throw new Exception('Language File Path name must be specified');
			}

			$this->files = explode(',', $this->files);

			$this->showUnusedConstants($this->scanFiles());
		}
		catch (Exception $e)
		{
			$this->out($e->getMessage());
			$this->help();
		}
	}

	/**
	 * Scan files given in argument
	 *
	 * @return  array  Unused Constants array
	 */
	public function scanFiles()
	{
		// Load all of joomla into a gigantic array
		$data = $this->loadAllJoomlaFileIntoGiganticArray();

		$unused = array();

		// Loop each file
		foreach ($this->files as $file)
		{
			$file = JPATH_BASE . '/' . $file;

			$this->out('Using File: ' . $file);

			$lines = $this->loadLanguage($file);

			foreach ($lines as $constant => $line)
			{
				// Some constants are system ones that are put together on the fly
				// We can not find them so just manually remove them
				$ignored_constant_patterns = array();
				$ignored_constant_patterns[] = '/.*_SAVE_SUCCESS/im';

				// A plural string with _N_
				$ignored_constant_patterns[] = '/.*_N_.*/im';

				// A plural string ending in a number
				$ignored_constant_patterns[] = '/.*_\d/im';

				// A plural string
				$ignored_constant_patterns[] = '/.*_MORE/im';
				$ignored_constant_patterns[] = '/.*_NO_ITEM_SELECTED/im';
				$ignored_constant_patterns[] = '/.*_LAYOUT_DEFAULT/im';
				$ignored_constant_patterns[] = '/.*_CONFIGURATION/im';
				$ignored_constant_patterns[] = '/TPL_.*_POSITION_.*/im';
				$ignored_constant_patterns[] = '/COM_MODULES_POSITION_.*/im';

				foreach ($ignored_constant_patterns as $ignored_constant_pattern)
				{
					if (preg_match($ignored_constant_pattern, $constant))
					{
						unset($lines[$constant]);

						continue 2;
					}
				}

				// Search the gigantic array for the string
				foreach ($data as $datafile => $d)
				{
					$file_parts = explode('.', $datafile);
					$file_extention = array_pop($file_parts);

					if ($file_extention == 'xml')
					{
						// If this is an XML file then simply look for the constant
						$pattern = "/" . $constant . "/mi";
					}
					else
					{
						// If not XML then it is php and we want to see the constant inside quotes
						$pattern = "/['|\"]\s*" . $constant . "\s*['|\"]/mi";
					}

					if (preg_match($pattern, $d) === 1)
					{
						// Remove the ones we find
						unset($lines[$constant]);
						continue 2;
					}
				}
			}

			$unused[$file] = $lines;
		}

		return $unused;
	}

	/**
	 * Load All Joomla files to prepare scan
	 *
	 * @return  array  File path array
	 */
	protected function loadAllJoomlaFileIntoGiganticArray()
	{
		return $this->loadFilesIntoArray(
			JFolder::files(JPATH_BASE, '.*\.(php|xml)', true, true)
		);
	}

	/**
	 * Load files contents into an array
	 *
	 * @param   array  $files  File path array
	 *
	 * @return  array  Containing files path and file data
	 */
	protected function loadFilesIntoArray($files)
	{
		$data = array();

		foreach ($files as $file)
		{
			$data[$file] = file_get_contents($file);
		}

		return $data;
	}

	/**
	 * Loads a language file
	 *
	 * @param   string  $filename  The name of the file.
	 *
	 * @return  string  Language string
	 */
	protected function loadLanguage($filename)
	{
		$strings = false;

		if (file_exists($filename))
		{
			$strings = $this->parseFile($filename);
		}

		return $strings;
	}

	/**
	 * Parses a language file
	 *
	 * @param   string  $filename  The name of the file.
	 *
	 * @return  string
	 */
	protected function parseFile($filename)
	{
		$contents = file_get_contents($filename);
		$contents = str_replace('_QQ_', '"\""', $contents);
		$strings  = @parse_ini_string($contents);

		if (!is_array($strings))
		{
			$strings = array();
		}

		return $strings;
	}

	/**
	 * Show unused constants on terminal
	 *
	 * @param   array  $unused  Array containing all the unused strings info
	 *
	 * @return  void
	 */
	public function showUnusedConstants($unused)
	{
		$this->out('Find unused Language Constants - Results!');

		if (count($unused) == 0)
		{
			$this->out('No unused constants were found in the file(s).');
		}
		else
		{
			$this->out('The following constants appear to be unused. It is recommended that you check manually and create a backup before deleting any constants');

			foreach ($unused as $file => $constants)
			{
				$this->out('Found (' . count($constants) . ' Constants) in ' . $file);

				foreach ($constants as $constant => $text)
				{
					$this->out($constant);
				}
			}
		}
	}

	/**
	 * display help
	 *
	 * @return void
	 */
	private function help()
	{
		$this->out();
		$this->out('--- Help Find Unused Language Constants ---');
		$this->out();
		$this->out('Usage:     php -f findunusedconstantscli.php [switches]');
		$this->out();
		$this->out('Switches:  --file=<file name. Example: language/en-GB/en-GB.com_redshop.ini>');
		$this->out('Switches:  -h | --help Prints this usage information.');
		$this->out();
	}
}

JApplicationCli::getInstance('FindUnusedConstants')->execute();
