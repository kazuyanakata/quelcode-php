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
$numbers = $db -> query('SELECT * FROM prechallenge3');
$i = 0;
while($number = $numbers -> fetch()){
  $num[$i] = $number['value'];
  $i++;
}
if (is_numeric($limit) && $limit >= 1 && !preg_match("/^0/",$limit) && !preg_match("/[.]/",$limit)){
  for($j=0;$j < 2**8-1 ;$j++){
    $str[$j] = str_split(sprintf('%08d',decbin($j + 1)));
    for($k=0;$k <= 7;$k++){
      if($str[$j][$k] == 1){
        $sum[$j][] = $num[$k];
      } 
    }
    if(array_sum($sum[$j]) == $limit){
      $array[] = $sum[$j];
    }
  }
  if(isset($array)){
    echo json_encode($array,JSON_NUMERIC_CHECK);
  } else {
    echo '[ ]';
  }
} else {
  http_response_code(400);
  exit();
}
?>