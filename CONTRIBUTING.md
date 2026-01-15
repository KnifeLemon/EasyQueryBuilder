# Contributing to GenerateQuery

Thank you for your interest in contributing to GenerateQuery! We welcome contributions from the community.

## How to Contribute

### Reporting Bugs

If you find a bug, please open an issue on GitHub with:
- A clear description of the bug
- Steps to reproduce
- Expected behavior
- Actual behavior
- PHP version and environment details

### Suggesting Enhancements

We welcome suggestions for new features! Please open an issue with:
- A clear description of the enhancement
- Use cases and examples
- Why this would be useful

### Pull Requests

1. Fork the repository
2. Create a new branch for your feature (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Write or update tests if applicable
5. Ensure your code follows PSR-12 coding standards
6. Update documentation as needed
7. Commit your changes (`git commit -m 'Add some amazing feature'`)
8. Push to the branch (`git push origin feature/amazing-feature`)
9. Open a Pull Request

### Code Style

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding style
- Use meaningful variable and method names
- Add PHPDoc comments for all public methods
- Keep methods focused and concise

### Testing

- Write tests for new features
- Ensure all tests pass before submitting PR
- Run `composer test` to execute tests
- Run `composer phpstan` for static analysis

### Documentation

- Update README.md if you add new features
- Add examples for new functionality
- Keep comments clear and concise
- Use English for all documentation

## Development Setup

```bash
# Clone the repository
git clone https://github.com/knifelemon/GenerateQuery.git
cd GenerateQuery

# Install dependencies
composer install

# Run tests
composer test

# Run static analysis
composer phpstan
```

## Questions?

Feel free to open an issue for any questions about contributing!

Thank you for contributing to GenerateQuery! 🎉
