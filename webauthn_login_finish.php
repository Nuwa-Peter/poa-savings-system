<?php
session_start();
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
    echo json_encode(['success' => false, 'message' => 'WebAuthn library not found. Please run "ddev composer install".']);
    exit;
}

try {
    $wa = new WebAuthn(WEBAUTHN_RELYING_PARTY_NAME, WEBAUTHN_RELYING_PARTY_ID);

    $challenge = $_SESSION['webauthn_challenge'] ?? null;
    if (!$challenge) {
        throw new Exception('Challenge not found in session.');
    }

    $client_data_json = $_POST['clientDataJSON'] ?? '';
    $authenticator_data = $_POST['authenticatorData'] ?? '';
    $signature = $_POST['signature'] ?? '';
    $user_handle = $_POST['userHandle'] ?? '';
    $id = $_POST['id'] ?? '';

    // Fetch the credential from the database
    $stmt = $pdo->prepare("SELECT * FROM webauthn_credentials WHERE credential_id = ?");
    $stmt->execute([$id]);
    $credential = $stmt->fetch();

    if (!$credential) {
        throw new Exception('Credential not found.');
    }

    // Process the login
    $wa->processGet($client_data_json, $authenticator_data, $signature, $credential['public_key'], $challenge, null, $user_handle);

    // Log the user in
    $user_id = $credential['user_id'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role_id'] = $user['role_id'];

        unset($_SESSION['webauthn_challenge']);
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('User not found.');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
