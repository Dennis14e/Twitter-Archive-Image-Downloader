<?php
// cli-only
if(php_sapi_name() !== 'cli')
{
  http_response_code(400);
  exit('Dieses Skript muss über die Kommandozeile ausgeführt werden.');
}

function taid_echo($stream, $format, ...$args)
{
  fwrite($stream, '[' . date('Y-m-d H:i:s') . '] ' . vsprintf($format, $args) . "\r\n");
}


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
    taid_echo(STDERR, 'Das Log-Verzeichnis "%s" konnte nicht erzeugt werden.', $config['path']['logs']);
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
  taid_echo(STDERR, 'Das Twitter Archiv wurde nicht gefunden.');
  exit(1);
}


// check tweets.csv
if(!file_exists($config['path']['archive'] . 'tweets.csv') || !is_readable($config['path']['archive'] . 'tweets.csv'))
{
  taid_echo(STDERR, 'Die Datei "tweets.csv" aus dem Twitter Archiv existiert nicht oder ist nicht lesbar.');
  exit(1);
}


// check downloads folder
if(!file_exists($config['path']['downloads']) || !is_dir($config['path']['downloads']))
{
  if(!mkdir($config['path']['downloads']))
  {
    taid_echo(STDERR, 'Das Downloads-Verzeichnis "%s" konnte nicht erzeugt werden.', $config['path']['downloads']);
    exit(1);
  }
}


// load csv
taid_echo(STDOUT, 'Lade Inhalt der Datei "tweets.csv');

$content['csv'] = file_get_contents($config['path']['archive'] . 'tweets.csv');
if($content['csv'] === false)
{
  taid_echo(STDERR, 'Lesen der Datei "tweets.csv" ist fehlgeschlagen.');
  exit(1);
}


// get media urls
taid_echo(STDOUT, 'Überprüfe Inhalt der Datei "tweets.csv" auf Medien-Verlinkungen.');

$count['csv'] = preg_match_all('/https:\/\/twitter\.com\/' . $config['user'] . '\/status\/([0-9]+)\/photo\/([0-9])/', $content['csv'], $matches['csv'], PREG_SET_ORDER);
if($count['csv'] === false)
{
  taid_echo(STDERR, 'Es gab ein Problem bei dem Prüfen der Datei "tweets.csv" auf Medien-Verlinkungen.');
  exit(1);
}

taid_echo(STDOUT, 'Es wurden %d Übereinstimmungen gefunden.', $count['csv']);


// remove duplicates
taid_echo(STDOUT, 'Überprüfe Übereinstimmungen auf Duplikate.');

$matches['csv'] = array_unique($matches['csv'], SORT_REGULAR);
$count['csv_duplicates'] = $count['csv'] - count($matches['csv']);
$count['csv'] -= $count['csv_duplicates'];

taid_echo(STDOUT, 'Es wurden %d Duplikate gefunden und entfernt.', $count['csv_duplicates']);


// adjust max entries
if($config['max'] == 0 || $config['max'] > $count['csv'])
{
  $config['max'] = $count['csv'];
}


taid_echo(STDOUT, 'Selektiere Einträge %d bis %d.', $config['min'], $config['max']);


for($csvKey = $config['min']; $csvKey < $config['max']; $csvKey++)
{
  $csvItem = $matches['csv'][$csvKey];

  taid_echo(STDOUT, '[%d] Überprüfe URL "%s" auf Medien.', $csvKey, $csvItem[0]);

  $content['web'] = file_get_contents($csvItem[0]);
  if($content['web'] === false)
  {
    taid_echo(STDERR, '[%d] Die URL "%s" wird übersprungen, da das Herunterladen fehlgeschlagen ist.', $csvKey, $csvItem[0]);
    continue;
  }

  $count['web'] = preg_match_all('/<meta[\ ]+property="og:image" content="(https:\/\/pbs.twimg.com\/media\/([^:]*)[^"]*)">/i', $content['web'], $matches['web'], PREG_SET_ORDER);
  if($count['web'] === false)
  {
    taid_echo(STDERR, '[%d] Lesen der URL "%s" ist fehlgeschlagen. Überspringe URL.', $csvKey, $csvItem[0]);
    continue;
  }

  taid_echo(STDOUT, '[%d] Es wurden %d Übereinstimmungen gefunden.', $csvKey, $count['web']);

  for($webKey = 0; $webKey < $count['web']; $webKey++)
  {
    $webItem = $matches['web'][$webKey];
    $webFilePath = $config['path']['downloads'] . $webItem[2];

    taid_echo(STDOUT, '[%d][%d] Gefundene URL: "%s".', $csvKey, $webKey, $webItem[1]);

    if(file_exists($webFilePath))
    {
      taid_echo(STDOUT, '[%d][%d] Datei "%s" wird nicht heruntergeladen, da diese bereits existiert.', $csvKey, $webKey, $webItem[2]);
      continue;
    }

    taid_echo(STDOUT, '[%d][%d] Lade Datei "%s" herunter.', $csvKey, $webKey, $webItem[2]);

    $webFileContent = file_get_contents($webItem[1]);
    if($webFileContent === false)
    {
      taid_echo(STDERR, '[%d][%d] Die URL "%s" wird übersprungen, da das Herunterladen fehlgeschlagen ist.', $csvKey, $webKey, $webItem[1]);
      continue;
    }

    if(!file_put_contents($webFilePath, $webFileContent))
    {
      taid_echo(STDERR, '[%d][%d] Die Datei "%s" konnte nicht gespeichert werden.', $csvKey, $webKey, $webItem[2]);
      continue;
    }
  }
}


// measure time
$time['end'] = microtime(true);
$time['diff'] = $time['end'] - $time['start'];

taid_echo(STDOUT, 'Programm nach %.2fs beendet.', $time['diff']);
