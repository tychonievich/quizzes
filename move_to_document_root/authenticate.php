<?php

require __DIR__ . '/vendor/autoload.php';

use League\OAuth2\Client\Provider\Google;

session_start(); // Remove if session.auto_start=1 in php.ini

$provider = new Google([
    'clientId'     => replce with clientID from https://console.cloud.google.com/apis/credentials,
    'clientSecret' => replce with clientSecret from https://console.cloud.google.com/apis/credentials,
    'redirectUri'  => http://you.domain.name/authenticate.php,
]);

if (!empty($_GET['error'])) {

    // Got an error, probably user denied access
    exit('Got error: ' . htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'));

} elseif (empty($_GET['code'])) {

    // If we don't have an authorization code then get one
    $authUrl = $provider->getAuthorizationUrl();
    $_SESSION['oauth2state'] = $provider->getState();
    header('Location: ' . $authUrl);
    exit;

} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {

    // State is invalid, possible CSRF attack in progress
    unset($_SESSION['oauth2state']);
    exit('Invalid state');

} else {

    // Try to get an access token (using the authorization code grant)
    $token = $provider->getAccessToken('authorization_code', [
        'code' => $_GET['code']
    ]);

    // Optional: Now you have a token you can look up a users profile data
    try {

        // We got an access token, let's now get the owner details
        $ownerDetails = $provider->getResourceOwner($token)->toArray();
        $email = $ownerDetails['email'];
        if (strstr($email, "@") != "@illinois.edu") {
            ?>
            <p>You need to log in with an @illinois.edu account for this site.</p>
            <p>If that wasn't an option but you are part of UIUC, you may need to <a href="https://answers.uillinois.edu/illinois/page.php?id=47599">enable Google Apps @ Illinois</a>.</p>
            <p><a href="authenticate.php">Click here to try logging in again</a></p>
            <?php
            exit;
        }
        $_SESSION['user'] = substr($email, 0, strpos($email,'@'));
        $_SESSION['details'] = $ownerDetails;
        require_once "check_rosters.php";
        if (isset($_SESSION['redirect'])) {
            header("Location: $_SESSION[redirect]");
            exit;
        }
        ?>
        <p>Welcome, <?=$ownerDetails['name']?>! You are successfully logged in.</p>
        <p><a href="./">Click here</a> to view the main index of this site.</p>
        <?php

    } catch (Exception $e) {

        // Failed to get user details
        exit('Something went wrong: ' . $e->getMessage());

    }
}
?>
