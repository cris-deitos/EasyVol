# Additional Print Templates for EasyVol

## Overview
This file documents the 14 additional professional print templates created for EasyVol.

## File Information
- **Filename**: `seed_additional_print_templates.sql`
- **Size**: 61 KB
- **Lines**: 1,197
- **Templates**: 14 complete SQL INSERT statements
- **Created**: 2026-01-04
- **Format**: MySQL-compatible SQL (UTF-8 encoding)

## Installation

```bash
mysql -u username -p database_name < seed_additional_print_templates.sql
```

Or import via phpMyAdmin or other database management tool.

## Templates List

### MEMBERS (4 templates)

1. **Scheda Socio Completa** (`relational`, `single`)
   - Full member profile with all sections
   - Relations: member_contacts, member_addresses, member_licenses, member_courses, member_employment, member_availability
   - Format: A4 Portrait

2. **Libro Soci** (`list`, `all`)
   - Complete member registry with all main fields
   - Format: A4 Landscape
   - **Default template** for members list

3. **Elenco Soci con Codice Fiscale** (`list`, `filtered`)
   - Compact listing with tax codes
   - Format: A4 Portrait

4. **Elenco Soci con Recapiti** (`list`, `filtered`)
   - Contact information (phone, mobile, email)
   - Format: A4 Landscape

### JUNIOR MEMBERS (4 templates)

5. **Scheda Cadetto Completa** (`relational`, `single`)
   - Full junior member profile with guardian information
   - Relations: junior_member_contacts, junior_member_addresses, junior_member_guardians, junior_member_health
   - Includes school and health information
   - Format: A4 Portrait

6. **Libro Soci Cadetti** (`list`, `all`)
   - Junior member registry
   - Format: A4 Landscape
   - **Default template** for junior members list

7. **Elenco Cadetti con Codice Fiscale** (`list`, `filtered`)
   - Compact listing with tax codes
   - Format: A4 Portrait

8. **Elenco Cadetti con Recapiti** (`list`, `filtered`)
   - Contact information including guardian phone
   - Format: A4 Portrait

### MEETINGS (2 templates)

9. **Elenco Riunioni** (`list`, `all`)
   - Meeting list with date, type, title, location, status, participant count
   - Sorted by date (descending)
   - Format: A4 Landscape

10. **Verbale Riunione Assemblea** (`relational`, `single`)
    - Formal assembly minutes
    - Relations: meeting_participants, meeting_agenda
    - Includes voting results and signature spaces
    - Format: A4 Portrait
    - **Default template** for meetings

### VEHICLES (2 templates)

11. **Scheda Mezzo Completa** (`relational`, `single`)
    - Complete vehicle card with identification, status, expiry dates
    - Relations: vehicle_maintenance
    - Highlights upcoming expiries
    - Format: A4 Portrait

12. **Elenco Mezzi con Scadenze** (`list`, `all`)
    - Vehicle list with insurance and inspection expiry dates
    - **Red highlighting** for expiries within 30 days
    - Format: A4 Landscape

### EVENTS (2 templates)

13. **Scheda Evento Dettagliata** (`relational`, `single`)
    - Detailed event sheet with:
      - Event information (type, title, dates, location)
      - Interventions list
      - Volunteer participants with roles and hours
      - Vehicles used with km and hours
    - Relations: interventions, event_participants, event_vehicles
    - Format: A4 Portrait

14. **Elenco Eventi** (`list`, `all`)
    - Event listing with type, title, dates, location, status
    - Format: A4 Landscape

## Technical Specifications

### HTML/CSS Features
- **Fonts**: Arial/Helvetica, 10-12pt body, 14-18pt headers
- **Colors**:
  - Headers/titles: `#333`
  - Table borders: `#ccc`
  - Alternating rows: `#f5f5f5`
  - Expiry warnings: `#d9534f` (red)
- **Margins**: Standard 1cm
- **Tables**: Collapsed borders, white headers (#333 background)

### Print Optimization
- `@media print` rules for multi-page documents
- `thead { display: table-header-group; }` for repeating headers
- `tr { page-break-inside: avoid; }` to prevent row breaks
- Responsive A4 layout (portrait/landscape)

### Handlebars Variables
Each template uses appropriate Handlebars variables for dynamic content:
- Standard: `{{association_name}}`, `{{current_date}}`, `{{current_year}}`
- Entity-specific: `{{registration_number}}`, `{{first_name}}`, `{{last_name}}`, etc.
- Loops: `{{#each member_contacts}}...{{/each}}`
- Conditionals: `{{#if present}}...{{/if}}`

### Database Integration
- Compatible with existing `print_templates` table structure
- JSON configuration for:
  - `relations`: Related table joins
  - `filter_config`: Available filters for filtered lists
- Proper escaping for SQL and HTML content

## Design Philosophy

All templates follow these principles:
1. **Professional appearance** - Clean, formal layouts suitable for official documents
2. **Information hierarchy** - Clear section headers and organized data presentation
3. **Print-friendly** - Optimized for PDF generation and physical printing
4. **Accessibility** - Readable fonts, adequate spacing, logical structure
5. **Consistency** - Uniform styling across all templates
6. **Flexibility** - Configurable headers/footers, filter options

## Compatibility

- MySQL 5.6+ and MySQL 8.x
- mPDF library for PDF generation
- Handlebars.js template engine
- UTF-8 character encoding

## Notes

- All templates are marked as `is_active = 1` (active)
- Default templates (libro soci, libro cadetti, verbale riunioni) have `is_default = 1`
- Templates include proper header and footer content with association name and timestamps
- Scadenze (expiry dates) are visually highlighted when applicable
- Multi-page documents have repeating headers for better readability

## Support

For issues or questions about these templates, refer to the main EasyVol documentation or database schema.
