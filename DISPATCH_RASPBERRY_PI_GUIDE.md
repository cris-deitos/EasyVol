# Dispatch System - Raspberry Pi Integration Guide

## Overview
This guide explains how to integrate a Raspberry Pi with the EasyVol Dispatch system for real-time radio monitoring, GPS tracking, and audio recording.

## System Requirements

### Hardware
- Raspberry Pi 3 Model B+ or newer
- DMR/MMDVM compatible radio hardware (e.g., MMDVM hotspot, DVMega, etc.)
- GPS module (optional, for position tracking)
- SD card (minimum 16GB recommended)
- Reliable internet connection

### Software
- Raspberry Pi OS (Bullseye or newer)
- Python 3.8+
- Required Python packages: `requests`, `pyserial` (for GPS)

## Installation

### 1. Configure the Dispatch System

#### Access the Configuration
1. Log into EasyVol as an administrator
2. Navigate to "Centrale Operativa" → "Radio" → "Dispatch"
3. The system will automatically create the necessary database tables on first access

#### Enable API Access
The API is controlled through the `dispatch_raspberry_config` table. To enable:

```sql
UPDATE dispatch_raspberry_config 
SET config_value = '1' 
WHERE config_key = 'api_enabled';
```

#### Set API Key (Recommended)
For security, set an API key:

```sql
UPDATE dispatch_raspberry_config 
SET config_value = 'YOUR_SECURE_API_KEY_HERE' 
WHERE config_key = 'api_key';
```

Generate a secure random key:
```bash
openssl rand -hex 32
```

### 2. Configure TalkGroups

1. Navigate to "Gestione TalkGroup" from the Dispatch page
2. Add your radio network's TalkGroups:
   - **ID TalkGroup**: Numeric ID used by your DMR network (e.g., 9, 99, 9990)
   - **Nome**: Descriptive name (e.g., "Nazionale", "Locale", "Emergenze")
   - **Descrizione**: Optional description of the TalkGroup's purpose

### 3. Configure Radio Directory

1. Navigate to "Radio Rubrica"
2. For each radio in your network, add:
   - **Nome Radio**: Descriptive name
   - **DMR ID**: The radio's DMR identification number (**required for dispatch integration**)
   - **Identificativo**: Call sign or other identifier
   - Other fields as needed

## API Endpoints

Base URL: `https://your-easyvol-domain.com/public/api/dispatch/`

All endpoints require authentication via API key in the header:
```
X-API-KEY: your_api_key_here
```

### 1. Report Transmission Start/End
```
POST /api/dispatch/transmission.php
Content-Type: application/json

{
  "action": "start|end",
  "slot": 1,
  "radio_dmr_id": "2227001",
  "talkgroup_id": "9",
  "transmission_id": 123  // Only required for "end" action
}
```

**Response:**
```json
{
  "success": true,
  "transmission_id": 123,
  "message": "Trasmissione iniziata"
}
```

### 2. Report GPS Position
```
POST /api/dispatch/position.php
Content-Type: application/json

{
  "radio_dmr_id": "2227001",
  "latitude": 45.464203,
  "longitude": 9.189982,
  "altitude": 122.5,           // Optional, in meters
  "speed": 15.3,                // Optional, in km/h
  "heading": 180.5,             // Optional, in degrees
  "accuracy": 5.2,              // Optional, in meters
  "timestamp": "2026-01-03 15:30:00"  // Optional, defaults to now
}
```

**Response:**
```json
{
  "success": true,
  "position_id": 456,
  "message": "Posizione salvata"
}
```

### 3. Upload Audio Recording
```
POST /api/dispatch/audio.php
Content-Type: multipart/form-data

Fields:
- audio: (file) Audio file (WAV, MP3, etc.)
- slot: 1
- radio_dmr_id: "2227001"
- talkgroup_id: "9"
- duration_seconds: 5
- recorded_at: "2026-01-03 15:30:00"  // Optional
```

**Response:**
```json
{
  "success": true,
  "audio_id": 789,
  "file_path": "uploads/dispatch/audio/2026-01-03_15-30-00_1_2227001_abc123.wav",
  "message": "Audio salvato"
}
```

### 4. Report Text Message
```
POST /api/dispatch/text_message.php
Content-Type: application/json

{
  "slot": 1,
  "from_radio_dmr_id": "2227001",
  "to_radio_dmr_id": "2227002",      // Optional, for direct messages
  "to_talkgroup_id": "9",             // Optional, for group messages
  "message_text": "Test message",
  "message_timestamp": "2026-01-03 15:30:00"  // Optional
}
```

### 5. Report Network Event
```
POST /api/dispatch/event.php
Content-Type: application/json

{
  "event_type": "registration|deregistration|call_start|call_end|etc",
  "slot": 1,                          // Optional
  "radio_dmr_id": "2227001",         // Optional
  "talkgroup_id": "9",                // Optional
  "event_data": {                     // Optional, any additional data
    "key": "value"
  },
  "event_timestamp": "2026-01-03 15:30:00"  // Optional
}
```

### 6. Report Emergency Code
```
POST /api/dispatch/emergency.php
Content-Type: application/json

{
  "radio_dmr_id": "2227001",
  "latitude": 45.464203,              // Optional
  "longitude": 9.189982,              // Optional
  "emergency_timestamp": "2026-01-03 15:30:00"  // Optional
}
```

**Important:** Emergency codes trigger immediate visual and audio alerts in the dispatch interface!

### 7. Get Configuration
```
GET /api/dispatch/config.php
```

**Response:**
```json
{
  "success": true,
  "config": {
    "api_enabled": "1",
    "audio_storage_path": "uploads/dispatch/audio/",
    "max_audio_file_size": 10485760,
    "position_update_interval": 60,
    "position_inactive_threshold": 1800
  }
}
```

## Python Integration Example

### Basic Transmission Reporting

```python
import requests
import json

API_BASE_URL = "https://your-easyvol-domain.com/public/api/dispatch/"
API_KEY = "your_api_key_here"

headers = {
    "X-API-KEY": API_KEY,
    "Content-Type": "application/json"
}

def report_transmission_start(slot, radio_dmr_id, talkgroup_id):
    """Report start of transmission"""
    data = {
        "action": "start",
        "slot": slot,
        "radio_dmr_id": radio_dmr_id,
        "talkgroup_id": talkgroup_id
    }
    
    response = requests.post(
        f"{API_BASE_URL}transmission.php",
        headers=headers,
        data=json.dumps(data)
    )
    
    if response.status_code == 200:
        result = response.json()
        return result.get('transmission_id')
    else:
        print(f"Error: {response.text}")
        return None

def report_transmission_end(transmission_id, slot, radio_dmr_id, talkgroup_id):
    """Report end of transmission"""
    data = {
        "action": "end",
        "slot": slot,
        "radio_dmr_id": radio_dmr_id,
        "talkgroup_id": talkgroup_id,
        "transmission_id": transmission_id
    }
    
    response = requests.post(
        f"{API_BASE_URL}transmission.php",
        headers=headers,
        data=json.dumps(data)
    )
    
    return response.status_code == 200

# Example usage
transmission_id = report_transmission_start(1, "2227001", "9")
if transmission_id:
    print(f"Transmission started: {transmission_id}")
    
    # ... transmission happens ...
    
    report_transmission_end(transmission_id, 1, "2227001", "9")
    print("Transmission ended")
```

### GPS Position Reporting

```python
import time
import serial  # For GPS module

def read_gps_position():
    """Read GPS position from GPS module"""
    # This is a simplified example. Actual implementation depends on your GPS module.
    # You might want to use libraries like gpsd or pynmea2
    
    # Example return format
    return {
        "latitude": 45.464203,
        "longitude": 9.189982,
        "altitude": 122.5,
        "speed": 15.3,
        "heading": 180.5,
        "accuracy": 5.2
    }

def report_position(radio_dmr_id):
    """Report current GPS position"""
    gps_data = read_gps_position()
    
    data = {
        "radio_dmr_id": radio_dmr_id,
        **gps_data
    }
    
    response = requests.post(
        f"{API_BASE_URL}position.php",
        headers=headers,
        data=json.dumps(data)
    )
    
    return response.status_code == 200

# Report position every 60 seconds
while True:
    if report_position("2227001"):
        print("Position reported successfully")
    else:
        print("Failed to report position")
    
    time.sleep(60)  # Wait 60 seconds
```

### Audio Recording Upload

```python
def upload_audio_recording(slot, radio_dmr_id, talkgroup_id, audio_file_path, duration_seconds):
    """Upload audio recording"""
    headers_upload = {
        "X-API-KEY": API_KEY
    }
    
    files = {
        'audio': open(audio_file_path, 'rb')
    }
    
    data = {
        'slot': slot,
        'radio_dmr_id': radio_dmr_id,
        'talkgroup_id': talkgroup_id,
        'duration_seconds': duration_seconds
    }
    
    response = requests.post(
        f"{API_BASE_URL}audio.php",
        headers=headers_upload,
        files=files,
        data=data
    )
    
    return response.status_code == 200

# Example usage
upload_audio_recording(1, "2227001", "9", "/path/to/recording.wav", 5)
```

### Emergency Code Reporting

```python
def report_emergency(radio_dmr_id, latitude=None, longitude=None):
    """Report emergency code"""
    data = {
        "radio_dmr_id": radio_dmr_id
    }
    
    if latitude and longitude:
        data["latitude"] = latitude
        data["longitude"] = longitude
    
    response = requests.post(
        f"{API_BASE_URL}emergency.php",
        headers=headers,
        data=json.dumps(data)
    )
    
    return response.status_code == 200

# Example usage
report_emergency("2227001", 45.464203, 9.189982)
```

## Integration with MMDVM/DMR Systems

### Pi-Star Integration
For Pi-Star systems, you can monitor the MMDVMHost log and parse events:

```python
import re
from datetime import datetime

LOG_FILE = "/var/log/pi-star/MMDVM-*.log"

def parse_mmdvm_log_line(line):
    """Parse MMDVM log line for transmission events"""
    # Example line: M: 2021-05-15 14:23:45.123 DMR Slot 1, received voice header from 2227001 to TG 9
    
    patterns = {
        'voice_header': r'DMR Slot (\d+), received voice header from (\d+) to TG (\d+)',
        'voice_end': r'DMR Slot (\d+), received voice end from (\d+) to TG (\d+)',
        'gps': r'DMR Slot (\d+), received GPS from (\d+): Lat: ([\d.-]+), Lon: ([\d.-]+)'
    }
    
    for event_type, pattern in patterns.items():
        match = re.search(pattern, line)
        if match:
            return event_type, match.groups()
    
    return None, None

# Monitor log file and report events
# (Use a proper log monitoring library like watchdog in production)
```

## Troubleshooting

### API Returns 401 (Unauthorized)
- Check that `api_enabled` is set to '1' in the database
- Verify your API key matches the one in the database
- Ensure the API key is being sent in the `X-API-KEY` header

### API Returns 503 (Service Unavailable)
- The API has been disabled in the configuration
- Enable it by setting `api_enabled` to '1'

### Audio Upload Fails
- Check file size is under the configured maximum (default 10MB)
- Verify the uploads directory has write permissions
- Ensure the file format is supported (WAV, MP3, etc.)

### Positions Not Showing on Map
- Verify the radio has a `dmr_id` configured in the Radio Directory
- Check that latitude/longitude values are valid
- Positions older than 30 minutes are hidden by default

### Emergency Popup Not Showing
- Check browser console for JavaScript errors
- Verify the radio exists in the Radio Directory
- Ensure the dispatch.php page is open and active

## Security Best Practices

1. **Always use HTTPS** for API communication
2. **Set a strong API key** (minimum 32 characters, random)
3. **Restrict API access** by IP address in your web server configuration if possible
4. **Monitor API usage** through the dispatch_events log
5. **Regularly rotate API keys** (every 3-6 months)
6. **Use firewall rules** to limit Raspberry Pi access to only necessary ports

## Support and Further Information

For questions or issues with the Dispatch system:
- Check the EasyVol documentation
- Review the system logs in `/var/log` 
- Contact your system administrator

---

**Note:** This integration guide assumes you have a working MMDVM/DMR system. Configuration of the radio hardware itself is beyond the scope of this document.
