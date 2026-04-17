# Contributing to RKD LLMs.txt Generator

Thanks for your interest in improving this module. Contributions of all kinds are welcome — bug reports, feature suggestions, documentation fixes, and code changes.

## Reporting bugs

Open an issue using the **Bug report** template. Please include:

- Magento version and edition (Community / Commerce)
- PHP version
- Module version
- Steps to reproduce
- Expected vs actual behavior
- Relevant output from `var/log/system.log` or `var/log/exception.log`

## Suggesting features

Open an issue using the **Feature request** template. Describe:

- The use case (what problem does this solve?)
- Any relevant context from the llms.txt specification ([llmstxt.org](https://llmstxt.org/))
- Whether you'd be willing to contribute the implementation

## Submitting pull requests

1. Fork the repository
2. Create a feature branch from `main`: `git checkout -b feature/short-description`
3. Make your changes — see coding standards below
4. Add or update tests where relevant
5. Run the quality checks locally (see below)
6. Commit with a clear message describing the change
7. Open a pull request using the PR template

All pull requests require:
- Passing CI (PHPCS against the Magento 2 coding standard)
- Review and approval from a maintainer before merge

## Coding standards

- **PSR-12** + Magento 2 coding standard
- `declare(strict_types=1);` on every PHP file
- Dependency injection — no direct use of `ObjectManager`
- Service contracts first — define interfaces under `Api/` before implementations under `Model/`
- Strict type hints on all parameters and return types
- Use `===` not `==`

## Local quality checks

Install the Magento coding standard via Composer:

```bash
composer global require --dev magento/magento-coding-standard
```

Run from the module directory:

```bash
~/.composer/vendor/bin/phpcs --standard=Magento2 --extensions=php .
```

## Running tests

Unit tests live under `Test/Unit/`. Run them against a Magento 2 installation:

```bash
# From Magento root, with this module installed
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/RKD/LlmsTxt/Test/Unit/
```

## Code of conduct

Be respectful, constructive, and patient. Feedback should focus on the code and ideas, not the person.

## License

By contributing, you agree that your contributions will be licensed under the [MIT License](LICENSE.txt).
