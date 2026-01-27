<?php
require_once 'includes/auth_check.php';
require_once 'config/db_connect.php';
require_once 'config/config.php';
require_once 'vendor/autoload.php';

use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;

header('Content-Type: application/json');

try {
    $wa = new WebAuthn(WEBAUTHN_RELYING_PARTY_NAME, WEBAUTHN_RELYING_PARTY_ID);

    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];

    $create_args = $wa->getCreateArgs($user_id, $username, $username);

    // The challenge is stored in the session to be verified in the next step
    $_SESSION['webauthn_challenge'] = $wa->getChallenge();

    echo json_encode($create_args);

} catch (WebAuthnException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
