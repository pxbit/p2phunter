<?php
/**
 * ============================================================================
 * PPD INSTALL PROGRAM
 */
if(!defined('IN_PPD')) {
	exit('Access Denied');
}
define('CHARSET', 'utf-8');
define('DBCHARSET', 'utf8');
define('TABLEPRE', 'ppd_');

$env_items = array
(
	'os' => array(''),
	'php' => array(''),
	'attachmentupload' => array(),
	'gdversion' => array(),
	'diskspace' => array(),
);
$dir_items = array
(
  'Install' => array('path' => '/Install'),
  'Application' => array('path' => '/Application/Runtime'),
  'Upload' => array('path' => '/Upload'),
  'Conf' => array('path' => '/Application/Common/Conf')
);
$func_items = array(
  'mysql_connect'=>array(),
  'file_get_contents'=>array(),
  'curl_init'=>array()
);
?>