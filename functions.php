<?php
function taid_echo($stream, $format, ...$args)
{
  fwrite($stream, '[' . date('Y-m-d H:i:s') . '] ' . vsprintf($format, $args) . "\r\n");
}

function seconds2human($duration)
{
  $periods = array(
    'day'    => 86400,
    'hour'   => 3600,
    'minute' => 60,
    'second' => 1
  );

  $parts = array();
  if($duration < 1)
  {
    $duration = 1;
  }

  foreach($periods as $name => $dur)
  {
    $div = floor($duration / $dur);

    switch($div)
    {
      case 0:
        continue;
        break;

      case 1:
        $parts[] = $div . ' ' . $name;
        break;

      default:
        $parts[] = $div . ' ' . $name . 's';
        break;
    }

    $duration %= $dur;
  }

  $last = array_pop($parts);

  if(empty($parts))
  {
    return $last;
  }

  return join(', ', $parts) . ' and ' . $last;
}
