# Security Policy - EasyVol

## Supported Versions

We actively support the following versions with security updates:

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |

## Reporting a Vulnerability

If you discover a security vulnerability, please follow these steps:

1. **DO NOT** open a public issue
2. Email the security team at: security@easyvol.example.com
3. Include:
   - Description of the vulnerability
   - Steps to reproduce
   - Potential impact
   - Suggested fix (if any)

We will respond within 48 hours and work with you to address the issue.

## Security Features

EasyVol implements multiple security layers:

### 1. Authentication & Authorization

- **Password Security**
  - Passwords hashed using bcrypt (PASSWORD_DEFAULT)
  - Minimum 8 characters required
  - No password reuse policy
  - Admin can force password reset

- **Session Security**
  - HTTPOnly cookies
  - SameSite=Strict
  - Secure flag in HTTPS
  - Session regeneration on login
  - Automatic timeout

- **Access Control**
  - Role-based permissions (RBAC)
  - Granular per-module, per-action permissions
  - Permission checks on every request
  - Audit logging of all actions

### 2. Input Validation & Sanitization

- **Server-Side Validation**
  - All inputs validated before processing
  - Type checking and range validation
  - Email and URL validation
  - File upload restrictions

- **Output Encoding**
  - HTML special characters escaped
  - XSS prevention via htmlspecialchars()
  - JSON encoding for API responses

### 3. Database Security

- **SQL Injection Prevention**
  - Prepared statements with PDO
  - Parameter binding for all queries
  - No string concatenation in queries

- **Database Credentials**
  - Stored outside web root
  - Restricted file permissions (640)
  - Not committed to version control

### 4. File Upload Security

- **Upload Restrictions**
  - File type whitelist (PDF, images, documents)
  - File size limits (10MB default)
  - Random filename generation
  - Upload directory outside public web
  - PHP execution disabled in uploads

- **File Validation**
  - MIME type checking
  - Extension validation
  - Content inspection
  - Virus scanning (recommended)

### 5. CSRF Protection

- **Token-Based Protection**
  - Unique tokens per session
  - Token validation on all forms
  - Token regeneration after use
  - SameSite cookie attribute

### 6. HTTP Security Headers

```apache
# .htaccess
Header set X-Frame-Options "SAMEORIGIN"
Header set X-XSS-Protection "1; mode=block"
Header set X-Content-Type-Options "nosniff"
Header set Referrer-Policy "strict-origin-when-cross-origin"
Header set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

### 7. Error Handling

- **Production Mode**
  - Display errors: OFF
  - Log errors: ON
  - Generic error messages to users
  - Detailed logs for administrators

- **Development Mode**
  - Display errors: ON (only in dev)
  - Stack traces for debugging
  - Should never be enabled in production

### 8. Rate Limiting

- **Login Attempts**
  - Max 5 attempts per 15 minutes
  - Account lockout after threshold
  - IP-based tracking
  - CAPTCHA after failed attempts

- **API Endpoints** (recommended)
  - Request throttling
  - IP-based rate limits
  - User-based rate limits

## Security Best Practices

### For Administrators

1. **Use Strong Passwords**
   - Minimum 12 characters
   - Mix of upper/lower, numbers, symbols
   - Unique per account
   - Use password manager

2. **Enable HTTPS**
   - Use SSL/TLS certificate
   - Force HTTPS redirect
   - Enable HSTS header
   - Regular certificate renewal

3. **Keep Updated**
   - Update PHP regularly
   - Update MySQL/MariaDB
   - Update EasyVol to latest version
   - Monitor security advisories

4. **Backup Regularly**
   - Daily database backups
   - Weekly full system backups
   - Store backups securely
   - Test restore procedures

5. **Monitor Logs**
   - Review activity logs weekly
   - Check for suspicious patterns
   - Investigate failed logins
   - Monitor error logs

6. **Restrict File Permissions**
   ```bash
   # Application files
   find . -type f -exec chmod 644 {} \;
   find . -type d -exec chmod 755 {} \;
   
   # Config file
   chmod 640 config/config.php
   
   # Uploads directory
   chmod 755 uploads/
   chmod 644 uploads/*
   ```

7. **Database Security**
   - Use strong database password
   - Restrict database user privileges
   - Limit remote access
   - Regular security audits

8. **Firewall Configuration**
   - Restrict SSH access
   - Allow only necessary ports
   - Use fail2ban or similar
   - Regular rule review

### For Developers

1. **Follow Secure Coding**
   - Never trust user input
   - Always use prepared statements
   - Escape output context-appropriately
   - Validate on server-side

2. **Code Review**
   - Peer review all changes
   - Security-focused reviews
   - Use static analysis tools
   - Regular security audits

3. **Dependency Management**
   - Keep dependencies updated
   - Use composer audit
   - Review security advisories
   - Remove unused dependencies

4. **Testing**
   - Test authentication flows
   - Test authorization checks
   - Test input validation
   - Penetration testing

## Vulnerability Disclosure

We follow responsible disclosure:

1. **Reporter Submits**
   - Private disclosure to security team
   - Wait for initial response

2. **We Investigate**
   - Confirm vulnerability
   - Assess severity
   - Develop fix

3. **We Fix**
   - Patch developed
   - Testing completed
   - Release prepared

4. **Coordinated Disclosure**
   - Security advisory published
   - Credit given to reporter
   - Update released

Timeline: 30-90 days depending on severity

## Security Checklist

Before deploying to production:

- [ ] HTTPS enabled and enforced
- [ ] Strong database credentials
- [ ] Config file permissions set to 640
- [ ] Upload directory outside public web
- [ ] PHP display_errors disabled
- [ ] Error logging enabled
- [ ] Security headers configured
- [ ] CSRF protection enabled
- [ ] Rate limiting implemented
- [ ] Backup system configured
- [ ] Monitoring system active
- [ ] All dependencies updated
- [ ] Security audit completed
- [ ] Firewall configured
- [ ] Intrusion detection enabled

## Common Vulnerabilities

### Prevented by Design

✅ **SQL Injection** - Prepared statements
✅ **XSS** - Output escaping
✅ **CSRF** - Token validation
✅ **Session Fixation** - Session regeneration
✅ **Directory Traversal** - Input validation
✅ **File Upload Attacks** - Strict validation
✅ **Clickjacking** - X-Frame-Options header

### Requires Configuration

⚠️ **Brute Force** - Enable rate limiting
⚠️ **DDoS** - Use CDN/WAF
⚠️ **Man-in-the-Middle** - Enable HTTPS
⚠️ **Data Exposure** - Configure backups securely

## Compliance

EasyVol helps meet requirements for:

- **GDPR** (EU Data Protection)
  - Data access controls
  - Activity logging
  - Data export/deletion
  - Consent management

- **ISO 27001** (Information Security)
  - Access control
  - Audit trails
  - Incident logging

- **Italian Privacy Laws**
  - D.Lgs. 196/2003
  - Codice Privacy
  - Proper data handling

## Security Updates

Subscribe to security updates:

1. Watch GitHub repository
2. Enable notifications
3. Join mailing list (if available)
4. Follow @easyvol on social media

## Incident Response

If a security breach occurs:

1. **Contain**
   - Identify affected systems
   - Isolate compromised components
   - Stop ongoing attacks

2. **Investigate**
   - Review logs
   - Identify attack vector
   - Assess damage

3. **Remediate**
   - Patch vulnerabilities
   - Remove malicious code
   - Reset credentials

4. **Notify**
   - Inform affected users
   - Report to authorities if required
   - Document incident

5. **Learn**
   - Post-mortem analysis
   - Update procedures
   - Improve defenses

## Resources

- OWASP Top 10: https://owasp.org/www-project-top-ten/
- PHP Security Guide: https://www.php.net/manual/en/security.php
- MySQL Security: https://dev.mysql.com/doc/refman/8.0/en/security-guidelines.html

## Contact

Security Team: security@easyvol.example.com
Bug Bounty: Not currently available

---

**Last Updated**: December 2024
**Version**: 1.0

Please report security vulnerabilities responsibly.
