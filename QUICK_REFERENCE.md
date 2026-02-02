# Moodle Code Standards - Quick Reference Card

## Essential Commands

```powershell
# Navigate to plugin directory
cd "C:\Users\yaron\My Drive\ELANTIS\Gamification Plugin\Moodle Version\Test Versions\ascend_rewards"

# Set PHP alias (add to $PROFILE for permanent)
$env:Path += ";C:\laragon\bin\php\php-8.2.28-nts-Win32-vs16-x64"

# Run the checker
php check_standards.php

# Auto-fix issues (BACKUP FIRST!)
php auto_fix_standards.php

# Check specific file
php check_standards.php classes/

# Install composer packages
C:\laragon\bin\composer\composer.bat install

# Run phpcs on single file
.\vendor\bin\phpcs file.php --standard=moodle
```

## Code Standards Summary

| Standard | Correct | Incorrect |
|----------|---------|-----------|
| Quotes | `'string'` | `"string"` |
| Indentation | 4 spaces | Tabs |
| Variable | `$myVar` | `$my_var` |
| Function | `function_name()` | `functionName()` |
| Class | `ClassName` | `class_name` |
| Constant | `MY_CONST` | `my_const` |
| If statement | `if ($x) {` | `if($x){` |
| Function call | `get_string()` | `getString()` |

## Critical Code Blocks

### File Header (REQUIRED)
```php
<php
// This file is part of Moodle - https://moodle.org/
// ... GPL license block ...
defined('MOODLE_INTERNAL') || die();
```

### Function Documentation (REQUIRED)
```php
/**
 * Short description.
 *
 * @param type $param Description
 * @return type Description
 */
function my_function($param) {
}
```

### Capability Check (REQUIRED)
```php
require_login();
require_capability('capability:name', context_system::instance());
```

### Input Validation (REQUIRED)
```php
$userid = required_param('userid', PARAM_INT);
$email = required_param('email', PARAM_EMAIL);
$text = optional_param('text', '', PARAM_TEXT);
```

### Output Escaping (REQUIRED)
```php
echo s($user->firstname);           // Attribute context
echo format_string($text);           // String from DB
echo format_text($content, FORMAT_HTML); // Rich text
echo json_encode($data);             // JSON output
```

### Database Access (REQUIRED)
```php
// Get record
$record = $DB->get_record('table', array('id' => $id));

// Get records
$records = $DB->get_records('table', array('active' => 1));

// Execute query
$sql = 'SELECT * FROM {table} WHERE id = ';
$record = $DB->get_record_sql($sql, array($id));

// Insert
$record = new stdClass();
$record->name = 'value';
$id = $DB->insert_record('table', $record);

// Update
$record->id = $id;
$record->name = 'new value';
$DB->update_record('table', $record);

// Delete
$DB->delete_records('table', array('id' => $id));
```

## Issues Quick Fix Guide

### Quote Errors (Most Common)
```php
// FIND & REPLACE in most files:
"text" → 'text'
"value" → 'value'
"key" → 'key'

// KEEP double quotes for:
"Variable: $x"
"With {$expression}"
```

### Missing MOODLE_INTERNAL
Add after file header if missing:
```php
defined('MOODLE_INTERNAL') || die();
```

### Silenced Errors (@)
Remove @ operator and add proper handling:
```php
// WRONG:
$result = @some_function();

// RIGHT:
$result = some_function();
```

### Long Lines
Break after 120 chars:
```php
// WRONG (150 chars):
$record = $DB->get_record('table', array('id' => $id, 'status' => 'active', 'deleted' => 0));

// RIGHT:
$record = $DB->get_record(
    'table',
    array('id' => $id, 'status' => 'active', 'deleted' => 0)
);
```

## Validation Parameters

| Parameter | Use | Example |
|-----------|-----|---------|
| PARAM_INT | Integer | `required_param('id', PARAM_INT)` |
| PARAM_FLOAT | Float | `required_param('price', PARAM_FLOAT)` |
| PARAM_ALPHA | Letters only | `required_param('code', PARAM_ALPHA)` |
| PARAM_TEXT | Text | `required_param('name', PARAM_TEXT)` |
| PARAM_EMAIL | Email | `required_param('email', PARAM_EMAIL)` |
| PARAM_URL | URL | `required_param('url', PARAM_URL)` |
| PARAM_FILE | Filename | `required_param('file', PARAM_FILE)` |
| PARAM_PATH | Path | `required_param('path', PARAM_PATH)` |
| PARAM_BOOL | Boolean | `required_param('flag', PARAM_BOOL)` |

## Common Capabilities

```php
// Check site admin
is_siteadmin();

// Check system capability
has_capability('moodle/site:config', context_system::instance());

// Check course capability
has_capability('moodle/course:manageactivities', context_course::instance($courseid));

// Check activity capability
has_capability('mod/forum:createpost', context_module::instance($cmid));

// Require capability (throws exception if denied)
require_capability('moodle/site:viewparticipants', context_system::instance());
```

## Moodle Constants

```php
// Maturity levels
MATURITY_ALPHA
MATURITY_BETA
MATURITY_RC
MATURITY_STABLE

// User roles
$CFG->studentroleid  // Student role ID
$CFG->defaultuserroleid  // Default user role

// Formats
FORMAT_HTML
FORMAT_PLAIN
FORMAT_MARKDOWN
FORMAT_MOODLE
FORMAT_JSON
```

## String Functions

```php
// Get translatable string
get_string('key', 'local_ascend_rewards');

// Format string (safe for output)
format_string($text);

// Sanitize output
s($text);  // For attributes/plain text

// Format rich text
format_text($text, FORMAT_HTML, array('context' => $context));
```

## Debugging

```php
// Enable debugging in development
define('DEBUG_DEVELOPER', true);
$CFG->debug = DEBUG_DEVELOPER;
$CFG->debugdisplay = true;

// Log a message
debugging('Your debug message', DEBUG_TRACE);

// Log an error
debugging('Error occurred', DEBUG_DEVELOPER);
```

## Time Functions

```php
// Current timestamp
time();

// User timezone
usertimezone();  // Get user's timezone

// Format time
userdate($timestamp);  // Format for user's timezone
```

## File Functions

```php
// Get file contents
$content = file_get_contents($filepath);

// Write file
file_put_contents($filepath, $content);

// Check file exists
file_exists($filepath);

// File in Moodle files
get_file_contents('admin/templates/actions.html');

// Plugin directory
__DIR__  // Current directory
dirname(__FILE__)  // Directory of this file
```

## Useful Constants

```php
MOODLE_INTERNAL  // Moodle is loaded
PHP_OS_FAMILY  // OS family (Windows, Linux, etc.)
PHP_VERSION  // PHP version
__FILE__  // Current file path
__DIR__  // Current directory
__LINE__  // Current line number
__CLASS__  // Current class name
__FUNCTION__  // Current function name
__METHOD__  // Current method name
```

## Quick Moodle Check List

- [ ] All files have proper header
- [ ] All files have `defined('MOODLE_INTERNAL')`
- [ ] All functions documented with PHPDoc
- [ ] No silenced errors (@)
- [ ] No bare die()/exit()
- [ ] All input validated
- [ ] All output escaped
- [ ] No deprecated functions
- [ ] All capabilities defined
- [ ] Version numbers incremented
- [ ] Database XML files valid
- [ ] Language strings in lang file

## Moodle Version Requirements

```php
// For Moodle 4.5
$plugin->requires  = 2022041900;

// For Moodle 4.4
$plugin->requires  = 2022041900;

// For Moodle 4.3
$plugin->requires  = 2022041900;

// For Moodle 4.1
$plugin->requires  = 2021110900;
```

## Resources

- Docs: https://docs.moodle.org
- Dev Guide: https://moodle.org/development
- Code Standards: https://moodle.org/dev/Coding_style_guide
- API Docs: https://moodle.org/dev/API_docs
- Plugin Repo: https://moodle.org/plugins
