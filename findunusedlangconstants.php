<?php
/**
 * A single file library to help find unused Joomla language constants
 * @author Soren Beck Jensen <soren@notwebdesign.com>
 * @copyright  Copyright (C) 2014 Jensen Technologies SL. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */
define('_JEXEC', true);
error_reporting(E_ALL);

//Increase limits
set_time_limit(60000);
ini_set('memory_limit','128M');

//Setup system to use Joomla framework
require_once 'configuration.php';
if (file_exists(__DIR__ . '/defines.php'))
{
	include_once __DIR__ . '/defines.php';
}
if (!defined('_JDEFINES'))
{
	define('JPATH_BASE', __DIR__);
	require_once JPATH_BASE . '/includes/defines.php';
}
require_once JPATH_BASE . '/includes/framework.php';
require_once JPATH_BASE . '/libraries/joomla/filesystem/file.php';
require_once JPATH_BASE . '/libraries/joomla/filesystem/folder.php';
require_once JPATH_BASE . '/libraries/joomla/language/language.php';

$unusedconstants = new Unusedconstants;

$scanfiles = filter_input(INPUT_POST, 'scanfiles', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY);
if (count($scanfiles) > 0) {
    $unused = $unusedconstants->scanFiles($scanfiles);
    $unusedconstants->showUnusedConstants($unused);
} else {
    $unusedconstants->showFileSelectForm();
}

class Unusedconstants 
{
    
    /**
     * Show a list of unused constants
     * @param array $unused a list of constants that are not in use
     */
    public function showUnusedConstants($unused) 
    {
        ?>
        <html>
            <head>
                <title>Find unused Language Constants - Results!</title>
                <meta charset="utf-8">
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php $this->includeBootstrap(); ?>
                <style>
                    body {
                        padding-top: 70px;
                    }
                    #backbtn {
                        margin-top: 7px;
                    }
                </style>
            </head>
            <body>
                <div class="navbar navbar-inverse navbar-fixed-top">
                    <div class="container">
                        <div class="row">
                            <div class="pull-left navbar-brand">Find unused Language Constants - Results!</div>
                            <div class="pull-right">
                                <a href="<?php echo basename(__FILE__); ?>" class="btn btn-primary" id="backbtn">Back to file list</a>
                            </div>
                        </div>
                    </div>
                </div>                
                
                <div class="container">
                    <?php if (count($unused) == 0): ?>
                        <div class="alert alert-info">No unused constants were found in the file(s).</div>
                    <?php else: ?>
                        <div class="alert alert-danger">The following constants appear to be unused. It is recommended that you check manually and create a backup before deleting any constants</div>
                        <?php foreach ($unused as $file => $constants): ?>
                            <div class="panel panel-warning">
                                <div class="panel-heading">
                                  <h3 class="panel-title">(<?php echo count($constants); ?> constants) <?php echo $file; ?></h3>
                                </div>
                                <div class="panel-body">
                                    <ul>
                                        <?php foreach ($constants as $constant => $text): ?>
                                        <li><strong><?php echo $constant; ?></strong>: <small class="text-muted"><?php echo htmlentities($text); ?></small></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>                                
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                        
                </div>
            </body>
        </html>
        
        <?php
    }
    
    /**
     * Scans a set of files
     * @param array $files
     * @return array with constants that appear to not be used, indexed by file.
     */
    public function scanFiles($files) 
    {
        //Load all of joomla into a gigantic array
        $data = $this->loadAllJoomlaFileIntoGiganticArray();
        //echo '<pre class="debug"><small>' . __file__ . ':' . __line__ . "</small>\n\$data = ". print_r($data, true)."\n</pre>";

        $unused = array();
        
        //Loop each file
        foreach ($files as $file) 
        {
            $file = urldecode($file);
            $lines = $this->loadLanguage($file);
            
            foreach ($lines as $constant => $line) 
            {
                //Some constants are system ones that are put together on the fly
                //We can not find them so just manually remove them
                $ignored_constant_patterns = array();
                $ignored_constant_patterns[] = '/.*_SAVE_SUCCESS/im';
                $ignored_constant_patterns[] = '/.*_N_.*/im'; //A plural string with _N_ 
                $ignored_constant_patterns[] = '/.*_\d/im'; //A plural string ending in a number
                $ignored_constant_patterns[] = '/.*_MORE/im'; //A plural string
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
                
                //Search the gigantic array for the string
                foreach ($data as $datafile => $d) 
                {
                    $file_parts = explode('.', $datafile);
                    $file_extention = array_pop($file_parts);
                    if ($file_extention == 'xml') {
                        //If this is an XML file then simply look for the constant
                        $pattern = "/".$constant."/mi"; 
                    } else {
                        //If not XML then it is php and we want to see the constant inside quotes
                        $pattern = "/['|\"]\s*".$constant."\s*['|\"]/mi";
                    }
                    if (preg_match($pattern, $d) === 1) 
                    {
                        unset($lines[$constant]); //Remove the ones we find
                        continue 2;
                    }
                }
            }
            
            $unused[$file] = $lines;
        }
        
        return $unused;
        
    }
    
    
    protected function includeBootstrap() 
    {
        ?>
        <!-- Latest compiled and minified CSS -->
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap-theme.min.css">
        <!-- Latest compiled and minified JavaScript -->
        <script src="https://code.jquery.com/jquery-2.1.1.min.js"></script>        
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.2.0/js/bootstrap.min.js"></script>        
        <?php
    }
    
    
    public function showFileSelectForm($selected=array()) 
    {
        
        //HTML
        ?>
        <html>
            <head>
                <title>Find unused Language Constants</title>
                <meta charset="utf-8">
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php $this->includeBootstrap(); ?>
                <style>
                    body {
                        padding-top: 70px;
                    }
                    #scanbtn {
                        margin-top: 7px;
                    }
                </style>
                <script>
                    $(document).ready(function(){
                        
                        //Bind selecting a file
                        $(':checkbox').change(function() 
                        {
                            var num_checked = countSelectedCheckboxes();
                            
                            $('#warn-too-many').hide();
                            
                            //Update button text
                            if (num_checked > 0) {
                                $('#scanbtn').text('Scan ' + num_checked + ' files').removeAttr('disabled');
                                if (num_checked > 3) {
                                    $('#warn-too-many').removeClass('hidden').show();
                                }
                            } else {
                                $('#scanbtn').text('Scan').attr('disabled','disabled');
                            }
                            
                        });
                        
                    });
                    
                    function countSelectedCheckboxes() 
                    {
                        return $(':checkbox:checked').length;
                    }
                </script>
            </head>
            <body>
                <?php
                //Find all language files
                $files = JFolder::files(JPATH_BASE, 'en-GB.*\.ini', true, true);                
                ?>
                <?php if (count($files)): ?>
                    <form action="<?php echo basename(__FILE__); ?>" method="post">
                        <div class="navbar navbar-inverse navbar-fixed-top">
                            <div class="container">
                                <div class="row">
                                    <div class="pull-left navbar-brand">Find unused Language Constants</div>
                                    <div class="pull-right">
                                        
                                        <button type="submit" disabled="disabled" class="btn btn-success" id="scanbtn" data-toggle="modal" data-target="#loading-modal">Scan</button>
                                        
                                    </div>
                                </div>
                            </div>
                        </div>                
                        <div class="container">
                            
                            <!-- modal -->
                            <div class="modal fade" id="loading-modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-body text-center">
                                            <h1>Scanning...</h1>
                                            <br /><br /><br />
                                        </div>
                                    </div>
                                </div>
                            </div>                                        

                            <div class="alert alert-danger hidden" id="warn-too-many">Warning! Selecting more than a few files can result in very long processing time and/or flat out timeouts and out of memory errors. Proceed with care!</div>
                            <h4>Select the language file(s) to scan below</h4>
                            <table class="table table-hover">
                                <tbody>
                                    <?php $i = 0; ?>
                                    <?php foreach ($files as $file): ?>
                                        <?php $i++; ?>
                                        <tr><td>
                                            <input type="checkbox" name="scanfiles[]" value="<?php echo urlencode($file); ?>" id="option<?php echo $i; ?>" /> 
                                            <label for="option<?php echo $i; ?>"><?php echo $file;?></label>
                                        </td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="container">
                        <div class="alert alert-danger">Error! Unable to find any language files.</div>
                        Please make sure you place this file in the root of your Joomla site
                    </div>
                <?php endif; ?>
            </body>
        </html>
        <?php

    }
    
    protected function loadAllJoomlaFileIntoGiganticArray() {
        
        $files = JFolder::files(JPATH_BASE, '.*\.(php|xml)', true, true);
        return $this->loadFilesIntoArray($files);
        
    }
    
    protected function loadFilesIntoArray($files) {
        
        $data = array();
        foreach ($files as $file) 
        {
            $data[$file] = file_get_contents($file);
        }
        
        return $data;
        
    }
    
    
	/**
	 * Loads a language file.
	 * @param   string  $filename   The name of the file.
	 */
	protected function loadLanguage($filename)
	{

		$strings = false;

		if (file_exists($filename))
		{
			$strings = $this->parse($filename);
		}

		return $strings;
	}
 
	/**
	 * Parses a language file.
	 *
	 * @param   string  $filename  The name of the file.
	 */
	protected function parse($filename)
	{

		$contents = file_get_contents($filename);
		$contents = str_replace('_QQ_', '"\""', $contents);
		$strings = @parse_ini_string($contents);

		if (!is_array($strings))
		{
			$strings = array();
		}

		return $strings;
	}    
    
    
}
