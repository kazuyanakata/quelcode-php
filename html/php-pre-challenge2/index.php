<?php
$array = explode(',', $_GET['array']);

// 修正はここから
$count = count($array);
for ($i = 0; $i < $count; $i++) {
  $min = min($array);
  $subArray[$i] = $min;
  $key = array_search($min , $array);
  unset($array[$key]);
}
$array = $subArray;
// 修正はここまで

echo "<pre>";
print_r($array);
echo "</pre>";
