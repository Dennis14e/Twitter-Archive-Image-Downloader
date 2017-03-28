<?php
// cli-only
if(php_sapi_name() !== 'cli')
{
  http_response_code(400);
  exit('Dieses Skript muss über die Kommandozeile ausgeführt werden.');
}


// config
require_once 'config.inc.php';

// variables
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


// error log
if(!file_exists($config['path']['logs']) || !is_dir($config['path']['logs']))
{
  if(!mkdir($config['path']['logs']))
  {
    fwrite(STDERR, sprintf("Das Log-Verzeichnis \"%s\" konnte nicht erzeugt werden.\r\n", $config['path']['logs']));
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
  fwrite(STDERR, "Das Twitter Archiv wurde nicht gefunden.\r\n");
  exit(1);
}


// check tweets.csv
if(!file_exists($config['path']['archive'] . 'tweets.csv') || !is_readable($config['path']['archive'] . 'tweets.csv'))
{
  fwrite(STDERR, "Die Datei \"tweets.csv\" aus dem Twitter Archiv existiert nicht oder ist nicht lesbar.\r\n");
  exit(1);
}


// check downloads folder
if(!file_exists($config['path']['downloads']) || !is_dir($config['path']['downloads']))
{
  if(!mkdir($config['path']['downloads']))
  {
    fwrite(STDERR, sprintf("Das Downloads-Verzeichnis \"%s\" konnte nicht erzeugt werden.\r\n", $config['path']['downloads']));
    exit(1);
  }
}


// load csv
fwrite(STDOUT, "Lade Inhalt der Datei \"tweets.csv\".\r\n");

$content['csv'] = file_get_contents($config['path']['archive'] . 'tweets.csv');
if($content['csv'] === false)
{
  fwrite(STDERR, "Lesen der Datei \"tweets.csv\" ist fehlgeschlagen.\r\n");
  exit(1);
}


// get media urls
fwrite(STDOUT, "Überprüfe Inhalt der Datei \"tweets.csv\" auf Medien-Verlinkungen.\r\n");

$count['csv'] = preg_match_all('/https:\/\/twitter\.com\/' . $config['user'] . '\/status\/([0-9]+)\/photo\/([0-9])/', $content['csv'], $matches['csv'], PREG_SET_ORDER);
if($count['csv'] === false)
{
  fwrite(STDERR, "Es gab ein Problem bei dem Prüfen der Datei \"tweets.csv\" auf Medien-Verlinkungen.\r\n");
  exit(1);
}

fwrite(STDOUT, sprintf("Es wurden %d Übereinstimmungen gefunden.\r\n", $count['csv']));


// remove duplicates
fwrite(STDOUT, "Überprüfe Übereinstimmungen auf Duplikate.\r\n");

$matches['csv'] = array_unique($matches['csv'], SORT_REGULAR);
$count['csv_duplicates'] = $count['csv'] - count($matches['csv']);
$count['csv'] -= $count['csv_duplicates'];

fwrite(STDOUT, sprintf("Es wurden %d Duplikate gefunden und entfernt.\r\n", $count['csv_duplicates']));


// adjust max entries
if($config['max'] > $count['csv'])
{
  $config['max'] = $count['csv'];
}


fwrite(STDOUT, sprintf("Selektiere Einträge %d bis %d.\r\n", $config['min'], $config['max']));


for($csvKey = $config['min']; $csvKey < $config['max']; $csvKey++)
{
  $csvItem = $matches['csv'][$csvKey];

  fwrite(STDOUT, sprintf("[%d] Überprüfe URL \"%s\" auf Medien.\r\n", $csvKey, $csvItem[0]));

  $content['web'] = file_get_contents($csvItem[0]);
  if($content['web'] === false)
  {
    fwrite(STDERR, sprintf("[%d] Die URL \"%s\" wird übersprungen, da das Herunterladen fehlgeschlagen ist.\r\n", $csvKey, $csvItem[0]));
    continue;
  }

  $count['web'] = preg_match_all('/<meta[\ ]+property="og:image" content="(https:\/\/pbs.twimg.com\/media\/([^:]*)[^"]*)">/i', $content['web'], $matches['web'], PREG_SET_ORDER);
  if($count['web'] === false)
  {
    fwrite(STDERR, sprintf("[%d] Lesen der URL \"%s\" ist fehlgeschlagen. Überspringe URL.\r\n", $csvKey, $csvItem[0]));
    continue;
  }

  fwrite(STDOUT, sprintf("[%d] Es wurden %d Übereinstimmungen gefunden.\r\n", $csvKey, $count['web']));

  for($webKey = 0; $webKey < $count['web']; $webKey++)
  {
    $webItem = $matches['web'][$webKey];
    $webFilePath = $config['path']['downloads'] . $webItem[2];

    fwrite(STDOUT, sprintf("[%d][%d] Gefundene URL: \"%s\"\r\n", $csvKey, $webKey, $webItem[1]));

    if(file_exists($webFilePath))
    {
      fwrite(STDOUT, sprintf("[%d][%d] Datei \"%s\" wird nicht heruntergeladen, da diese bereits existiert.\r\n", $csvKey, $webKey, $webItem[2]));
      continue;
    }

    fwrite(STDOUT, sprintf("[%d][%d] Lade Datei \"%s\" herunter.\r\n", $csvKey, $webKey, $webItem[2]));

    $webFileContent = file_get_contents($webItem[1]);
    if($webFileContent === false)
    {
      fwrite(STDERR, sprintf("[%d][%d] Die URL \"%s\" wird übersprungen, da das Herunterladen fehlgeschlagen ist.\r\n", $csvKey, $webKey, $webItem[1]));
      continue;
    }

    if(!file_put_contents($webFilePath, $webFileContent))
    {
      fwrite(STDERR, sprintf("[%d][%d] Die Datei \"%s\" konnte nicht gespeichert werden.\r\n", $csvKey, $webKey, $webItem[2]));
      continue;
    }
  }
}
