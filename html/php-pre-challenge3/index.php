<?php
$limit = $_GET['target'];

$dsn = 'mysql:dbname=test;host=mysql';
$dbuser = 'test';
$dbpassword = 'test';

try {
  $db = new PDO ($dsn , $dbuser , $dbpassword);
} catch (PDOException $e) {
  http_response_code(500);
  exit();
}

// データベースから数値を取り出す
$numbers = $db->prepare('SELECT value FROM prechallenge3 where (value <= :limit) order by value asc');
$numbers->bindValue(':limit', $limit, PDO::PARAM_INT);
$numbers->execute();
while ($number = $numbers->fetch()) {
  $num[] = $number['value'];
}
if (is_numeric($limit) && $limit >= 1 && !preg_match("/^0/",$limit) && !preg_match("/[.]/",$limit)){
  for($j=0;$j < 2**8-1 ;$j++){
    $str[$j] = str_split(sprintf('%08d',decbin($j + 1)));
    for($k=0;$k <= 7;$k++){
      if((int)$str[$j][$k] === 1){
        $sum[$j][] = $num[$k];
      } 
    }
    if(array_sum($sum[$j]) === (int)$limit){
      $array[] = $sum[$j];
    }
  }
  if(!isset($array)){
    $array = [];
  }
  echo json_encode($array,JSON_NUMERIC_CHECK);
} else {
  http_response_code(400);
  exit();
}
