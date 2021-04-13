<?php

require_once "authenticate.php";

if (!$_GET['qid'] || !$_GET['slug'] || strpos($_GET['qid'],'/') !== FALSE || strpos($_GET['slug'],'/') !== FALSE /*|| strpos($_GET['slug'],'-') !== FALSE*/) {
    http_response_code(403);
    die("Invalid request");
}

$path="log/$_GET[qid]/$user-$_GET[slug]";

if (!file_exists($path)) {
    http_response_code(404);
    die("No such image: $path");
}

$finfo = new finfo(FILEINFO_MIME);
$mime = $finfo->file($path);

header('Content-Type: '.$mime);

readfile($path);


?>
