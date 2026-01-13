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

**Step 3: Import Database Structure and Seed Initial Users**
Run the following two commands from your terminal.

1.  **Import the database structure:**
    ```bash
    ddev import-db --file=db.sql
    ```
2.  **Seed the database with initial users:** This command runs our secure PHP seeding script to create the `root` and `chairman` users with correct passwords.
    ```bash
    ddev exec php db_seed.php
    ```

**Step 4: Launch the Application**
Your system is now fully configured and ready to run. Use the following command to open the application in your default web browser:

```bash
ddev launch
```

### How to Log In

You can log in with either of the two users created during setup.

-   **Username:** `root` or `chairman`
-   **Password:** `password`

### A Note on Manual SQL Inserts

If you ever need to manually insert a user with a password hash directly via the command line, **you must wrap the SQL statement in single quotes (`'`)**. This prevents the shell from interpreting the `$` in the hash as a variable, which would corrupt the hash.

**Correct Way:**
```bash
ddev mysql -e 'INSERT INTO users ... VALUES ("...", "$2y$10$...");'
```

**Incorrect Way (will fail):**
```bash
ddev mysql -e "INSERT INTO users ... VALUES ("...", "$2y$10$...");"
```

### Useful DDEV Commands

-   **Stop the project:** `ddev stop`
-   **View live application logs:** `ddev logs -f`
-   **Get project details (URL, etc.):** `ddev describe`
-   **Delete the project containers and database:** `ddev delete` (use with care)
