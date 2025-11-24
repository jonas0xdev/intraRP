# Database Migration Guide

This document explains the database migration system and best practices for creating and managing migration files.

## Migration File Naming Convention

Migration files must follow strict naming conventions to ensure proper validation and execution.

### Standard Naming Pattern

```
<action>_<table_name>_<ddmmyyyy>.php
```

- **action**: The type of operation (create, alter, insert, update, etc.)
- **table_name**: The exact name of the database table being modified
- **ddmmyyyy**: Date stamp (day, month, year)

### Examples

✅ **Correct naming:**
- `create_intra_users_07062025.php` → Creates `intra_users` table
- `alter_intra_edivi_03092025.php` → Alters `intra_edivi` table
- `alter_intra_notifications_04112025.php` → Alters `intra_notifications` table

❌ **Incorrect naming:**
- `alter_intra_notifications_type_04112025.php` → Would try to alter non-existent `intra_notifications_type` table

## Migration Types

### Critical Operations (Validated)

These operations are validated before execution and will abort the migration process on failure:

1. **CREATE** - Creates new tables
   - Naming must match the table being created
   - Validation checks if table exists after migration runs

2. **ALTER** - Modifies existing tables
   - Naming must match the table being altered
   - Validation checks if table exists before attempting to alter
   - Most common source of naming errors

### Non-Critical Operations

These operations are not validated and won't abort the migration process on failure:

3. **INSERT** - Inserts data into tables
   - Can use descriptive names (e.g., `insert_intra_config_defaults_04112025.php`)
   - Table name in filename doesn't need to match exactly

4. **UPDATE** - Updates existing data
   - Should match table name when possible for clarity

### Special Files

Some migrations don't follow the standard convention because they perform multiple operations:

- `add_foreign_keys_07062025.php` - Adds foreign keys to multiple tables
- `migrate_existing_documents_30092025.php` - Data migration script
- `remove_lang_config_04112025.php` - Removes specific config entries

### Multi-Table Migrations

For migrations that create multiple tables in one file:

```php
// In database-init.php
['file' => 'create_intra_support_db_28102025.php', 'type' => 'create', 
 'tables' => ['intra_support_passwords', 'intra_support_sessions', 'intra_support_actions_log']],
```

The `'tables'` parameter specifies all tables to validate.

## Validation Tool

### Running Validation

Validate all migration files before committing changes:

```bash
# Using composer
composer db:validate

# Or directly
php setup/validate-migrations.php
```

### What Gets Validated

- All CREATE and ALTER migrations (critical operations)
- Checks that filename table name matches actual SQL statements
- Skips INSERT, UPDATE, and special files (non-critical)
- Handles multi-table migrations configured in database-init.php

### Example Output

```
=== Migration File Validation ===
Checking 58 migration files...

Checked 39 critical migration files (CREATE/ALTER)

✅ All critical migration files validated successfully!
   No naming mismatches found.
```

## Best Practices

1. **Always validate** before committing new migration files
2. **Follow naming conventions** strictly for CREATE and ALTER migrations
3. **Use descriptive names** for INSERT migrations to explain what data is being inserted
4. **Document special cases** when creating multi-table or special-purpose migrations
5. **Test locally** before pushing to ensure migrations run successfully

## Common Issues

### Issue: "Cannot alter table 'X' - table does not exist"

**Cause:** Migration filename doesn't match the actual table name in the SQL

**Example:**
- File: `alter_intra_notifications_type_04112025.php`
- SQL: `ALTER TABLE intra_notifications ...`
- Extracted table: `intra_notifications_type` (doesn't exist)

**Solution:** Rename file to match actual table name:
```bash
# Rename to match the table in the SQL
mv alter_intra_notifications_type_04112025.php alter_intra_notifications_04112025.php

# Update reference in setup/database-init.php
```

### Issue: Validation fails for multi-table migration

**Solution:** Add `'tables'` parameter in `database-init.php`:

```php
['file' => 'create_my_migration_28102025.php', 'type' => 'create',
 'tables' => ['table1', 'table2', 'table3']],
```

## Creating New Migrations

1. Create the migration file following the naming convention
2. Add the file to the `$migrationFiles` array in `setup/database-init.php`
3. Run validation: `composer db:validate`
4. Test the migration locally
5. Commit both the migration file and updated `database-init.php`

## Migration Execution Order

Migrations are executed in the order they appear in the `$migrationFiles` array in `database-init.php`. This allows for:
- Proper dependency management (tables created before being altered)
- Chronological organization by date
- Manual override of execution order when needed

## Troubleshooting

If a migration fails:

1. Check the error message for the specific issue
2. Verify the table name in the filename matches the SQL
3. Ensure all dependencies (referenced tables) exist
4. Check database permissions
5. Review the migration file for SQL syntax errors
6. Use `composer db:validate` to catch naming issues early
