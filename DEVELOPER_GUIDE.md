# Developer Guide: Adding New Features

Welcome to the POA Savings platform developer guide. This document outlines the architecture and the standard process for extending the system with new features.

## Architecture Overview
- **Backend:** PHP 8.x with PDO for database interactions.
- **Database:** MySQL/MariaDB, managed via **Phinx** migrations.
- **Frontend:** Tailwind CSS for styling, Lucide for icons, and Chart.js for data visualization.
- **JavaScript:** `assets/js/main.js` handles global interactive components (toasts, currency masking, responsive sidebar).
- **Security:** Standardized use of prepared statements and `htmlspecialchars` for output.

## Feature Implementation Workflow

Follow these steps to add a new module or feature:

### 1. Database Schema (`db/migrations/`)
If your feature requires new tables or columns, use Phinx:
- Create a migration: `ddev exec vendor/bin/phinx create MyNewFeature`
- Define the schema in the `change()` method.
- Apply: `ddev exec vendor/bin/phinx migrate`

### 2. Backend Logic (`includes/`)
- Place reusable logic, helper classes, or calculation engines in the `includes/` directory.
- Example: `includes/CreditScoreHelper.php`.

### 3. Action Handlers (Root Directory)
- Create processing scripts for form submissions (e.g., `process_new_feature.php`).
- **Security Checklist:**
    - Include `auth_check.php` and `check_permissions()`.
    - Use `str_replace(',', '', $amount)` for masked currency inputs.
    - Use prepared statements for all queries.
    - Redirect back with `?success=` or `?error=` parameters.

### 4. Frontend Pages (Root Directory)
- Use `templates/header.php` and `templates/footer.php`.
- Use Tailwind classes for a modern, responsive look.
- For data tables, add `data-interactive="true"` and a `data-search-input` ID to enable automatic search and pagination.

### 5. UI Components (`includes/ui_components.php`)
- Use existing components like `renderEmptyState()` for consistency.
- Add new Lucide icons via `<i>` tags; the system initializes them automatically.

### 6. Navigation (`templates/header.php`)
- Add your new page to the sidebar navigation.
- Use a relevant Lucide icon.

### 7. Mobile Optimization
- Ensure your page works on mobile.
- Use `isMobileDevice()` from `includes/user_functions.php` for conditional rendering if a specialized mobile view is needed.

## Example: Adding "Export to CSV"
1. Create `export_savings.php`.
2. Implement CSV headers and loop through database results.
3. Add a link in `view_savings.php` using the "Secondary Button" style.
4. Verify on both Desktop and Mobile.
