#!/usr/bin/env php
<?php
/**
 * Tool to generate objects for dreamweaver template files.
 *
 * PHP version 5
 *
 * @package   UNL_DWT
 * @author    Brett Bieber <brett.bieber@gmail.com>
 * @copyright 2015 Regents of the University of Nebraska
 * @license   http://wdn.unl.edu/software-license BSD License
  */

ini_set('display_errors', true);

$vendorDir = __DIR__ . '/../vendor';
if (!file_exists($vendorDir)) {
	$vendorDir = __DIR__ . '/../../..';
}

require $vendorDir . '/autoload.php';

if (!@$_SERVER['argv'][1]) {
    throw new Exception("\nERROR: createTemplates.php usage: 'php createTemplates.php example.ini'\n\n");
}

$config = parse_ini_file($_SERVER['argv'][1], true);
foreach($config as $class => $values) {
    if ($class === 'UNL\DWT\AbstractDwt') {
        UNL\DWT\AbstractDwt::$options = $values;
    }
}

if (empty(UNL\DWT\AbstractDwt::$options)) {
    throw new Exception("\nERROR: could not read ini file\n\n");
}

set_time_limit(0);

$generator = new UNL\DWT\Generator;
$generator->start();
