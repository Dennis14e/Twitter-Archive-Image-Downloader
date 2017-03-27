<?php
echo "Importiere CSV...\r\n";
$csv = file_get_contents('tweets.csv');

echo "Überprüfe Tweets auf Medien-Verlinkungen...\r\n";
$csv_matches = array();
preg_match_all('/https:\/\/twitter\.com\/Dennis14e\/status\/([0-9]+)\/photo\/([0-9])/', $csv, $csv_matches, PREG_SET_ORDER);

echo "Entferne doppelte Einträge...\r\n";
$csv_matches = array_unique($csv_matches, SORT_REGULAR);
$count['csv_matches'] = count($csv_matches);

$min = 0;
$max = 50;
$max = ($count['csv_matches'] > $max) ? $max : $count['csv_matches'];

echo "Selektiere Einträge $min bis $max...\r\n";
$csv_sliced = array_slice($csv_matches, $min, $max);

for($c = $min; $c < count($csv_sliced); $c++)
{
  echo "[$c] Check URL \"" . $csv_sliced[$c][0] . "\"...\r\n";

  $web = file_get_contents($csv_sliced[$c][0]);

  $web_matches = array();
  preg_match_all('/<meta[\ ]+property="og:image" content="(https:\/\/pbs.twimg.com\/media\/([^:]*)[^"]*)">/i', $web, $web_matches, PREG_SET_ORDER);

  echo "[$c] Get " . count($web_matches) . " matches.\r\n";

  for($w = 0; $w < count($web_matches); $w++)
  {
    echo "[$c][$w] Download \"" . $web_matches[$w][2] . "\" from \"" . $web_matches[$w][1] . "\"\r\n";
    $filename = 'download/' . $web_matches[$w][2];

    if(file_exists($filename)) continue;

    $image = file_get_contents($web_matches[$w][1]);
    file_put_contents($filename, $image);
  }
}
