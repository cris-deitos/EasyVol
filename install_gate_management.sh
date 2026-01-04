#!/bin/bash

# Gate Management System - Quick Installation Script
# This script helps set up the Gate Management System

echo "==============================================="
echo "EasyVol - Gate Management System Installer"
echo "==============================================="
echo ""

# Check if config exists
if [ ! -f "config/config.php" ]; then
    echo "⚠️  WARNING: config/config.php not found!"
    echo "Please copy config/config.sample.php to config/config.php"
    echo "and configure your database settings first."
    echo ""
    exit 1
fi

echo "Step 1: Database Migration"
echo "--------------------------"
echo "This will create the required tables for Gate Management System:"
echo "  - gate_system_config (system on/off status)"
echo "  - gates (gate data and people count)"
echo "  - gate_activity_log (activity tracking)"
echo "  - permissions entries"
echo ""

# Extract database config from PHP config file
DB_NAME=$(php -r "include 'config/config.php'; echo \$config['database']['name'] ?? 'easyvol';")
DB_HOST=$(php -r "include 'config/config.php'; echo \$config['database']['host'] ?? 'localhost';")
DB_USER=$(php -r "include 'config/config.php'; echo \$config['database']['username'] ?? 'root';")

echo "Database: $DB_NAME"
echo "Host: $DB_HOST"
echo "User: $DB_USER"
echo ""

read -p "Apply database migration? (y/n): " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "Applying migration..."
    mysql -h "$DB_HOST" -u "$DB_USER" -p "$DB_NAME" < migrations/20260104_gate_management_system.sql
    
    if [ $? -eq 0 ]; then
        echo "✅ Migration applied successfully!"
    else
        echo "❌ Error applying migration. Please check your database credentials."
        exit 1
    fi
else
    echo "⚠️  Skipping migration. You can apply it manually later."
fi

echo ""
echo "Step 2: Permissions"
echo "-------------------"
echo "Grant 'gate_management' permissions to admin roles in the database:"
echo ""
echo "SQL Example:"
echo "  -- View permission ID for gate_management"
echo "  SELECT id FROM permissions WHERE module = 'gate_management';"
echo ""
echo "  -- Grant to admin role (replace 1 with your admin role_id)"
echo "  INSERT INTO role_permissions (role_id, permission_id)"
echo "  SELECT 1, id FROM permissions WHERE module = 'gate_management';"
echo ""

echo "Step 3: Access URLs"
echo "-------------------"
echo "Admin Interface:"
echo "  📊 Gate Management: /public/gate_management.php"
echo "  🗺️  Fullscreen Map: /public/gate_map_fullscreen.php"
echo ""
echo "Public Interfaces (No Login Required):"
echo "  📱 Mobile Management: /public/public_gate_manage.php"
echo "  📺 Display Board: /public/public_gate_display.php"
echo ""

echo "Step 4: Testing"
echo "---------------"
echo "1. Login as admin and go to Centrale Operativa (Dispatch)"
echo "2. Click 'Gestione Varchi' button"
echo "3. Toggle system ON"
echo "4. Add test gates with GPS coordinates"
echo "5. Test public mobile interface"
echo "6. Test public display board"
echo ""

echo "✅ Installation guide completed!"
echo ""
echo "📖 For detailed documentation, see GATE_MANAGEMENT_GUIDE.md"
echo "==============================================="
