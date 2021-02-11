<?php 
require_once "tools.php";

$data = json_decode(file_get_contents("php://input"), true);
if (!$data || !isset($data['qid']) ||  !isset($data['question']) || !isset($data['up'])) {
    http_response_code(404);
    echo 'page requires POST data: '.json_encode($data);
    exit;
}
if ($data['user'] && $isstaff) { $user = $data['user']; } 

$qid = $data['qid'];
if (strpos($qid,'/') !== FALSE || strpos($qid, "..") !== FALSE) {
    http_response_code(403);
    echo 'invalid quiz: '.json_encode($qid);
    exit;
}

$qobj = qparse("review/$qid.md", TRUE);
if (isset($qobj['error'])) {
    http_response_code(403);
    echo 'invalid quiz: '.json_encode($qid)."\n".$qobj['error'];
    exit;
}

$path = "review/$qid.md.votes";

$fh = fopen($path, "r+");
if (!$fh) { fclose(fopen($path, "w+")); $fh = fopen($path, "r+"); }
if (!$fh) die("unable to open $qid votes file");

if (!flock($fh, LOCK_EX)) die("unable to acquire reader lock for $qid");
$len = filesize($path);
if ($len) {
    rewind($fh);
    $votes = json_decode(fread($fh, $len), true);
} else {
    $votes = array();
}

if (!isset($votes[$data['question']])) $votes[$data['question']] = array();
$votes[$data['question']][$user] = $data['up'] ? 1 : 0;

rewind($fh);
ftruncate($fh, 0);
fwrite($fh, json_encode($votes));
fflush($fh);

flock($fh, LOCK_UN);

?>
﻿
