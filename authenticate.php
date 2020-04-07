<?php

$metadata = json_decode(file_get_contents('course.json'), TRUE);

if (php_sapi_name() == "cli") { // let run from commandline for testing
    parse_str(implode('&', array_slice($argv, 1)), $_GET);
    $_SERVER['REQUEST_URI'] = '.';
    $user = 'mst3k'; // UVA's reserved "example use only" shibboleth username
    $isstaff = true;
} else if ($_SERVER['SERVER_PORT'] == 8080) {
    $user = 'mst3k'; // UVA's reserved "example use only" shibboleth username
    $isstaff = true;
} else {
    $user = $_SERVER['PHP_AUTH_USER']; 
    $isstaff = in_array($user, $metadata['staff']);
}
if ($isstaff && array_key_exists('asuser', $_GET)) { $user = $_GET['asuser']; }

?>
