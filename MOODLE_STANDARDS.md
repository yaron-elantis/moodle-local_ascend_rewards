# Moodle Plugin Repository Standards Checklist for ascend_rewards

This document outlines the standards required for submitting plugins to the official Moodle Plugin Repository and identifies areas to address in your code.

## Moodle Plugin Development Standards

### 1. **File Structure & Organization** âœ“ GOOD
Your plugin follows the correct structure:
- Root files: version.php, lib.php, index.php âœ“
- Classes directory with proper namespacing âœ“
- Database directory (db/) with proper XML files âœ“
- Language directory (lang/en/) âœ“
- Assets directory (pix/ and style/) âœ“

**Required Actions:**
- [ ] Ensure all XML files are properly formatted
- [ ] Validate all database install/upgrade files
- [ ] Check privacy/provider.php is correctly implemented (GDPR compliance)

### 2. **Code Style Standards** - ACTION REQUIRED

Moodle uses PSR-12 with some customizations. Common issues to fix:

#### File Headers
Every PHP file MUST start with:
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
 * Short description of what this file does.
 *
 * Longer description if needed.
 *
 * @package    local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
```

**Issues Found in lib.php:**
- Line 23: Missing space after `function` keyword and before opening brace
- Line 24: Global keyword should be on separate line
- Line 40: Silenced error `@` should be avoided

#### Naming Conventions
- [ ] Class names: PascalCase (CamelCase) âœ“ (seen in classes/)
- [ ] Function names: snake_case âœ“
- [ ] Method names: camelCase âœ“
- [ ] Constants: UPPERCASE_SNAKE_CASE
- [ ] Private/protected properties: prefix with `_` underscore

#### Indentation & Whitespace
- [ ] Use 4 spaces for indentation (NOT tabs)
- [ ] No trailing whitespace at end of lines
- [ ] Blank line at end of file
- [ ] Max line length: 120 characters (soft limit, 200 hard limit)

#### String Usage
- [ ] Use single quotes for simple strings: `'text'` not `"text"`
- [ ] Use double quotes only when containing variables or special chars
- [ ] No string concatenation with `.` on the same line as assignment:
  ```php
  // WRONG:
  $text = 'Hello' . $name;
  
  // RIGHT:
  $text = 'Hello ';
  $text .= $name;
  ```

#### Comments
- [ ] PHPDoc blocks for all functions/methods/classes
- [ ] Use `/**` for documentation blocks
- [ ] Include @param, @return, @throws for all functions
- [ ] Inline comments start with `//` not `#`

Example:
```php
/**
 * Short description.
 *
 * Long description if needed. Can span multiple lines.
 *
 * @param int $userid The user ID
 * @param string $action The action to perform
 * @return bool True if successful, false otherwise
 * @throws moodle_exception
 */
public function do_something($userid, $action) {
    // Your code here
}
```

### 3. **Security Standards** - CRITICAL

#### Input Validation
- [ ] All user input must be validated with `validate_param()`
- [ ] All output must be escaped with appropriate functions:
  - `s()` - for attribute context
  - `format_string()` - for strings in DB
  - `format_text()` - for text with formatting
  - `json_encode()` - for JSON
- [ ] SQL must use parameterized queries with `$DB->get_records_sql()`

#### Capability Checks
- [ ] All functions requiring permissions must check with `require_capability()`
- [ ] Define all required capabilities in db/access.php âœ“

#### Database
- [ ] Use `$DB` global for all database operations
- [ ] Never use `mysql_*` functions (deprecated)
- [ ] Use prepared statements to prevent SQL injection
- [ ] All database access should go through Data classes in classes/

#### File Access
- [ ] Check `MOODLE_INTERNAL` in all non-entry point files âœ“
- [ ] Use `get_file_contents()` instead of direct file ops where possible
- [ ] Validate all file paths

### 4. **Error Handling**
- [ ] No bare `die()` or `exit()` calls - use `throw new moodle_exception()`
- [ ] Proper exception handling with try/catch
- [ ] No silenced errors with `@` operator
- [ ] Log errors using `debugging()` for development

### 5. **Plugin Manifest (version.php)**
Check your version.php contains:
- [ ] `$plugin->component` - matches your plugin directory âœ“
- [ ] `$plugin->version` - integer like 2025121001 âœ“
- [ ] `$plugin->requires` - minimum Moodle version âœ“
- [ ] `$plugin->maturity` - MATURITY_STABLE/BETA/ALPHA âœ“
- [ ] `$plugin->release` - human-readable version âœ“
- [ ] `$plugin->copyright` - copyright year and holder
- [ ] `$plugin->license` - license identifier

Missing from your version.php:
- [ ] Add `$plugin->copyright` and `$plugin->license`

### 6. **Language Strings**
- [ ] All user-facing strings must use language files
- [ ] Check lang/en/local_ascend_rewards.php has all strings
- [ ] Use `get_string('key', 'local_ascend_rewards')` to fetch
- [ ] Never hardcode user-visible text

### 7. **Database Management**
- [ ] All changes go through XML install/upgrade files in db/
- [ ] install.xml for new tables/fields
- [ ] upgrade.php for version-specific upgrades
- [ ] Proper use of XMLDB API

### 8. **Privacy & GDPR Compliance**
- [ ] Implement classes/privacy/provider.php
- [ ] Define what user data is stored
- [ ] Implement data export functionality
- [ ] Implement data deletion functionality

Required interface: `\core_privacy\local\metadata\provider`

### 9. **Events & Logging**
- [ ] Define custom events in db/events.php
- [ ] Log significant actions using events
- [ ] Follow event naming: component_entity_action

### 10. **Hooks (Moodle 4.3+)**
- [ ] If using hooks, define in db/hooks.php
- [ ] Callbacks in classes/hook_callbacks/
- [ ] Follow hook naming conventions

### 11. **Automated Tasks**
- [ ] Define scheduled tasks in db/tasks.php
- [ ] Place in classes/task/
- [ ] Must extend `\core\task\scheduled_task`
- [ ] Implement `get_name()` and `execute()`

### 12. **Tests & Documentation**
- [ ] Include README.md or similar documentation
- [ ] Document what the plugin does
- [ ] Document installation steps (if needed)
- [ ] Note any dependencies
- [ ] Consider writing unit tests

### 13. **AJAX & JavaScript**
- [ ] All JavaScript must be minified for production
- [ ] Use M.util.js_pending() for async operations
- [ ] Implement proper error handling
- [ ] Use CSRF tokens for POST requests

**Check your avatar_modals.js:**
- [ ] Is it minified
- [ ] Does it properly handle CSRF tokens
- [ ] Are there proper error handlers

### 14. **Accessibility (WCAG 2.1 AA)**
- [ ] All form inputs have labels
- [ ] Color is not the only means of conveying information
- [ ] Images have alt text
- [ ] Keyboard navigation works
- [ ] Sufficient color contrast

### 15. **Mobile Responsiveness**
- [ ] CSS should be responsive
- [ ] Check apexrewards.css is mobile-friendly
- [ ] Test on mobile devices

## Key Areas to Address

### HIGH PRIORITY
1. âœ“ Add copyright and license to version.php
2. âœ“ Fix code style issues (spacing, indentation)
3. âœ“ Ensure all output is properly escaped
4. âœ“ Ensure all input is validated with validate_param()
5. âœ“ Remove silenced errors (@)
6. âœ“ Remove bare die() calls - use exceptions
7. âœ“ Implement GDPR provider fully
8. âœ“ Review security: SQL injection, XSS, CSRF

### MEDIUM PRIORITY
1. âœ“ Add PHPDoc blocks to all functions
2. âœ“ Review error handling
3. âœ“ Ensure events are properly logged
4. âœ“ Check database XML files
5. âœ“ Validate language strings

### LOW PRIORITY
1. âœ“ Write comprehensive documentation
2. âœ“ Add unit tests
3. âœ“ Optimize JavaScript/CSS (minify)
4. âœ“ Accessibility audit
5. âœ“ Mobile responsiveness test

## Helpful Resources

- **Moodle Developer Handbook**: https://moodle.org/development
- **Plugin Submission Guidelines**: https://moodle.org/plugins
- **Moodle Code Standards**: https://moodle.org/dev/Coding_style_guide
- **Moodle Security**: https://moodle.org/security
- **Privacy API**: https://docs.moodle.org/en/Privacy_API
- **XMLDB**: https://docs.moodle.org/en/XMLDB

## How to Run Code Checks

Once moodle-plugin-ci is installed globally, you can run:

```bash
# PHP Lint check
phplint -r .

# CodeSniffer with Moodle standards
phpcs --standard=moodle --report=full .

# Copy-paste detection
phpcpd --min-lines 5 --min-tokens 70 .

# PHP Mess Detector
phpmd . text codesize,naming,unusedcode

# All at once
moodle-plugin-ci install && moodle-plugin-ci validate && moodle-plugin-ci codesniffer
```

## Notes

Your plugin is structurally sound and follows many best practices already. The main work now is:
1. Reviewing and fixing code style to match PSR-12
2. Ensuring complete security validation and escaping
3. Adding comprehensive documentation blocks
4. Testing thoroughly across Moodle versions
