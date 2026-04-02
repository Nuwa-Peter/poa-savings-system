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

**Step 3: Run Database Migrations and Seed Initial Users**
Run the following commands from your terminal to set up your database schema and initial data.

1.  **Install dependencies:**
    ```bash
    ddev composer install
    ```
2.  **Run Migrations:** This will create the necessary tables using Phinx.
    ```bash
    ddev exec vendor/bin/phinx migrate
    ```
3.  **Seed the database:** This command runs our secure PHP seeding script to create the `root` and `chairman` users with correct passwords.
    ```bash
    ddev exec php db_seed.php
    ```

> **Note:** The `db.sql` file is obsolete and should not be used for new installations. Always use migrations.

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

## Database Migrations with Phinx

We use Phinx to manage database schema changes. This ensures that everyone has the same database structure without losing any existing data.

### 1. Pre-Migration Safety (Snapshot)
Before running any migrations on a database with important information, it is highly recommended to take a snapshot:
```bash
ddev snapshot --name pre_migration_$(date +%Y%m%d_%H%M%S)
```

### 2. Running Migrations
To apply any new schema changes, you can use our helper script which automatically takes a snapshot for safety before migrating:
```bash
./db_migrate.sh
```

Alternatively, you can run the command directly:
```bash
ddev exec vendor/bin/phinx migrate
```
Phinx will automatically keep track of which migrations have already been run and only apply new ones.

### 3. Checking Migration Status
To see which migrations have been applied and which are pending:
```bash
ddev exec vendor/bin/phinx status
```

### 4. Rolling Back
If a migration causes issues, you can undo the last one with:
```bash
ddev exec vendor/bin/phinx rollback
```

### 5. Creating a New Migration
If you need to change the database schema, create a new migration file:
```bash
ddev exec vendor/bin/phinx create MyNewMigrationName
```
Then edit the generated file in `db/migrations/`.
