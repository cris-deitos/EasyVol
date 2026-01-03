# Dispatch System - Quick Start Guide

## 5-Minute Setup

### Step 1: Database Migration (2 minutes)
```bash
cd /path/to/EasyVol
mysql -u your_user -p your_database < migrations/add_dispatch_system.sql
```

This creates 8 new tables and adds the DMR ID field to the radio directory.

### Step 2: Configure First TalkGroup (1 minute)
1. Login to EasyVol
2. Go to **Centrale Operativa** → **Radio** → **Dispatch**
3. Click **Gestione TalkGroup**
4. Add your first TalkGroup:
   - ID TalkGroup: `9` (or your network's ID)
   - Nome: `Nazionale`
   - Click **Crea TalkGroup**

### Step 3: Add DMR IDs to Radios (1 minute)
1. Go to **Radio Rubrica**
2. Edit each radio and add its **DMR ID** (e.g., `2227001`)
3. Save changes

### Step 4: Enable API for Raspberry Pi (1 minute)
1. From Dispatch page, click **Configurazione**
2. Set **Stato API** to **Abilitata**
3. Click **Genera** to create an API key
4. Copy the API key (you'll need it for Raspberry Pi)
5. Click **Salva Configurazione**

### Done! ✅
You can now access the dispatch interface at:
**Centrale Operativa → Radio → Dispatch**

## Features Available Immediately

### Without Raspberry Pi (Manual Testing)
- ✅ TalkGroup management
- ✅ Map view (will show radios once positions are received)
- ✅ Events log
- ✅ Configuration interface

### With Raspberry Pi Integration
- ✅ Real-time transmission monitoring
- ✅ Live GPS tracking on map
- ✅ Audio recording storage
- ✅ Text message display
- ✅ Emergency alerts
- ✅ Complete event logging

## Testing the System

### Send Test Position (using curl)
```bash
curl -X POST https://your-domain.com/public/api/dispatch/position.php \
  -H "X-API-KEY: your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{
    "radio_dmr_id": "2227001",
    "latitude": 45.464203,
    "longitude": 9.189982,
    "timestamp": "'$(date '+%Y-%m-%d %H:%M:%S')'"
  }'
```

The radio should now appear on the map in the dispatch interface!

### Send Test Emergency
```bash
curl -X POST https://your-domain.com/public/api/dispatch/emergency.php \
  -H "X-API-KEY: your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{
    "radio_dmr_id": "2227001",
    "latitude": 45.464203,
    "longitude": 9.189982
  }'
```

An emergency popup should appear immediately in the dispatch interface!

## Next Steps

### For Production Use
1. **Secure API**: Use HTTPS only, never HTTP
2. **Set Strong API Key**: Use the generated 64-character key
3. **Configure Audio Directory**: Ensure `uploads/dispatch/audio/` exists and is writable
4. **Set Up Raspberry Pi**: Follow [DISPATCH_RASPBERRY_PI_GUIDE.md](DISPATCH_RASPBERRY_PI_GUIDE.md)
5. **Train Operators**: Show them the interface and emergency procedures

### For Development/Testing
1. **Test All API Endpoints**: Use the examples in the Raspberry Pi guide
2. **Verify Permissions**: Ensure users have `operations_center` / `view` permission
3. **Check Browser Compatibility**: Test on Chrome, Firefox, Safari
4. **Monitor Logs**: Watch for any errors in web server logs

## Common First-Time Issues

### "Nessun TalkGroup configurato"
→ Add TalkGroups via **Gestione TalkGroup**

### Radios not appearing on map
→ Ensure radios have **DMR ID** configured in Radio Directory

### API returns 401
→ Check API key matches the one in configuration

### Emergency popup doesn't appear
→ Ensure dispatch.php page is open and browser allows audio

## Quick Reference

### Main Pages
- **Dispatch Interface**: `/public/dispatch.php`
- **TalkGroup Management**: `/public/talkgroup_manage.php`
- **Position History**: `/public/dispatch_position_history.php`
- **Configuration**: `/public/dispatch_raspberry_config.php`

### API Base URL
`https://your-domain.com/public/api/dispatch/`

### Key Configuration
Database table: `dispatch_raspberry_config`

## Getting Help

1. **System Documentation**: [DISPATCH_SYSTEM_DOCUMENTATION.md](DISPATCH_SYSTEM_DOCUMENTATION.md)
2. **Raspberry Pi Integration**: [DISPATCH_RASPBERRY_PI_GUIDE.md](DISPATCH_RASPBERRY_PI_GUIDE.md)
3. **Web Server Logs**: Check for PHP errors
4. **Browser Console**: Check for JavaScript errors

## Video Walkthrough

(Coming soon - placeholder for a video tutorial showing the interface in action)

---

**Congratulations!** You now have a fully functional dispatch system for real-time radio monitoring. 🎉
