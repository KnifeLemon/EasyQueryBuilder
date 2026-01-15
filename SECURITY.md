# Security Policy

## Supported Versions

We release security updates for the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 1.x.x   | :white_check_mark: |

## Reporting a Vulnerability

If you discover a security vulnerability in EasyQuery, please send an email to **knifelemon@gmail.com**.

Please do NOT create a public GitHub issue for security vulnerabilities.

### What to Include

When reporting a vulnerability, please include:

1. **Description** - A clear description of the vulnerability
2. **Impact** - What an attacker could potentially do
3. **Steps to Reproduce** - Detailed steps to reproduce the issue
4. **Proof of Concept** - Code or examples demonstrating the vulnerability
5. **Suggested Fix** - If you have ideas on how to fix it (optional)

### Response Timeline

- We will acknowledge receipt of your vulnerability report within 48 hours
- We will provide an initial assessment within 7 days
- We will work on a fix and keep you updated on progress
- Once fixed, we will release a security update and credit you (if desired)

## Security Best Practices

When using EasyQuery:

### ✅ DO:
- Always use parameter binding (automatically handled by the library)
- Use `raw()` only with trusted data or SQL functions
- Validate and sanitize user input before passing to queries
- Keep dependencies updated
- Use prepared statements through adapters

### ❌ DON'T:
- Never use `raw()` with user input directly
- Don't disable parameter binding
- Don't trust user input without validation

### Example - Safe Usage

```php
// ✅ SAFE - Using parameter binding
$q = GQuery::table('users')
    ->where(['email' => $_POST['email']])
    ->build();

// ✅ SAFE - Using raw() with SQL functions only
$q = GQuery::table('users')
    ->update(['updated_at' => GQuery::raw('NOW()')])
    ->build();
```

### Example - Unsafe Usage

```php
// ❌ DANGEROUS - Never do this!
$q = GQuery::table('users')
    ->where(['email' => GQuery::raw("'{$_POST['email']}'")])
    ->build();

// This creates SQL injection vulnerability!
```

## Known Security Considerations

- `raw()` method bypasses parameter binding - use only with trusted data
- Always validate user input before using in queries
- Keep PHP and database drivers updated for security patches

Thank you for helping keep EasyQuery secure!
