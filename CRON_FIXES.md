# Cron Job Database Connection Fixes

## Problem Statement
The public cron endpoints were returning HTTP 500 errors due to incorrect database initialization and method calls in the cron job files.

## Root Causes Identified

### 1. Incorrect App Instantiation
**Problem:** Several cron files were attempting to instantiate the App class with a parameter:
```php
$app = new App(false);
```

**Issue:** The App class constructor doesn't accept any parameters, causing a fatal error.

**Solution:** Use the singleton pattern correctly:
```php
$app = App::getInstance();
```

### 2. Incorrect Method Name
**Problem:** Some files were calling a non-existent method:
```php
$db = $app->getDatabase();
```

**Issue:** The correct method name in the App class is `getDb()`, not `getDatabase()`.

**Solution:** Use the correct method name:
```php
$db = $app->getDb();
```

### 3. Inconsistent Initialization Pattern
**Problem:** Some cron files were using an old pattern with direct Database instantiation:
```php
require_once __DIR__ . '/../config/config.php';
use EasyVol\Database;
$db = new Database($config);
```

**Issue:** This pattern bypasses the App singleton and doesn't properly register the autoloader, leading to potential inconsistencies.

**Solution:** Use the App singleton pattern consistently:
```php
require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();
use EasyVol\App;
$app = App::getInstance();
$db = $app->getDb();
$config = $app->getConfig();
```

## Files Modified

### Critical Fixes (HTTP 500 errors)
1. **cron/backup.php**
   - Line 9: Added autoloader registration
   - Line 14: Changed `new App(false)` to `App::getInstance()`
   - Line 63: Changed `getDatabase()` to `getDb()`

2. **cron/vehicle_alerts.php**
   - Line 9: Added autoloader registration
   - Line 15: Changed `new App(false)` to `App::getInstance()`
   - Line 16: Changed `getDatabase()` to `getDb()`

### Consistency Improvements
3. **cron/member_expiry_alerts.php**
   - Replaced direct Database instantiation with App singleton pattern
   - Added proper autoloader registration
   - Removed direct config.php require

4. **cron/scheduler_alerts.php**
   - Replaced direct Database instantiation with App singleton pattern
   - Added proper autoloader registration
   - Removed direct config.php require

5. **cron/health_surveillance_alerts.php**
   - Replaced direct Database instantiation with App singleton pattern
   - Added proper autoloader registration
   - Removed direct config.php require

6. **cron/annual_member_verification.php**
   - Replaced direct Database instantiation with App singleton pattern
   - Added proper autoloader registration
   - Removed direct config.php require

## Benefits of These Changes

### 1. Correct Execution
- All cron jobs now properly initialize the App and Database classes
- No more HTTP 500 errors when calling public cron endpoints

### 2. Consistency
- All cron files now use the same initialization pattern
- Easier to maintain and understand

### 3. Proper Singleton Usage
- The App and Database singletons are used correctly
- Ensures only one instance exists per request

### 4. Better Error Handling
- Autoloader is properly registered before any class usage
- Configuration is loaded through the App class

## Testing Recommendations

### Manual Testing
1. Ensure config.php is properly configured with database credentials
2. Set the cron secret token in config.php:
   ```php
   'cron' => [
       'secret_token' => 'your-secure-token-here',
       'allow_web' => true,
   ]
   ```
3. Test each public cron endpoint:
   - `GET /public/cron/backup.php?token=YOUR_TOKEN`
   - `GET /public/cron/vehicle_alerts.php?token=YOUR_TOKEN`
   - `GET /public/cron/email_queue.php?token=YOUR_TOKEN`
   - etc.

### Expected Response
A successful response should return:
```json
{
    "success": true,
    "cron_job": "backup",
    "message": "Cron job executed successfully",
    "output": "...",
    "timestamp": "2024-01-11 14:00:00"
}
```

## Verification

All modified files have been checked for:
- ✅ PHP syntax errors (php -l)
- ✅ Correct use of App::getInstance()
- ✅ Correct use of getDb() method
- ✅ Proper autoloader registration
- ✅ Consistent initialization pattern

## Related Documentation
- See `public/cron/README.md` for web cron setup instructions
- See `cron/README.md` for CLI cron setup instructions
- See `ARUBA_CRON_SETUP.md` for Aruba hosting specific instructions
