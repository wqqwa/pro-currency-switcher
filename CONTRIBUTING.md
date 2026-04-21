# Contributing to Pro Currency Switcher

Thank you for your interest in contributing to Pro Currency Switcher! This document provides guidelines and instructions for contributing.

## 📋 Table of Contents

- [Code of Conduct](#code-of-conduct)
- [How to Contribute](#how-to-contribute)
- [Development Setup](#development-setup)
- [Coding Standards](#coding-standards)
- [Pull Request Process](#pull-request-process)
- [Reporting Bugs](#reporting-bugs)
- [Feature Requests](#feature-requests)

---

## Code of Conduct

This project follows the [WordPress Code of Conduct](https://make.wordpress.org/handbook/community-code-of-conduct/). By participating, you are expected to uphold this code. Please report unacceptable behavior to woocross@qq.com.

---

## How to Contribute

### Ways to Contribute

1. **Report bugs** - Submit issues for any bugs you find
2. **Suggest features** - Share your ideas for new features
3. **Improve documentation** - Help make our docs better
4. **Submit pull requests** - Contribute code improvements
5. **Translate** - Help translate the plugin to other languages

---

## Development Setup

### Prerequisites

- PHP 7.4 or higher
- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- Composer (for development dependencies)
- Node.js & npm (for frontend assets)

### Local Development

```bash
# 1. Clone the repository
git clone https://github.com/woocross/pro-currency-switcher.git
cd pro-currency-switcher

# 2. Install dependencies
composer install
npm install

# 3. Build assets (if modifying CSS/JS)
npm run build

# 4. Create a symlink to your WordPress plugins folder
ln -s $(pwd) /path/to/wordpress/wp-content/plugins/pro-currency-switcher
```

### Directory Structure

```
pro-currency-switcher/
├── assets/
│   ├── css/                 # Stylesheets
│   ├── js/                  # JavaScript files
│   └── images/              # Image assets
├── includes/
│   ├── Admin/               # Admin panel classes
│   ├── Core/                # Core functionality
│   ├── Frontend/            # Frontend display classes
│   └── Utils/               # Utility functions
├── languages/               # Translation files (.pot, .po, .mo)
├── templates/               # Template files
├── pro-currency-switcher.php # Main plugin file
├── uninstall.php            # Cleanup on uninstall
└── readme.txt               # WordPress.org readme
```

---

## Coding Standards

### PHP Standards

We follow the [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/). Key points:

```php
// Use tabs for indentation (not spaces)
// Opening brace on same line for control structures
if ( condition ) {
    // code here
}

// Yoda conditions
if ( true === $value ) {
    // code here
}

// Proper spacing
$variable = 'value';
function_name( $param1, $param2 );

// Class naming: PascalCase
class CurrencyService {}

// Method naming: snake_case
public function get_exchange_rate() {}

// Constants: UPPER_SNAKE_CASE
define( 'PCS_VERSION', '1.2.0' );
```

### JavaScript Standards

We follow [WordPress JavaScript Coding Standards](https://developer.wordpress.org/coding-standards/javascript/):

```javascript
// Use const/let instead of var
const currency = 'USD';
let exchangeRate = 1.0;

// Function naming: camelCase
function convertPrice( amount, currency ) {
    // ...
}

// Use template literals
const message = `Current currency: ${currency}`;
```

### CSS Standards

```css
/* Use lowercase */
.pcs-selector {
    /* Properties in alphabetical order */
    background-color: #fff;
    border-radius: 6px;
    color: #333;
}

/* BEM naming convention */
.pcs-selector__item {}
.pcs-selector--active {}
.pcs-selector__item--selected {}
```

### Running Code Checks

```bash
# Run PHP CodeSniffer
vendor/bin/phpcs --standard=WordPress includes/

# Auto-fix issues
vendor/bin/phpcbf --standard=WordPress includes/

# Run JavaScript lint
npm run lint:js

# Run CSS lint
npm run lint:css
```

---

## Pull Request Process

### Before Submitting

1. **Create an issue** - Discuss major changes before implementing
2. **Fork the repository** - Create your own fork
3. **Create a branch** - Use descriptive branch names
   ```
   feature/add-new-currency
   fix/exchange-rate-calculation
   docs/update-installation-guide
   ```
4. **Make your changes** - Follow coding standards
5. **Test thoroughly** - Test on multiple PHP/WP versions
6. **Update documentation** - Update relevant docs

### Commit Messages

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
feat: add support for cryptocurrency display
fix: correct exchange rate calculation for JPY
docs: update installation instructions
style: format code according to standards
refactor: simplify currency detection logic
test: add unit tests for GeoIP detection
chore: update dependencies
```

### Submitting

1. Push to your fork
2. Create a Pull Request against the `main` branch
3. Fill out the PR template completely
4. Wait for review

### PR Checklist

- [ ] Code follows WordPress coding standards
- [ ] Changes are tested on PHP 7.4 and 8.x
- [ ] Changes are tested on WordPress 5.x and 6.x
- [ ] Documentation is updated
- [ ] Commit messages follow conventions
- [ ] No new warnings or errors

---

## Reporting Bugs

### Before Reporting

1. Check [existing issues](https://github.com/woocross/pro-currency-switcher/issues)
2. Test with all other plugins disabled
3. Test with a default theme (Twenty Twenty-Four)
4. Check PHP error logs

### Bug Report Template

```markdown
**Description**
A clear description of the bug.

**Steps to Reproduce**
1. Go to '...'
2. Click on '...'
3. See error

**Expected Behavior**
What you expected to happen.

**Actual Behavior**
What actually happened.

**Environment**
- WordPress version: [e.g. 6.4]
- WooCommerce version: [e.g. 8.2]
- PHP version: [e.g. 8.1]
- Plugin version: [e.g. 1.2.0]

**Screenshots**
If applicable, add screenshots.

**Additional Context**
Any other context about the problem.
```

---

## Feature Requests

We welcome feature requests! Please:

1. Check [existing issues](https://github.com/woocross/pro-currency-switcher/issues) first
2. Use the feature request template
3. Provide as much detail as possible

### Feature Request Template

```markdown
**Is your feature request related to a problem?**
A clear description of the problem.

**Describe the solution you'd like**
A clear description of what you want to happen.

**Describe alternatives you've considered**
Any alternative solutions or features you've considered.

**Additional Context**
Any other context, screenshots, or examples.
```

---

## Translation

Help translate Pro Currency Switcher:

1. The `.pot` file is in `/languages/pro-currency-switcher.pot`
2. Use [Poedit](https://poedit.net/) or translate.wordpress.org
3. Submit `.po` and `.mo` files via Pull Request

---

## Questions?

- Open a [Discussion](https://github.com/woocross/pro-currency-switcher/discussions)
- Email: woocross@qq.com
- WhatsApp: [Contact us](https://wa.me/8612345678900)

---

Thank you for contributing! 🎉
