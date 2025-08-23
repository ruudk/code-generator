# Copilot Instructions for ruudk/code-generator

## Repository Summary

This is a PHP library that revolutionizes code generation through generators and yield syntax. The `CodeGenerator` class allows developers to yield code line by line while automatically handling indentation, namespace imports, and formatting. Instead of manual string concatenation, developers can write readable generator functions that produce clean, well-formatted PHP code with proper imports and consistent structure.

Key use cases include generating PHP classes, interfaces, enums, traits, and functions with automatic namespace management and beautiful formatting.

## High-Level Repository Information

- **Type**: PHP Composer library/package 
- **PHP Version**: Requires PHP 8.4+ (uses property hooks syntax)
- **Size**: Small focused library (~7 source files, ~13 test files)
- **Languages**: PHP only
- **Framework**: Pure PHP with no external runtime dependencies
- **Package Manager**: Composer
- **Target Runtime**: PHP 8.4+ CLI and web environments
- **Namespace**: `Ruudk\CodeGenerator`

## Build and Validation Instructions

**CRITICAL**: This project requires PHP 8.4+. All commands will fail with syntax errors on PHP 8.3 or lower due to property hooks usage.

### Prerequisites
```bash
# Ensure PHP 8.4+ is available
php --version  # Must show 8.4+

# Install dependencies
composer install
```

### Complete Validation Sequence (CI Pipeline Order)

The following commands MUST be run in this exact order. Each command must pass before proceeding:

1. **Validate Composer Configuration**
   ```bash
   composer validate --strict
   ```
   - Takes ~2 seconds
   - Validates composer.json syntax and dependencies

2. **Install Dependencies** 
   ```bash
   composer install --prefer-dist --no-progress
   ```
   - Takes 30-60 seconds
   - ALWAYS run after any composer.json changes

3. **Check Composer Normalization**
   ```bash
   composer normalize --diff --dry-run
   ```
   - Takes ~3 seconds
   - Ensures composer.json follows standardized format

4. **Analyze Dependencies**
   ```bash
   vendor/bin/composer-dependency-analyser
   ```
   - Takes ~5 seconds
   - Detects unused or missing dependencies

5. **Run Code Style Fixer**
   ```bash
   vendor/bin/php-cs-fixer check --diff
   ```
   - Takes 5-10 seconds
   - Validates PSR-12 and custom coding standards
   - To fix issues: `vendor/bin/php-cs-fixer fix`

6. **Run Static Analysis**
   ```bash
   vendor/bin/phpstan analyse
   ```
   - Takes 10-15 seconds  
   - Runs at level 9 (strictest)
   - Configuration in phpstan.php

7. **Run Unit Tests**
   ```bash
   vendor/bin/phpunit
   ```
   - Takes 5-10 seconds
   - Must have 100% pass rate
   - Configuration in phpunit.xml

### Example Validation (Pre-commit Hook)
```bash
# Validate example files (run from project root)
cd examples && for file in *.php; do 
  echo "Testing examples/$file..."
  php "$file" > /dev/null 2>&1 || { 
    echo "✗ Failed: examples/$file"
    exit 1
  }
  echo "✓ Passed: examples/$file"
done
```

### Development Workflow

1. **Make code changes**
2. **Auto-fix style issues**: `vendor/bin/php-cs-fixer fix`
3. **Run validation sequence** (all commands above)
4. **Test examples** to ensure they work
5. **Commit only if all validations pass**

### Common Issues and Workarounds

- **PHP Version Error**: Project requires PHP 8.4+ due to property hooks syntax. Cannot run on older PHP versions.
- **Memory Issues**: Set `memory_limit=-1` for PHPUnit if needed
- **Lock File Issues**: Run `composer install` before any validation commands
- **Style Failures**: Run `vendor/bin/php-cs-fixer fix` to auto-fix most issues
- **Example Failures**: Examples must execute without errors; they test real library usage

## Project Layout and Architecture

### Core Architecture

The library follows a simple yet powerful architecture:

- **`CodeGenerator`**: Main class for code generation and namespace management
- **`Group`**: Handles indentation and nesting of code blocks  
- **`FullyQualified`**: Represents fully qualified class names
- **`ClassName`**: Validates and represents class names
- **`NamespaceName`**: Handles namespace parsing and validation
- **`FunctionName`**: Represents function names for imports
- **`Alias`**: Handles aliased imports

### Directory Structure

```
/
├── .github/
│   ├── workflows/ci.yml          # GitHub Actions CI pipeline
│   └── FUNDING.yml               # Sponsorship info
├── src/                          # Main source code (7 files)
│   ├── CodeGenerator.php         # Core generator class
│   ├── Group.php                 # Indentation/grouping
│   ├── FullyQualified.php        # FQCN handling  
│   ├── ClassName.php             # Class name validation
│   ├── NamespaceName.php         # Namespace management
│   ├── FunctionName.php          # Function imports
│   └── Alias.php                 # Import aliases
├── tests/                        # Unit tests (matches src structure)
│   ├── CodeGeneratorTest.php     # Core functionality tests
│   ├── GroupTest.php             # Grouping tests
│   ├── FullyQualifiedTest.php    # FQCN tests
│   ├── ClassNameTest.php         # Class name tests  
│   ├── NamespaceNameTest.php     # Namespace tests
│   ├── FunctionNameTest.php      # Function tests
│   └── Fixtures/TestEnum.php     # Test fixtures
├── examples/                     # Working examples
│   ├── example.php               # Comprehensive feature demo
│   └── class.php                 # Simple class generation
├── .php-cs-fixer.php            # Code style configuration
├── phpstan.php                   # Static analysis config  
├── phpstan.neon                  # PHPStan include file
├── phpunit.xml                   # Test configuration
├── captainhook.json              # Git hooks configuration
├── composer-dependency-analyser.php  # Dependency analysis config
└── composer.json                 # Project dependencies
```

### Configuration Files

- **`.php-cs-fixer.php`**: PHP CS Fixer rules using TicketSwap ruleset
- **`phpstan.php`**: PHPStan level 9 with strict rules and custom error formatter
- **`phpunit.xml`**: PHPUnit configuration with strict settings
- **`captainhook.json`**: Pre-commit hooks that run full validation suite
- **`composer-dependency-analyser.php`**: Dependency analysis configuration

### CI/CD Pipeline

GitHub Actions workflow (`.github/workflows/ci.yml`):
- Runs on Ubuntu latest with PHP 8.4
- Matrix strategy for PHP versions  
- Caches Composer dependencies
- Executes complete validation sequence
- Must pass for all commits to main branch

### Key Dependencies

**Runtime**: None (pure PHP library)

**Development**:
- `phpunit/phpunit`: Unit testing framework
- `phpstan/phpstan`: Static analysis at level 9
- `friendsofphp/php-cs-fixer`: Code style enforcement  
- `captainhook/captainhook-phar`: Git hooks
- `shipmonk/composer-dependency-analyser`: Dependency validation
- `ergebnis/composer-normalize`: Composer.json formatting

### Usage Patterns

The library is designed around generator functions that yield lines of code:

```php
$generator = new CodeGenerator('App\\Services');

echo $generator->dumpFile(function() use ($generator) {
    yield 'class UserService';
    yield '{';
    yield $generator->indent(function() use ($generator) {
        yield sprintf('private %s $repository;', $generator->import('App\\Repository\\UserRepository'));
        yield '';
        yield 'public function find(int $id): ?User';
        yield '{';
        yield $generator->indent([
            'return $this->repository->find($id);'
        ]);
        yield '}';
    });
    yield '}';
});
```

### File Modification Guidelines

- **Source files** (`src/`): Core library functionality - modify carefully
- **Test files** (`tests/`): Add tests for new features, maintain coverage
- **Examples** (`examples/`): Must remain functional - they're validated in CI
- **Config files**: Changes may affect entire validation pipeline
- **README.md**: Keep examples in sync with actual working code

### Trust These Instructions

These instructions are comprehensive and tested. Only search for additional information if:
- Commands fail with unexpected errors
- New dependencies are added to composer.json  
- PHP version requirements change
- CI pipeline is modified

The validation sequence provided is the authoritative build process used by the project maintainer and CI system.