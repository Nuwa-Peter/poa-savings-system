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

    // Get the session challenge
    $challenge = $_SESSION['webauthn_challenge'] ?? null;
    if (!$challenge) {
        throw new Exception('Challenge not found in session.');
    }

    $client_data_json = $_POST['clientDataJSON'] ?? '';
    $attestation_object = $_POST['attestationObject'] ?? '';
    $user_id = $_SESSION['user_id'];

    // Process the registration
    $data = $wa->processCreate($client_data_json, $attestation_object, $challenge);

    // Save the new credential to the database
    $stmt = $pdo->prepare(
        "INSERT INTO webauthn_credentials (user_id, credential_id, public_key, attestation_object, user_agent) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $user_id,
        $data->credentialId,
        $data->credentialPublicKey,
        $attestation_object,
        $_SERVER['HTTP_USER_AGENT']
    ]);

    unset($_SESSION['webauthn_challenge']);

    echo json_encode(['success' => true, 'message' => 'Device registered successfully.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
