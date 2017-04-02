<?php
// cli-only
if(php_sapi_name() !== 'cli')
{
  http_response_code(400);
  exit('This script must be run from the command line.');
}

// functions
require_once 'functions.php';

// config
require_once 'config.php';

// variables
$time = array(
  'start' => 0,
  'end'   => 0,
  'diff'  => 0
);

$content = array(
  'csv' => '',
  'web' => ''
);

$matches = array(
  'csv' => array(),
  'web' => array()
);

$count = array(
  'csv' => 0,
  'csv_duplicates' => 0,
  'web' => 0
);


// measure time
$time['start'] = microtime(true);


// error log
if(!file_exists($config['path']['logs']) || !is_dir($config['path']['logs']))
{
  if(!mkdir($config['path']['logs']))
  {
    taid_echo(STDERR, 'The log directory "%s" could not be created.', $config['path']['logs']);
    exit(1);
  }
}

ini_set('log_errors', 1);
ini_set('display_errors', 0);
ini_set('error_reporting', E_ALL);
ini_set('error_log', $config['path']['logs'] . 'error.log');


// check archive folder
if(!file_exists($config['path']['archive']) || !is_dir($config['path']['archive']))
{
  taid_echo(STDERR, 'The Twitter archive was not found.');
  exit(1);
}


// check tweets.csv
if(!file_exists($config['path']['archive'] . 'tweets.csv') || !is_readable($config['path']['archive'] . 'tweets.csv'))
{
  taid_echo(STDERR, 'The file "tweets.csv" from the Twitter archive does not exist or is not readable.');
  exit(1);
}


// check downloads folder
if(!file_exists($config['path']['downloads']) || !is_dir($config['path']['downloads']))
{
  if(!mkdir($config['path']['downloads']))
  {
    taid_echo(STDERR, 'The downloads directory "%s" could not be created.', $config['path']['downloads']);
    exit(1);
  }
}


// load csv
taid_echo(STDOUT, 'Load the "tweets.csv" file.');

$content['csv'] = file_get_contents($config['path']['archive'] . 'tweets.csv');
if($content['csv'] === false)
{
  taid_echo(STDERR, 'Reading the "tweets.csv" file failed.');
  exit(1);
}


// get media urls
taid_echo(STDOUT, 'Check the contents of the "tweets.csv" file for media links.');

$count['csv'] = preg_match_all('/https:\/\/twitter\.com\/' . $config['user'] . '\/status\/([0-9]+)\/photo\/([0-9])/', $content['csv'], $matches['csv'], PREG_SET_ORDER);
if($count['csv'] === false)
{
  taid_echo(STDERR, 'There was a problem checking the "tweets.csv" file for media links.');
  exit(1);
}

taid_echo(STDOUT, '%d matches were found.', $count['csv']);


// remove duplicates
taid_echo(STDOUT, 'Check matches for duplicates.');

$matches['csv'] = array_values(array_unique($matches['csv'], SORT_REGULAR));
$count['csv_duplicates'] = $count['csv'] - count($matches['csv']);
$count['csv'] -= $count['csv_duplicates'];

taid_echo(STDOUT, '%d duplicates were found and removed.', $count['csv_duplicates']);


// adjust max entries
if($config['max'] == 0 || $config['max'] > $count['csv'])
{
  $config['max'] = $count['csv'];
}


taid_echo(STDOUT, 'Select entries %d to %d.', $config['min'], $config['max']);


for($csvKey = $config['min']; $csvKey < $config['max']; $csvKey++)
{
  $csvItem = $matches['csv'][$csvKey];

  taid_echo(STDOUT, '[%d] Check URL "%s" on media.', $csvKey, $csvItem[0]);

  $content['web'] = file_get_contents($csvItem[0]);
  if($content['web'] === false)
  {
    taid_echo(STDERR, '[%d] The URL "%s" is skipped because the download failed.', $csvKey, $csvItem[0]);
    continue;
  }

  $count['web'] = preg_match_all('/<meta[\ ]+property="og:image" content="(https:\/\/pbs.twimg.com\/media\/([^:]*)[^"]*)">/i', $content['web'], $matches['web'], PREG_SET_ORDER);
  if($count['web'] === false)
  {
    taid_echo(STDERR, '[%d] Unable to read the URL "%s". Skipping URL.', $csvKey, $csvItem[0]);
    continue;
  }

  taid_echo(STDOUT, '[%d] %d matches were found.', $csvKey, $count['web']);

  for($webKey = 0; $webKey < $count['web']; $webKey++)
  {
    $webItem = $matches['web'][$webKey];
    $webFilePath = $config['path']['downloads'] . $webItem[2];

    taid_echo(STDOUT, '[%d][%d] Found URL: "%s".', $csvKey, $webKey, $webItem[1]);

    if(file_exists($webFilePath))
    {
      taid_echo(STDOUT, '[%d][%d] File "%s" is not downloaded because it already exists.', $csvKey, $webKey, $webItem[2]);
      continue;
    }

    taid_echo(STDOUT, '[%d][%d] Download file "%s".', $csvKey, $webKey, $webItem[2]);

    $webFileContent = file_get_contents($webItem[1]);
    if($webFileContent === false)
    {
      taid_echo(STDERR, '[%d][%d] The URL "%s" is skipped because the download failed.', $csvKey, $webKey, $webItem[1]);
      continue;
    }

    if(!file_put_contents($webFilePath, $webFileContent))
    {
      taid_echo(STDERR, '[%d][%d] Failed to save file "%s".', $csvKey, $webKey, $webItem[2]);
      continue;
    }
  }
}


// measure time
$time['end'] = microtime(true);
$time['diff'] = $time['end'] - $time['start'];

taid_echo(STDOUT, 'Program finished after %s.', seconds2human($time['diff']));
