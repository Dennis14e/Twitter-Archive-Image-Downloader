<?php
// cli-only
if(php_sapi_name() !== 'cli')
{
  http_response_code(400);
  exit('This script must be run from the command line.');
}

// change dir
chdir(__DIR__);

// functions
require_once 'functions.php';

// config
require_once 'config.php';

// variables
$time = array(
  'start' => 0,
  'end'   => 0,
  'diff'  => 0,
);

$js_files = array();

$media_urls = array();

$count = array(
  'media'        => 0,
  'duplicates'   => 0,
  'tweets'       => 0,
  'tweets_media' => 0,
);


// measure time
$time['start'] = microtime(true);


// error log
if(!is_dir($config['path']['logs']) && !mkdir($config['path']['logs']))
{
  taid_echo(STDERR, 'The log directory "%s" could not be created.', $config['path']['logs']);
  exit(1);
}

ini_set('log_errors', 1);
ini_set('display_errors', 0);
ini_set('error_reporting', E_ALL);
ini_set('error_log', $config['path']['logs'] . 'error.log');


// check archive folder
if(!is_dir($config['path']['archive']))
{
  taid_echo(STDERR, 'The Twitter archive was not found.');
  exit(1);
}


// check data/js/tweets folder
if(!is_readable($config['path']['archive'] . 'data/js/tweets/'))
{
  taid_echo(STDERR, 'The folder "data/js/tweets/" from the Twitter archive does not exist or is not readable.');
  exit(1);
}


// check downloads folder
if(!is_dir($config['path']['downloads']) && !mkdir($config['path']['downloads']))
{
  taid_echo(STDERR, 'The downloads directory "%s" could not be created.', $config['path']['downloads']);
  exit(1);
}


// get .js-files
taid_echo(STDOUT, 'Create array of javascript files.');

foreach(glob($config['path']['archive'] . 'data/js/tweets/*.js') as $path)
{
  $name = pathinfo($path, PATHINFO_FILENAME);
  $js_files[$name] = $path;
}


// get content of .js-files
taid_echo(STDOUT, 'Get content of the javascript files.');

foreach($js_files as $js_name => $js_path)
{
  $string = file_get_contents($js_path);
  if($string === false)
  {
    taid_echo(STDERR, '[%s] Failed to load file "%s".', $js_name, $js_path);
    continue;
  }

  // remove first line
  $string = str_chop_lines($string, 1);

  // save to array
  $tweets = json_decode($string, true);
  if(!is_array($tweets))
  {
    taid_echo(STDERR, '[%s] Failed to decode file "%s" to JSON.', $js_name, $js_path);
    continue;
  }

  taid_echo(STDOUT, '[%s] Check tweets for media urls.', $js_name);

  $count['tweets'] = count($tweets);
  $count['tweets_media'] = 0;

  foreach($tweets as $tweet)
  {
    if(empty($tweet['entities']['media']))
    {
      // tweet has no media
      continue;
    }

    foreach($tweet['entities']['media'] as $media)
    {
      // only images, remove video thumbnails
      if(!preg_match('/\/media\//', $media['media_url']))
      {
        continue;
      }

      $count['tweets_media']++;

      $media_urls[] = ($config['https'])
        ? $media['media_url_https']
        : $media['media_url'];
    }
  }

  taid_echo(STDOUT, '[%s] Found %d media urls in %d tweets.', $js_name, $count['tweets_media'], $count['tweets']);
}

$count['media'] = count($media_urls);


// remove duplicates
taid_echo(STDOUT, 'Check urls for duplicates.');

$media_urls = array_values(array_unique($media_urls, SORT_REGULAR));
$count['duplicates'] = $count['media'] - count($media_urls);
$count['media'] -= $count['duplicates'];

taid_echo(STDOUT, '%d duplicates were found and removed.', $count['duplicates']);

// adjust max entries
if($config['max'] == 0 || $config['max'] > $count['media'])
{
  $config['max'] = $count['media'];
}

taid_echo(STDOUT, 'Select entries %d to %d.', $config['min'], $config['max']);


// loop media urls
for($i = $config['min']; $i < $config['max']; $i++)
{
  $url  = $media_urls[$i];
  $name = pathinfo($url, PATHINFO_BASENAME);
  $path = $config['path']['downloads'] . $name;

  if(file_exists($path))
  {
    taid_echo(STDOUT, 'File "%s" is not downloaded because it already exists.', $name);
    continue;
  }

  taid_echo(STDOUT, 'Download file "%s".', $name);

  $content = file_get_contents($url);
  if($content === false)
  {
    taid_echo(STDERR, 'The URL "%s" is skipped because the download failed.', $url);
    continue;
  }

  if(!file_put_contents($path, $content))
  {
    taid_echo(STDERR, 'Failed to save file "%s".', $name);
    continue;
  }

  $headers = array_change_key_case(get_headers($url, 1), CASE_LOWER);
  if(array_key_exists('last-modified', $headers))
  {
    taid_echo(STDOUT, 'Update local headers of "%s".', $name);
    touch($path, strtotime($headers['last-modified']));
  }
}


// measure time
$time['end'] = microtime(true);
$time['diff'] = $time['end'] - $time['start'];

taid_echo(STDOUT, 'Program finished after %s.', seconds2human($time['diff']));
