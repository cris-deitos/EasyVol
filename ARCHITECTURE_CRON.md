# Cron Architecture Explanation

## Directory Structure

```
/cron/                          <- Actual cron job logic (CLI or included by web)
  ├── backup.php                <- Real backup implementation ✅ FIXED
  ├── vehicle_alerts.php        <- Real vehicle alerts logic ✅ FIXED
  ├── email_queue.php           <- Real email queue logic ✅ ALREADY CORRECT
  └── ...

/public/cron/                   <- HTTP endpoints (wrappers only)
  ├── _cron_base.php            <- Authentication & security ✅ ALREADY CORRECT
  ├── backup.php                <- Wrapper: calls /cron/backup.php ✅ NO CHANGES NEEDED
  ├── vehicle_alerts.php        <- Wrapper: calls /cron/vehicle_alerts.php ✅ NO CHANGES NEEDED
  └── ...
```

## Execution Flow

### When calling public cron endpoint:

```
1. HTTP Request
   └─> GET /public/cron/backup.php?token=xxx

2. Public Wrapper (/public/cron/backup.php)
   ├─> Defines CRON_JOB_NAME = 'backup'
   └─> Includes _cron_base.php
       ├─> Loads Autoloader ✅
       ├─> Uses App::getInstance() ✅
       ├─> Validates token
       └─> Calls executeCronJob('/cron/backup.php')

3. Actual Cron Logic (/cron/backup.php)
   ├─> Was using: new App(false) ❌ FIXED
   ├─> Now uses: App::getInstance() ✅
   ├─> Was using: $app->getDatabase() ❌ FIXED
   └─> Now uses: $app->getDb() ✅

4. Response
   └─> JSON response with success/failure
```

## Why Only /cron/ Files Were Modified

The `/public/cron/*.php` files are **thin wrappers** that:
- Define a constant (CRON_JOB_NAME)
- Include _cron_base.php for authentication
- Call executeCronJob() to run the real cron file
- Return JSON response

They contain **NO database code** and **NO App instantiation issues**.

All database errors were in `/cron/*.php` files, which are:
- The actual implementation
- Used both by CLI and web execution
- Where the buggy `new App(false)` and `getDatabase()` calls were

## Files That Had Bugs (All in /cron/)

1. ✅ `/cron/backup.php` - Fixed App instantiation and method name
2. ✅ `/cron/vehicle_alerts.php` - Fixed App instantiation and method name
3. ✅ `/cron/member_expiry_alerts.php` - Standardized pattern
4. ✅ `/cron/scheduler_alerts.php` - Standardized pattern
5. ✅ `/cron/health_surveillance_alerts.php` - Standardized pattern
6. ✅ `/cron/annual_member_verification.php` - Standardized pattern

## Files That Were Already Correct (All in /public/cron/)

1. ✅ `/public/cron/_cron_base.php` - Uses App::getInstance() correctly
2. ✅ `/public/cron/backup.php` - Simple wrapper, no DB code
3. ✅ `/public/cron/vehicle_alerts.php` - Simple wrapper, no DB code
4. ✅ All other `/public/cron/*.php` files - Simple wrappers

## Verification

You can verify this by checking the public wrapper files:

```bash
cat public/cron/backup.php
```

You'll see it's only 28 lines and contains no database code.

The actual backup logic with database access is in:

```bash
cat cron/backup.php
```

This is the file that had the bugs and was fixed.
