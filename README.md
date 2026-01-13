# POA Savings Management System

This is a group savings management system built with PHP.

## Local Development Setup with DDEV

This guide will help you set up and run the project on your local machine using DDEV. This is tested on Linux but should work on macOS and Windows with WSL2.

### Prerequisites

- **DDEV:** You must have DDEV installed, along with its dependencies (Docker, etc.). If you don't have it, you can install it by following the [official DDEV installation guide](https://ddev.readthedocs.io/en/latest/users/install/ddev-installation/).

### Setup Instructions

**Step 1: Configure and Start DDEV**
From your project's root directory in your terminal, run the following command. This will configure the project as a standard PHP application and start the web and database containers.

```bash
ddev config --project-type php && ddev start
```

**Step 2: Update Database Connection**
The application needs to know how to connect to the database provided by DDEV. Open the file `config/db_connect.php` and replace the existing credentials with the following:

```php
<?php
// Database configuration for DDEV
$host = 'db';
$dbname = 'db';
$username = 'db';
$password = 'db';

try {
    // ... rest of the file remains the same
```

**Step 3: Import Database and Create Initial Users**
Run this single command from your terminal. It performs two critical actions:
1.  `ddev import-db`: It imports the application's table structure from the `db.sql` file.
2.  `ddev ssh ... mysql -e`: It connects to the database and runs a SQL command to create the two essential starting users: the hidden **root** user and a default **chairman**.

```bash
ddev import-db --file=db.sql && ddev ssh -s web --mysql -e "INSERT INTO users (id, account_no, username, email, password, role_id) VALUES (1, 'POA00000', 'root', 'root@poa.dev', '\$2y\$10\$JA4iSiIs3dk/p3UK4fx5XefzABMZ9ccEIVzJ1jAYYuDCKs1Gww.tq', 1), (2, 'POA00001', 'chairman', 'chairman@poa.dev', '\$2y\$10\$JA4iSiIs3dk/p3UK4fx5XefzABMZ9ccEIVzJ1jAYYuDCKs1Gww.tq', 2);"
```

**Step 4: Launch the Application**
Your system is now fully configured and ready to run. Use the following command to open the application in your default web browser:

```bash
ddev launch
```

### How to Log In

You can log in with either of the two users created during setup. They both use the same default password.

**Root User (Superuser):**
-   **Username:** `root`
-   **Password:** `password`

**Chairman User:**
-   **Username:** `chairman`
-   **Password:** `password`

### Useful DDEV Commands

-   **Stop the project:** `ddev stop`
-   **View live application logs:** `ddev logs -f`
-   **Get project details (URL, etc.):** `ddev describe`
-   **Delete the project containers and database:** `ddev delete` (use with care)
