<?php
include_once "tools.php";
array_shift($argv);
$dump = array();
foreach($argv as $fn) {
    $dump[$fn] = qparse($fn, TRUE);
}
echo json_encode($dump, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
?>
