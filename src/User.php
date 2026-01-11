<?php

class User
{
    private $pdo;

    public $id;
    public $account_no;
    public $username;
    public $email;
    public $phone;
    private $password_hash;
    public $role_id;
    public $avatar;
    public $created_at;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Finds a user by their username, email, or phone number.
     *
     * @param string $identifier The login identifier.
     * @return User|false The User object if found, otherwise false.
     */
    public function findByLoginIdentifier(string $identifier)
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM users WHERE username = :identifier OR email = :identifier OR phone = :identifier LIMIT 1'
        );
        $stmt->execute(['identifier' => $identifier]);
        $userData = $stmt->fetch();

        if ($userData) {
            $this->id = $userData['id'];
            $this->account_no = $userData['account_no'];
            $this->username = $userData['username'];
            $this->email = $userData['email'];
            $this->phone = $userData['phone'];
            $this->password_hash = $userData['password'];
            $this->role_id = $userData['role_id'];
            $this->avatar = $userData['avatar'];
            $this->created_at = $userData['created_at'];
            return $this;
        }

        return false;
    }

    /**
     * Verifies the user's password.
     *
     * @param string $password The plain-text password.
     * @return bool True if the password is correct, false otherwise.
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password_hash);
    }

    /**
     * Registers a new user in the database, safely handling auto-incrementing IDs.
     *
     * @param array $data User data (username, email, phone, password, role_id).
     * @return bool True on success, false on failure.
     */
    public function register(array $data): bool
    {
        $this->pdo->beginTransaction();

        try {
            // Check for existing user by username, email, or phone
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE username = :username OR email = :email OR phone = :phone');
            $stmt->execute(['username' => $data['username'], 'email' => $data['email'], 'phone' => $data['phone']]);
            if ($stmt->fetch()) {
                // User already exists
                $this->pdo->rollBack();
                return false;
            }

            $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

            // Insert user with a temporary placeholder account number
            $tempAccountNo = 'TEMP_' . uniqid();

            $stmt = $this->pdo->prepare(
                'INSERT INTO users (account_no, username, email, phone, password, role_id)
                 VALUES (:account_no, :username, :email, :phone, :password, :role_id)'
            );

            $stmt->execute([
                'account_no' => $tempAccountNo,
                'username' => $data['username'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $passwordHash,
                'role_id' => $data['role_id'] ?? 5, // Default to 'Member' role
            ]);

            // Get the last inserted ID, which is guaranteed to be unique
            $lastId = $this->pdo->lastInsertId();

            // Generate the permanent, formatted account number
            $permanentAccountNo = sprintf('POA%05d', $lastId);

            // Update the user record with the permanent account number
            $updateStmt = $this->pdo->prepare('UPDATE users SET account_no = :account_no WHERE id = :id');
            $updateStmt->execute([
                'account_no' => $permanentAccountNo,
                'id' => $lastId
            ]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            // In a real application, you would log this error.
            // error_log($e->getMessage());
            return false;
        }
    }
}
