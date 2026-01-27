# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

## Reporting a Vulnerability

If you discover a security vulnerability within this package, please send an email to Arvid de Jong at info@arvid.nl.

All security vulnerabilities will be promptly addressed.

### What to Include

Please include the following information in your report:

- A description of the vulnerability
- Steps to reproduce the issue
- Possible impact of the vulnerability
- Any potential solutions you've identified

### Response Timeline

- **Initial Response**: Within 48 hours
- **Status Update**: Within 7 days
- **Resolution**: Depends on complexity, but we aim for 30 days or less

### Disclosure Policy

- We will acknowledge receipt of your vulnerability report
- We will send you regular updates about our progress
- We will notify you when the vulnerability is fixed
- We will publicly acknowledge your responsible disclosure (unless you prefer to remain anonymous)

## Best Practices for Users

1. **Never commit API keys** - Always use environment variables for sensitive data
2. **Keep dependencies updated** - Regularly run `composer update` to get security patches
3. **Use HTTPS** - Ensure all API communications use HTTPS
4. **Validate input** - Always validate user input before passing it to the generator

Thank you for helping keep this package and its users safe!
