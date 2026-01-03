# Dispatch System Documentation

## Overview
The Dispatch system is a comprehensive real-time radio monitoring and management solution integrated into EasyVol. It provides live tracking of radio transmissions, GPS positions, audio recordings, text messages, and emergency alerts across two radio slots (DMR Tier 2 compatible).

## Features

### Real-Time Monitoring
- **Two-Slot Radio Monitoring**: Independent monitoring of Slot 1 and Slot 2
- **Live Transmission Status**: Shows active transmissions with radio ID, name, assignee, and TalkGroup
- **Audio Streaming**: Built-in audio player with mute capability for each slot
- **Automatic Updates**: JavaScript polling every 2-5 seconds for real-time data

### GPS Position Tracking
- **Live Map View**: OpenStreetMap integration showing real-time radio positions
- **Auto-Hide Inactive Radios**: Radios not transmitting positions for 30+ minutes are automatically removed from the map
- **Detailed Tooltips**: Hover over markers to see radio details, assignee information, and GPS coordinates
- **Historical Position Tracking**: Full history with filtering by radio, date, and time
- **Path Visualization**: Visual trail of radio movements on the map

### Emergency Alerts
- **Visual Alert**: Red flashing popup when emergency code is received
- **Audio Siren**: 3-second siren sound to alert operators
- **Complete Information**: Shows radio details, assignee contact info, GPS position, and time
- **Quick Actions**: One-click acknowledge and map view
- **Persistent Tracking**: Emergency codes are logged and tracked until resolved

### TalkGroup Management
- **Complete CRUD**: Create, read, update, and delete TalkGroups
- **ID and Name Mapping**: Associate numeric TalkGroup IDs with descriptive names
- **Description Support**: Add notes about each TalkGroup's purpose

### Events Log
- **Comprehensive Logging**: All network events (transmissions, registrations, positions, etc.)
- **Real-Time Stream**: Live feed of events in the dispatch interface
- **Filterable History**: Search by date, slot, radio, or TalkGroup

### Audio Recordings
- **Automatic Storage**: Save audio recordings from transmissions
- **Metadata Tracking**: Duration, timestamp, radio, and TalkGroup information
- **Web Playback**: Listen to recordings directly in the browser
- **Filterable Archive**: Search by slot, radio, TalkGroup, or date

### Text Messages
- **SMS Support**: Display text messages sent between radios or to TalkGroups
- **Sender/Recipient Tracking**: Shows who sent what to whom
- **Real-Time Display**: Messages appear immediately in the interface

## System Architecture

### Frontend Components
1. **dispatch.php**: Main dispatch monitoring interface
2. **talkgroup_manage.php**: TalkGroup administration
3. **dispatch_position_history.php**: Historical position viewer
4. **dispatch_raspberry_config.php**: Raspberry Pi configuration

### Backend Components
1. **DispatchController.php**: Core business logic controller
2. **API Endpoints**: 14 total endpoints (7 for Raspberry Pi, 7 for web interface)

### Database Tables
1. **dispatch_talkgroups**: TalkGroup definitions
2. **dispatch_transmissions**: Real-time transmission tracking
3. **dispatch_positions**: GPS position history
4. **dispatch_events**: Network events log
5. **dispatch_audio_recordings**: Audio file metadata
6. **dispatch_text_messages**: Text message history
7. **dispatch_emergency_codes**: Emergency alert tracking
8. **dispatch_raspberry_config**: System configuration

## Installation

### 1. Database Migration
Run the migration script to create all necessary tables:
```sql
mysql -u username -p database_name < migrations/add_dispatch_system.sql
```

### 2. Configure Radios
1. Navigate to **Radio Rubrica**
2. For each radio, ensure the **DMR ID** field is populated
3. This DMR ID is crucial for linking transmissions to radios

### 3. Configure TalkGroups
1. Navigate to **Dispatch** → **Gestione TalkGroup**
2. Add all TalkGroups used in your radio network
3. Enter the numeric ID and descriptive name for each

### 4. Enable API for Raspberry Pi
1. Navigate to **Dispatch** → **Configurazione**
2. Set **Stato API** to "Abilitata"
3. Generate and save a secure API Key
4. Configure audio storage and position tracking settings
5. Follow the [Raspberry Pi Integration Guide](DISPATCH_RASPBERRY_PI_GUIDE.md)

### 5. Create Uploads Directory
Ensure the audio upload directory exists and is writable:
```bash
mkdir -p uploads/dispatch/audio
chmod 755 uploads/dispatch/audio
```

## User Interface Guide

### Main Dispatch Page

#### Slot Monitors (Top Section)
- **Green Background**: Active transmission
- **Pulsing Animation**: Ongoing transmission
- **Radio Information**: Shows ID, name, assignee, and TalkGroup
- **Audio Player**: Appears when streaming is available
- **Mute Button**: Silence audio for the slot

#### Map (Middle Section)
- **Blue Markers**: Radio positions (last 30 minutes)
- **Hover Tooltips**: Shows complete radio information
- **Automatic Centering**: Map adjusts to show all active radios
- **Storico Posizioni Button**: Opens historical position viewer

#### Information Tabs (Bottom Section)
1. **Eventi**: Live feed of all network events
2. **Registrazioni Audio**: Recent audio recordings with playback
3. **Messaggi di Testo**: Text messages sent in the network

### Emergency Popup
- Appears automatically when emergency code is received
- **Red Flashing Background**: Visual alert
- **Siren Sound**: 3-second audio alert
- **Complete Information**: Radio, assignee, GPS, time
- **Actions**: 
  - "Ricevuto": Acknowledge the emergency
  - "Vedi su Mappa": Open location in OpenStreetMap
  - "Chiudi": Dismiss popup

## API Documentation

### For Raspberry Pi Integration
See [DISPATCH_RASPBERRY_PI_GUIDE.md](DISPATCH_RASPBERRY_PI_GUIDE.md) for complete API documentation including:
- Authentication methods
- All 7 API endpoints
- Python integration examples
- Troubleshooting guide

### For Web Interface
The following endpoints are used by the web interface (require user login):

1. **GET /api/dispatch_transmission_status.php**: Current transmission status for both slots
2. **GET /api/dispatch_positions.php**: Active radio positions
3. **GET /api/dispatch_events.php**: Recent network events (last 50)
4. **GET /api/dispatch_audio.php**: Recent audio recordings (last 50)
5. **GET /api/dispatch_messages.php**: Recent text messages (last 50)
6. **GET /api/dispatch_emergencies.php**: Active emergency codes
7. **POST /api/dispatch_emergency_acknowledge.php**: Acknowledge an emergency

## Security Considerations

### API Security
- **API Key Authentication**: Required for all Raspberry Pi API calls
- **HTTPS Only**: Never use HTTP for API communication
- **Key Rotation**: Change API key every 3-6 months
- **IP Restrictions**: Consider restricting API access by IP address

### User Permissions
- **View Permission**: `operations_center` / `view` required for dispatch access
- **Edit Permission**: `operations_center` / `edit` required for configuration
- **Create/Delete**: Required for TalkGroup management

### Data Privacy
- Audio recordings contain voice data - ensure compliance with local laws
- GPS positions are sensitive - limit access appropriately
- Emergency alerts may contain personal information

## Performance Optimization

### Polling Intervals
- Transmission status: 2 seconds
- Radio positions: 5 seconds
- Events, audio, messages: 3-5 seconds
- Emergencies: 2 seconds

These can be adjusted in `dispatch.php` if needed.

### Database Optimization
- Indexes are created on all frequently queried columns
- Old data can be archived periodically
- Consider implementing data retention policies

## Troubleshooting

### Dispatch Page Not Loading
1. Check that database migration was run successfully
2. Verify user has `operations_center` / `view` permission
3. Check browser console for JavaScript errors

### Positions Not Showing on Map
1. Verify radios have `dmr_id` configured in Radio Directory
2. Check that positions are less than 30 minutes old
3. Verify latitude/longitude values are valid

### API Not Working
1. Check that API is enabled in configuration
2. Verify API key matches
3. Confirm uploads directory exists and is writable
4. Check web server error logs

### Emergency Popup Not Appearing
1. Ensure dispatch.php page is open and active
2. Check browser console for errors
3. Verify browser allows audio playback
4. Test by manually triggering an emergency via API

## Future Enhancements

Potential improvements for future versions:
- WebSocket support for true real-time updates (instead of polling)
- Live audio streaming from Raspberry Pi
- Advanced analytics and reporting
- Integration with external mapping services
- Mobile app for iOS/Android
- Multi-language support
- Recording playback speed control
- Audio spectrum analyzer
- Geofencing and alerts

## Support

For issues or questions:
1. Check this documentation
2. Review the [Raspberry Pi Integration Guide](DISPATCH_RASPBERRY_PI_GUIDE.md)
3. Check system logs
4. Contact your system administrator

## Credits

Developed for EasyVol by the development team.
Based on requirements from the cris-deitos/EasyDispatch project.

## License

This dispatch system is part of EasyVol and follows the same license terms.
