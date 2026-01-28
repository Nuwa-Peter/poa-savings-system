<?php
require_once 'config/db_connect.php';
require_once 'config/config.php';

if (!file_exists('vendor/autoload.php')) {
    echo json_encode(['error' => 'Dependencies not installed. Please run "ddev composer install".']);
    exit;
}
require_once 'vendor/autoload.php';

use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;

header('Content-Type: application/json');

if (!class_exists('lbuchs\WebAuthn\WebAuthn')) {
    echo json_encode(['error' => 'WebAuthn library not found. Please run "ddev composer install".']);
    exit;
}

try {
    $wa = new WebAuthn(WEBAUTHN_RELYING_PARTY_NAME, WEBAUTHN_RELYING_PARTY_ID);

    // Get all credential IDs for the user
    // In a real application, you would first ask for the username
    // and then fetch the credentials for that user.
    // For simplicity, we are fetching all credentials here.
    $stmt = $pdo->query("SELECT credential_id FROM webauthn_credentials");
    $credential_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($credential_ids)) {
        throw new Exception('No biometric credentials found in the system.');
    }

    $get_args = $wa->getGetArgs($credential_ids);

    // The challenge is stored in the session to be verified in the next step
    $_SESSION['webauthn_challenge'] = $wa->getChallenge();

    echo json_encode($get_args);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
