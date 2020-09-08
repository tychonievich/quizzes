<?php 
require_once "tools.php";
if (!$isstaff) exit('must be staff to check grade status');

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

$fn = "cache/$_GET[qid]-$_GET[slug]-sync.json";
$fh = fopen($fn, "r+");
if (!$fh) fclose(fopen($fn, "w+"));
$fh = fopen($fn, "r+");
if (!$fh) die(json_encode(array($user=>$data)));
if (!flock($fh, LOCK_EX)) die(json_encode(array($user=>$data)));

$len = filesize($fn);
if ($len) {
    rewind($fh);
    $txt = fread($fh, $len);
    $everyone = json_decode($txt, true);
} else {
    $everyone = array();
}
$everyone[$user] = $data;
$txt = json_encode($everyone);
ftruncate($fh, 0);
rewind($fh);
fwrite($fh, $txt);
fflush($fh);
flock($fh, LOCK_UN);

$len = filesize($fn);

fclose($fh);
echo $txt;
?>
