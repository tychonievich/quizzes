<?php

$staff = array("lat7h", "acz2npn","nqs2ez","zbh4m","ejf9kwf","shm3px","jh4yr","am3cs","ns8nf");

$homepage = "https://www.cs.virginia.edu/luther/DMT1/";

$extra = array(
    # student => extra time portion
    'jnt3xv' => 1.5,
    'maf3wc' => 1.5,
    'cas8ds' => 1.5,
    'lsb3fg' => 1.5,
);

$quizignore_by_student = array(
    # student => number to drop
);

$quizadjust_by_student_quiz = array(
);

$quizadjust_silent_by_student_quiz = array(
);

if (php_sapi_name() == "cli") {
    parse_str(implode('&', array_slice($argv, 1)), $_GET);
    $_SERVER['REQUEST_URI'] = '.';
    $user = 'mst3k'; // UVA's reserved "example use only" shibboleth username
    $isstaff = true;
} else {
    $user = $_SERVER['PHP_AUTH_USER']; 
    $isstaff = in_array($user, $staff);
}


if ($isstaff && array_key_exists('asuser', $_GET)) { $user = $_GET['asuser']; }
?>
