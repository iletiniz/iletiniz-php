# Contributing to the İletiniz PHP SDK

Thank you for your interest in contributing! We welcome all contributions.

## Development Environment

```bash
git clone https://github.com/iletiniz/iletiniz-php.git
cd iletiniz-php
composer install
```

## Code Style

```bash
vendor/bin/phpstan analyse
```

## Running Tests

```bash
vendor/bin/phpunit
```

## Commit Message Guidelines

We use [Conventional Commits](https://www.conventionalcommits.org/):

- `feat:` new feature
- `fix:` bug fix
- `docs:` documentation only changes
- `chore:` configuration, dependency updates, etc.
- `refactor:` code restructuring without behavior changes
- `test:` adding or fixing tests
- `build:` build system or external dependency changes

## Pull Request Process

1. Fork this repository.
2. Create a new branch: `git checkout -b feat/feature-name`.
3. Commit your changes.
4. Push to your fork.
5. Open a Pull Request on GitHub.

## Reporting Issues

Please include:

- A clear and descriptive title
- Steps to reproduce the issue
- Expected and actual behavior
- SDK version and PHP version

## Contact

support@iletiniz.com
