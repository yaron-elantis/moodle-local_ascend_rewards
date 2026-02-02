# Ascend Rewards - Moodle Repository Submission Guide

## What Has Been Set Up

We've installed and configured code checking tools for your Moodle plugin to ensure it meets the official Moodle Plugin Repository standards.

### Tools Created

1. **check_standards.php** - Basic code standards checker
   - Scans all PHP files for common violations
   - Quick local checking without external dependencies
   - Use: `php check_standards.php`

2. **auto_fix_standards.php** - Automated fixer for common issues
   - Fixes whitespace, quotes, trailing space
   - BACKUP YOUR CODE FIRST before running
   - Use: `php auto_fix_standards.php`

3. **.phpcs.xml** - Code Sniffer configuration
   - Configuration for professional PHP_CodeSniffer
   - Defines Moodle coding standards rules
   - Can be used with phpcs commands

4. **MOODLE_STANDARDS.md** - Complete standards guide
   - Detailed explanation of all Moodle requirements
   - Code examples for correct implementations
   - Security best practices

5. **STANDARDS_REPORT.md** - Current status report
   - Summary of issues found
   - Breakdown by category
   - Recommended action plan

6. **SETUP_CHECKER_TOOLS.md** - Installation guide
   - How to set up professional tools
   - VS Code extensions
   - Command-line tools
   - CI/CD integration

## Quick Start - Fix Your Code

### Step 1: Back Up Your Code
```bash
# Create a backup of your entire plugin
robocopy . .backup /S /E
# or
Copy-Item . -Destination "..\ascend_rewards_backup" -Recurse
```

### Step 2: Run the Checker
```bash
# From your plugin directory
C:\laragon\bin\php\php-8.2.28-nts-Win32-vs16-x64\php.exe check_standards.php

# This will show all issues found
```

### Step 3: Auto-Fix Common Issues
```bash
# THIS MODIFIES YOUR FILES - ensure backup first!
C:\laragon\bin\php\php-8.2.28-nts-Win32-vs16-x64\php.exe auto_fix_standards.php
```

### Step 4: Check Again
```bash
# Run the checker again to see what's left
C:\laragon\bin\php\php-8.2.28-nts-Win32-vs16-x64\php.exe check_standards.php
```

### Step 5: Manual Fixes
Review MOODLE_STANDARDS.md for guidance on remaining issues, particularly:
- Critical errors (security, missing checks)
- Long lines that need breaking
- PHPDoc blocks that need additions

## Critical Issues to Fix First

### 1. Missing MOODLE_INTERNAL Checks
Files: `store_section.php`, `villain_unlock.php`

Add to the top of each file after the opening `<php` tag:
```php
<php
// ... license header ...

defined('MOODLE_INTERNAL') || die();

// rest of code
```

### 2. Silenced Error Operators
Files: `store_section.php` (lines 448, 459, 487, 493, 559)

Replace:
```php
// WRONG:
$data = @json_decode($str);

// RIGHT:
$data = json_decode($str);
if ($data === null) {
    // Handle error
    throw new moodle_exception('invalid_json', 'local_ascend_rewards');
}
```

### 3. Version.php Updates
Add to your version.php:
```php
$plugin->copyright = '2025 Your Organization Name';
$plugin->license = 'https://www.gnu.org/copyleft/gpl.html';
```

## Standard Moodle File Header Template

Use this for ALL new PHP files:

```php
<php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Brief description of this file.
 *
 * Longer description if needed. Can explain what functions or classes
 * are defined here and what they do.
 *
 * @package    local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
```

## Code Standards Quick Reference

### Indentation
- Use 4 SPACES (NOT tabs)
- No trailing whitespace

### Quotes
- Use SINGLE quotes: `'string'`
- Only use double quotes if: `"Hello $name"` (with variables)

### Spacing
- `if ($condition) {` - space after control keyword
- `function myFunc() {` - space before brace
- `$var = 'value';` - spaces around operators
- Max line length: 120 chars (soft), 200 chars (hard)

### Comments
```php
// Single line comment

/**
 * Documentation block
 * for functions/classes
 *
 * @param string $param Description
 * @return bool Description
 */
```

### Naming
- Classes: `PascalCase` e.g. `BadgeAwarder`
- Functions: `snake_case` e.g. `award_badge()`
- Constants: `UPPER_CASE` e.g. `BADGE_TIMEOUT`
- Private properties: `_prefix` e.g. `$_internal`

## Most Common Issues to Fix Manually

### Issue 1: String Quotes (850+ cases)
```php
// WRONG:
echo "Hello";
$text = "Some text";

// RIGHT:
echo 'Hello';
$text = 'Some text';
```

### Issue 2: Long Lines (200+ cases)
```php
// WRONG:
$badge = $DB->get_record('badges', array('id' => $badgeid, 'component' => 'local_ascend_rewards'));

// RIGHT:
$badge = $DB->get_record(
    'badges',
    array('id' => $badgeid, 'component' => 'local_ascend_rewards')
);
```

### Issue 3: Function Documentation
```php
// WRONG:
function process_badge($badgeid) {
    // code
}

// RIGHT:
/**
 * Process a badge for a user.
 *
 * @param int $badgeid The badge ID to process
 * @return bool True if successful, false otherwise
 * @throws moodle_exception
 */
function process_badge($badgeid) {
    // code
}
```

### Issue 4: Security - Input Validation
```php
// WRONG:
$userid = $_POST['userid'];
$DB->get_record('user', array('id' => $userid));

// RIGHT:
$userid = required_param('userid', PARAM_INT);
require_login();
require_capability('moodle/site:viewparticipants', context_system::instance());
$DB->get_record('user', array('id' => $userid));
```

### Issue 5: Security - Output Escaping
```php
// WRONG:
echo $user->firstname;
echo "Score: " . $score;

// RIGHT:
echo s($user->firstname);
echo 'Score: ' . $score;
```

## Testing Your Plugin

After making fixes, test thoroughly:

```bash
# Test in Moodle
1. Upload to your local Moodle
2. Visit Site Administration > Plugins
3. Check for any errors
4. Test main functionality
5. Check database changes work
6. Test as different user roles
```

## Professional Code Checking (Advanced)

Once you have composer working, install professional tools:

```bash
# Install PHP CodeSniffer with Moodle standard
C:\laragon\bin\composer\composer.bat require --dev \
    squizlabs/php_codesniffer:^3.8 \
    moodlerooms/moodle-coding-standard:5.1.0

# Then run professional checks
.\vendor\bin\phpcs . --standard=moodle --report=full
```

## Submission Checklist

Before submitting to Moodle Plugin Repository:

### Code Quality
- [ ] Run `check_standards.php` - zero critical errors
- [ ] No MOODLE_INTERNAL warnings
- [ ] No silenced error operators (@)
- [ ] All functions have PHPDoc blocks
- [ ] No bare die()/exit() calls
- [ ] All input validated with validate_param()
- [ ] All output escaped appropriately

### Functionality
- [ ] Plugin installs without errors
- [ ] All features work as expected
- [ ] Database changes work on upgrade
- [ ] No unhandled exceptions
- [ ] Works with Moodle 4.5+

### Security
- [ ] SQL injection prevented (parameterized queries)
- [ ] XSS prevention (proper escaping)
- [ ] CSRF tokens used
- [ ] Capabilities checked
- [ ] Input validation complete

### Documentation
- [ ] README.md created (or in version.php doc)
- [ ] Installation instructions clear
- [ ] Usage instructions provided
- [ ] Requirements listed (PHP, Moodle version)
- [ ] License clearly stated

### Repository Requirements
- [ ] Plugin meets PSR-12 standards
- [ ] No third-party dependencies in repo
- [ ] No sensitive data in code
- [ ] Copyright and license in all files
- [ ] Version number follows convention

## Support & Questions

If you need help understanding specific issues:
1. Check MOODLE_STANDARDS.md - detailed explanations
2. Check SETUP_CHECKER_TOOLS.md - setup issues
3. Visit https://moodle.org/dev - official docs
4. Check Moodle forums and tracker

## Key Files for Repository

Make sure your plugin root has:

- âœ“ version.php
- âœ“ lib.php  
- âœ“ lang/en/local_ascend_rewards.php
- âœ“ classes/ directory with proper classes
- âœ“ db/access.php (capabilities)
- âœ“ db/events.php (if using events)
- âœ“ db/install.xml (database schema)
- âœ“ README.md or similar documentation
- âœ“ .phpcs.xml (code standards config)
- âœ“ LICENSE (GPL-3.0 or similar)

## Next Steps

1. **Immediately:** 
   - Back up your code
   - Fix critical errors (MOODLE_INTERNAL, @ operators)
   - Update version.php with copyright/license

2. **This week:**
   - Run auto-fixer
   - Fix remaining quote and spacing issues
   - Add PHPDoc blocks
   - Security audit

3. **Before submission:**
   - Final code review
   - Functionality testing
   - Professional tool checks
   - Compliance verification

Good luck with your submission! ðŸš€
