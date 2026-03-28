<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Baseline extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE `withdrawals` (
              `id` int(11) NOT NULL,
              `user_id` int(11) NOT NULL,
              `amount` decimal(10,2) NOT NULL,
              `status` varchar(255) NOT NULL DEFAULT 'pending',
              `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `processed_at` timestamp NULL DEFAULT NULL,
              `processed_by_user_id` int(11) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        $this->execute("
            ALTER TABLE `withdrawals`
              ADD PRIMARY KEY (`id`),
              ADD KEY `user_id` (`user_id`),
              ADD KEY `processed_by_user_id` (`processed_by_user_id`);
        ");

        $this->execute("
            ALTER TABLE `withdrawals`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
        ");

        $this->execute("
            CREATE TABLE `loans` (
              `id` int(11) NOT NULL,
              `user_id` int(11) NOT NULL,
              `amount` decimal(10,2) NOT NULL,
              `balance` decimal(10,2) NOT NULL,
              `interest_rate` decimal(4,2) NOT NULL DEFAULT 2.00,
              `status` varchar(255) NOT NULL DEFAULT 'pending',
              `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `approved_at` timestamp NULL DEFAULT NULL,
              `approved_by_user_id` int(11) DEFAULT NULL,
              `due_date` date DEFAULT NULL,
              `last_interest_applied_at` timestamp NULL DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        $this->execute("
            ALTER TABLE `loans`
              ADD PRIMARY KEY (`id`),
              ADD KEY `user_id` (`user_id`),
              ADD KEY `approved_by_user_id` (`approved_by_user_id`);
        ");

        $this->execute("
            ALTER TABLE `loans`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
        ");

        $this->execute("
            CREATE TABLE `loan_payments` (
              `id` int(11) NOT NULL,
              `loan_id` int(11) NOT NULL,
              `amount` decimal(10,2) NOT NULL,
              `paid_at` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        $this->execute("
            ALTER TABLE `loan_payments`
                ADD PRIMARY KEY (`id`),
                ADD KEY `loan_id` (`loan_id`);
        ");

        $this->execute("
            ALTER TABLE `loan_payments`
                MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
        ");

        $this->execute("
            CREATE TABLE `loan_guarantors` (
              `id` int(11) NOT NULL,
              `loan_id` int(11) NOT NULL,
              `guarantor_id` int(11) NOT NULL,
              `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
              `responded_at` timestamp NULL DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        $this->execute("
            ALTER TABLE `loan_guarantors`
              ADD PRIMARY KEY (`id`),
              ADD UNIQUE KEY `loan_guarantor_unique` (`loan_id`,`guarantor_id`),
              ADD KEY `guarantor_id` (`guarantor_id`);
        ");

        $this->execute("
            ALTER TABLE `loan_guarantors`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
        ");

        $this->execute("
            CREATE TABLE `logs` (
              `id` int(11) NOT NULL,
              `user_id` int(11) DEFAULT NULL,
              `action` text DEFAULT NULL,
              `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        $this->execute("
            ALTER TABLE `logs`
              ADD PRIMARY KEY (`id`);
        ");

        $this->execute("
            ALTER TABLE `logs`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
        ");

        $this->execute("
            CREATE TABLE `notifications` (
              `id` int(11) NOT NULL,
              `user_id` int(11) DEFAULT NULL,
              `message` text DEFAULT NULL,
              `is_read` tinyint(1) DEFAULT 0,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        $this->execute("
            ALTER TABLE `notifications`
              ADD PRIMARY KEY (`id`);
        ");

        $this->execute("
            ALTER TABLE `notifications`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
        ");

        $this->execute("
            CREATE TABLE `savings` (
              `id` int(11) NOT NULL,
              `user_id` int(11) DEFAULT NULL,
              `amount` decimal(10,2) DEFAULT NULL,
              `verified_by_user_id` int(11) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        $this->execute("
            ALTER TABLE `savings`
              ADD PRIMARY KEY (`id`);
        ");

        $this->execute("
            ALTER TABLE `savings`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
        ");

        $this->execute("
            CREATE TABLE `users` (
              `id` int(11) NOT NULL,
              `account_no` varchar(255) NOT NULL,
              `first_name` varchar(100) NOT NULL,
              `surname` varchar(100) NOT NULL,
              `username` varchar(255) NOT NULL,
              `email` varchar(255) NOT NULL,
              `phone` varchar(20) DEFAULT NULL,
              `password` varchar(255) NOT NULL,
              `role_id` int(11) DEFAULT NULL,
              `avatar` varchar(255) DEFAULT NULL,
              `status` ENUM('active', 'deleted') NOT NULL DEFAULT 'active',
              `created_at` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        $this->execute("
            ALTER TABLE `users`
              ADD PRIMARY KEY (`id`),
              ADD UNIQUE KEY `account_no` (`account_no`);
        ");

        $this->execute("
            ALTER TABLE `users`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
        ");

        $this->execute("
            CREATE TABLE `password_resets` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) NOT NULL,
              `token` varchar(255) NOT NULL,
              `expires_at` datetime NOT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `user_id` (`user_id`),
              CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        $this->execute("
            CREATE TABLE `system_settings` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `setting_key` varchar(255) NOT NULL,
              `setting_value` text DEFAULT NULL,
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `setting_key` (`setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        $this->execute("
            INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
            ('last_interest_run', NULL);
        ");

        $this->execute("
            CREATE TABLE `savings_log` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `saving_id` int(11) NOT NULL,
              `admin_id` int(11) NOT NULL,
              `old_amount` decimal(10,2) NOT NULL,
              `new_amount` decimal(10,2) NOT NULL,
              `reason` text NOT NULL,
              `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `saving_id` (`saving_id`),
              KEY `admin_id` (`admin_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        $this->execute("
            CREATE TABLE `webauthn_credentials` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) NOT NULL,
              `credential_id` varchar(255) NOT NULL,
              `public_key` text NOT NULL,
              `attestation_object` text,
              `user_agent` varchar(255),
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `credential_id` (`credential_id`),
              KEY `user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
    }

    public function down(): void
    {
        $this->execute("DROP TABLE `withdrawals`");
        $this->execute("DROP TABLE `loans`");
        $this->execute("DROP TABLE `loan_payments`");
        $this->execute("DROP TABLE `loan_guarantors`");
        $this->execute("DROP TABLE `logs`");
        $this->execute("DROP TABLE `notifications`");
        $this->execute("DROP TABLE `savings`");
        $this->execute("DROP TABLE `users`");
        $this->execute("DROP TABLE `password_resets`");
        $this->execute("DROP TABLE `system_settings`");
        $this->execute("DROP TABLE `savings_log`");
        $this->execute("DROP TABLE `webauthn_credentials`");
    }
}
