# Quick Start Guide - New Features

## 1. Email Configuration (Native Sendmail)

### Setup

1. Edit your `config/config.php` file (copy from config.sample.php if needed):

```php
'email' => [
    'enabled' => true,
    'from_address' => 'noreply@yourdomain.com',
    'from_name' => 'Your Association Name',
    'reply_to' => 'info@yourdomain.com',
    'return_path' => 'bounce@yourdomain.com',
    'charset' => 'UTF-8',
    'encoding' => '8bit',
    'sendmail_params' => null,
    'additional_headers' => [],
],
```

### Testing Email

1. Login as administrator
2. Navigate to `public/test_sendmail.php`
3. Enter your email address
4. Click "Invia Email di Test"
5. Check your inbox (and spam folder)

### Troubleshooting

- Check PHP error logs
- Verify sendmail is installed: `which sendmail`
- Test from command line: `echo "test" | sendmail youremail@domain.com`
- Check sendmail configuration in php.ini

---

## 2. Member Status Changes (Operativo Sanction)

### Database Migration Required

Run this SQL command on your database:

```sql
ALTER TABLE `member_sanctions` 
MODIFY COLUMN `sanction_type` enum('decaduto', 'dimesso', 'in_aspettativa', 'sospeso', 'in_congedo', 'operativo') NOT NULL;
```

Or use the migration file:
```bash
mysql -u username -p database_name < migrations/add_operativo_sanction_and_junior_sanctions.sql
```

### Using Operativo Sanction

1. Go to a member's detail page
2. Click "Provvedimenti" tab
3. Add new sanction
4. Select "Operativo" from dropdown
5. Choose date after any suspension
6. Save

**Result**: Member status will automatically change to "Attivo" if there was a previous suspension.

### Status Transitions

```
Suspension → Operativo → Active
(in_aspettativa, sospeso, in_congedo) → (operativo) → (attivo)
```

### Logging

All sanction changes are logged to PHP error log with:
- Member ID
- Sanction type
- Date
- Status transitions

---

## 3. Dashboard Fee Requests Counter

### What It Shows

A new yellow card on the dashboard displays the count of pending fee payment requests.

### Features

- **Icon**: Clock (bi-clock-history)
- **Color**: Yellow/Warning
- **Clickable**: Click to see all pending requests
- **Live**: Updates automatically on page refresh

### Direct Link

The counter links to: `fee_payments.php?status=pending`

This shows only requests that need approval.

---

## File Changes Summary

| File | Change |
|------|--------|
| `src/Utils/EmailSender.php` | Native mail() implementation |
| `config/config.sample.php` | New email configuration |
| `public/test_sendmail.php` | New test tool |
| `database_schema.sql` | Added 'operativo' to enum |
| `public/member_sanction_edit.php` | Added logging |
| `public/dashboard.php` | Added counter card |

---

## Security Improvements

✅ Header injection protection in email sender  
✅ Sanitized logging (no personal data exposed)  
✅ Input validation on all forms  
✅ CSRF protection maintained  
✅ SQL injection protection with parameterized queries

---

## Support

For detailed implementation information, see:
- `IMPLEMENTATION_SENDMAIL_AND_FIXES.md` - Complete documentation
- `migrations/README.md` - Database migration guide
- Error logs for troubleshooting

---

## Rollback

If you need to revert changes:

```bash
# Email only
git checkout HEAD~2 -- src/Utils/EmailSender.php config/config.sample.php
rm public/test_sendmail.php

# Member status only
mysql -u user -p database << EOF
ALTER TABLE member_sanctions 
MODIFY COLUMN sanction_type enum('decaduto', 'dimesso', 'in_aspettativa', 'sospeso', 'in_congedo') NOT NULL;
EOF

# Dashboard only
git checkout HEAD~2 -- public/dashboard.php
```

---

## Next Steps

1. ✅ Configure email settings in config/config.php
2. ✅ Test email with test_sendmail.php
3. ✅ Run database migration for operativo sanction
4. ✅ Test member status changes
5. ✅ Verify dashboard counter displays
6. ✅ Train users on new features

---

**Last Updated**: 2024-12-07  
**Version**: 1.0
