<?php
// cli-only
if (php_sapi_name() !== 'cli')
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
$csv = array(
  'handle'  => null,
  'content' => array(),
);

$time = array(
  'start' => 0,
  'end'   => 0,
  'diff'  => 0,
);

$js_files = array();

$images = array();

$count = array(
  'images'        => 0,
  'duplicates'    => 0,
  'tweets'        => 0,
  'tweets_images' => 0,
);


// measure time
$time['start'] = microtime(true);


// open csv file
$csv['handle'] = fopen($config['path']['csv'], 'w');
$csv['content'][] = array(
  'date_check',
  'date_file',
  'url_display',
  'url_media',
  'type',
  'status',
);


// check/create logs folder
if (!taid_is_dir($config['path']['logs']))
{
  taid_echo(STDERR, 'The log directory "%s" could not be created.', $config['path']['logs']);
  exit(1);
}

// error log
ini_set('log_errors', 1);
ini_set('display_errors', 0);
ini_set('error_reporting', E_ALL);
ini_set('error_log', $config['path']['logs'] . 'error.log');


// check archive folder
if (!is_dir($config['path']['archive']))
{
  taid_echo(STDERR, 'The Twitter archive was not found.');
  exit(1);
}


// check data/js/tweets folder
if (!is_readable($config['path']['archive'] . 'data/js/tweets/'))
{
  taid_echo(STDERR, 'The folder "data/js/tweets/" from the Twitter archive does not exist or is not readable.');
  exit(1);
}


// check/create downloads folder
if (!taid_is_dir($config['path']['downloads'] . 'oc/') || !taid_is_dir($config['path']['downloads'] . 'rt/'))
{
  taid_echo(STDERR, 'The downloads directory "%s" could not be created.', $config['path']['downloads']);
  exit(1);
}


// get .js-files
taid_echo(STDOUT, 'Create array of javascript files.');

foreach (glob($config['path']['archive'] . 'data/js/tweets/*.js') as $path)
{
  $name = pathinfo($path, PATHINFO_FILENAME);
  $js_files[$name] = $path;
}


// get content of .js-files
taid_echo(STDOUT, 'Get content of the javascript files.');

foreach ($js_files as $js_name => $js_path)
{
  $string = file_get_contents($js_path);
  if ($string === false)
  {
    taid_echo(STDERR, '[%s] Failed to load file "%s".', $js_name, $js_path);
    continue;
  }

  // remove first line
  $string = str_chop_lines($string, 1);

  // save to array
  $tweets = json_decode($string, true);
  if (!is_array($tweets))
  {
    taid_echo(STDERR, '[%s] Failed to decode file "%s" to JSON.', $js_name, $js_path);
    continue;
  }

  taid_echo(STDOUT, '[%s] Check tweets for media urls.', $js_name);

  $count['tweets'] = count($tweets);
  $count['tweets_images'] = 0;

  foreach ($tweets as $tweet)
  {
    if (empty($tweet['entities']['media']))
    {
      // tweet has no media
      continue;
    }

    foreach ($tweet['entities']['media'] as $media)
    {
      // only images, remove video thumbnails
      if (!preg_match('/\/media\//', $media['media_url']))
      {
        continue;
      }

      $count['tweets_images']++;

      $images[] = array(
        'image_url'   => ($config['https']) ? $media['media_url_https'] : $media['media_url'],
        'display_url' => $media['display_url'],
        'retweet'     => isset($tweet['retweeted_status']),
      );
    }
  }

  taid_echo(STDOUT, '[%s] Found %d image urls in %d tweets.', $js_name, $count['tweets_images'], $count['tweets']);
}

$count['images'] = count($images);


// remove duplicates
taid_echo(STDOUT, 'Check urls for duplicates.');

$images = array_unique($images, SORT_REGULAR);
$count['duplicates'] = $count['images'] - count($images);
$count['images'] -= $count['duplicates'];

taid_echo(STDOUT, '%d duplicates were found and removed.', $count['duplicates']);

// adjust max entries
if ($config['max'] == 0 || $config['max'] > $count['images'])
{
  $config['max'] = $count['images'];
}

taid_echo(STDOUT, 'Select entries %d to %d.', $config['min'], $config['max']);


// loop image urls
for ($i = $config['min']; $i < $config['max']; $i++)
{
  $image = $images[$i];
  $image['name'] = pathinfo($image['image_url'], PATHINFO_BASENAME);
  $image['type'] = ($image['retweet'] === true) ? 'rt' : 'oc';
  $image['path'] = $config['path']['downloads'] . $image['type'] . '/' . $image['name'];

  if (file_exists($image['path']))
  {
    taid_echo(STDOUT, '[%d] File "%s" is not downloaded because it already exists.', $i, $image['name']);
    $csv['content'][] = array(
      date('Y-m-d H:i:s'),
      date('Y-m-d H:i:s', filemtime($image['path'])),
      $image['display_url'],
      $image['image_url'],
      $image['type'],
      'file_exists',
    );

    continue;
  }

  taid_echo(STDOUT, '[%d] Download file "%s".', $i, $image['name']);

  $image['content'] = file_get_contents($image['image_url']);
  if ($image['content'] === false)
  {
    taid_echo(STDERR, '[%d] The URL "%s" is skipped because the download failed.', $i, $image['image_url']);
    $csv['content'][] = array(
      date('Y-m-d H:i:s'),
      '',
      $image['display_url'],
      $image['image_url'],
      $image['type'],
      'download_failed',
    );

    continue;
  }

  if (!file_put_contents($image['path'], $image['content']))
  {
    taid_echo(STDERR, '[%d] Failed to save file "%s".', $i, $image['name']);
    $csv['content'][] = array(
      date('Y-m-d H:i:s'),
      '',
      $image['display_url'],
      $image['image_url'],
      $image['type'],
      'save_failed',
    );

    continue;
  }

  $image['headers'] = array_change_key_case(get_headers($image['image_url'], 1), CASE_LOWER);
  if (array_key_exists('last-modified', $image['headers']))
  {
    taid_echo(STDOUT, '[%d] Update local headers of "%s".', $i, $image['name']);
    touch($image['path'], strtotime($image['headers']['last-modified']));
  }

  $csv['content'][] = array(
    date('Y-m-d H:i:s'),
    date('Y-m-d H:i:s', filemtime($image['path'])),
    $image['display_url'],
    $image['image_url'],
    $image['type'],
    'success',
  );
}


// write and close csv file
foreach ($csv['content'] as $line)
{
  fputcsv($csv['handle'], $line);
}
fclose($csv['handle']);


// measure time
$time['end'] = microtime(true);
$time['diff'] = $time['end'] - $time['start'];

taid_echo(STDOUT, 'Program finished after %s.', seconds2human($time['diff']));
