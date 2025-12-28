# Architecture Diagram - Print Template Restoration System

## System Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                         USER INTERFACE                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌────────────────────┐          ┌──────────────────────┐     │
│  │  Settings Page     │          │  Restoration Page    │     │
│  │  settings.php      │  click   │  restore_print       │     │
│  │                    │ ────────▶│  _templates.php      │     │
│  │  [Ripristina       │          │                      │     │
│  │   Template]        │          │  - Shows 10 templates│     │
│  └────────────────────┘          │  - Confirmation form │     │
│                                   │  - CSRF protection   │     │
│                                   └──────────────────────┘     │
│                                            │                    │
└────────────────────────────────────────────┼────────────────────┘
                                             │ POST (confirmed)
                                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                    APPLICATION LAYER                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  restore_print_templates.php                             │  │
│  │                                                           │  │
│  │  1. Validate CSRF token ✓                               │  │
│  │  2. Check permissions (settings:edit) ✓                 │  │
│  │  3. Read seed_print_templates.sql                       │  │
│  │  4. Parse SQL statements                                │  │
│  │  5. Begin transaction                                   │  │
│  │  6. Execute INSERT statements                           │  │
│  │  7. Commit transaction                                  │  │
│  │  8. Log activity                                        │  │
│  │  9. Show success message                                │  │
│  └──────────────────────────────────────────────────────────┘  │
│                           │                                     │
└───────────────────────────┼─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                      DATABASE LAYER                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  MySQL Database - EasyVol                                │  │
│  │                                                           │  │
│  │  Table: print_templates                                  │  │
│  │  ┌────────────────────────────────────────────────────┐  │  │
│  │  │ id  │ name              │ template_type │ entity   │  │  │
│  │  ├─────┼───────────────────┼───────────────┼──────────┤  │  │
│  │  │ 1   │ Tessera Socio     │ single        │ members  │  │  │
│  │  │ 2   │ Scheda Socio      │ relational    │ members  │  │  │
│  │  │ 3   │ Attestato...      │ single        │ members  │  │  │
│  │  │ 4   │ Libro Soci        │ list          │ members  │  │  │
│  │  │ 5   │ Tessere Multiple  │ multi_page    │ members  │  │  │
│  │  │ 6   │ Scheda Mezzo      │ relational    │ vehicles │  │  │
│  │  │ 7   │ Elenco Mezzi      │ list          │ vehicles │  │  │
│  │  │ 8   │ Verbale Riunione  │ relational    │ meetings │  │  │
│  │  │ 9   │ Foglio Presenze   │ relational    │ meetings │  │  │
│  │  │ 10  │ Elenco Eventi     │ list          │ events   │  │  │
│  │  └────────────────────────────────────────────────────┘  │  │
│  │                                                           │  │
│  │  Table: activity_logs                                    │  │
│  │  ┌────────────────────────────────────────────────────┐  │  │
│  │  │ Logs: restore_templates action with timestamp      │  │  │
│  │  └────────────────────────────────────────────────────┘  │  │
│  └──────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

## Template Rendering Flow

```
┌──────────────┐       ┌─────────────────┐       ┌──────────────┐
│   User       │       │ PrintTemplate   │       │   Database   │
│   Action     │       │   Controller    │       │              │
└──────┬───────┘       └────────┬────────┘       └──────┬───────┘
       │                        │                        │
       │ Generate PDF           │                        │
       │───────────────────────▶│                        │
       │                        │                        │
       │                        │ Load template          │
       │                        │───────────────────────▶│
       │                        │                        │
       │                        │ Template data          │
       │                        │◀───────────────────────│
       │                        │                        │
       │                        │ Load entity data       │
       │                        │───────────────────────▶│
       │                        │                        │
       │                        │ Entity data            │
       │                        │◀───────────────────────│
       │                        │                        │
       │                        │ Load related data      │
       │                        │ (if relational)        │
       │                        │───────────────────────▶│
       │                        │                        │
       │                        │ Related data           │
       │                        │◀───────────────────────│
       │                        │                        │
       │                        │ Process template:      │
       │                        │ 1. Replace variables   │
       │                        │ 2. Process loops       │
       │                        │ 3. Process conditionals│
       │                        │ 4. Apply CSS           │
       │                        │ 5. Add header/footer   │
       │                        │                        │
       │      PDF Generated     │                        │
       │◀───────────────────────│                        │
       │                        │                        │
```

## Template Types Explained

```
┌─────────────────────────────────────────────────────────────────┐
│                     TEMPLATE TYPES                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  1. SINGLE (single entity, single page)                        │
│     ┌─────────────────────────────────────┐                    │
│     │  Tessera Socio                      │                    │
│     │  ┌─────────────────────────────┐    │                    │
│     │  │ Member ID: 001              │    │                    │
│     │  │ Name: Mario Rossi           │    │                    │
│     │  │ Valid until: 31/12/2024     │    │                    │
│     │  └─────────────────────────────┘    │                    │
│     └─────────────────────────────────────┘                    │
│                                                                 │
│  2. LIST (multiple entities, single table)                     │
│     ┌─────────────────────────────────────────────────────┐    │
│     │  Libro Soci                                         │    │
│     │  ┌──────┬────────┬─────────┬──────────┐            │    │
│     │  │ ID   │ Name   │ Birth   │ Status   │            │    │
│     │  ├──────┼────────┼─────────┼──────────┤            │    │
│     │  │ 001  │ Mario  │ 1980... │ Active   │            │    │
│     │  │ 002  │ Luigi  │ 1985... │ Active   │            │    │
│     │  │ 003  │ Anna   │ 1990... │ Active   │            │    │
│     │  └──────┴────────┴─────────┴──────────┘            │    │
│     └─────────────────────────────────────────────────────┘    │
│                                                                 │
│  3. RELATIONAL (single entity with related data)               │
│     ┌─────────────────────────────────────────────────────┐    │
│     │  Scheda Socio - Mario Rossi                        │    │
│     │  ┌───────────────────────────────────────────────┐  │    │
│     │  │ Personal Data: ...                           │  │    │
│     │  └───────────────────────────────────────────────┘  │    │
│     │  Contacts:                                          │    │
│     │  ┌───────────┬──────────────────────┐               │    │
│     │  │ Mobile    │ +39 123 456 789     │               │    │
│     │  │ Email     │ mario@example.com   │               │    │
│     │  └───────────┴──────────────────────┘               │    │
│     │  Licenses:                                          │    │
│     │  ┌──────┬────────────┬──────────────┐               │    │
│     │  │ Type │ Number     │ Expiry       │               │    │
│     │  ├──────┼────────────┼──────────────┤               │    │
│     │  │ B    │ AB123456   │ 01/01/2025   │               │    │
│     │  │ C    │ CD789012   │ 01/01/2026   │               │    │
│     │  └──────┴────────────┴──────────────┘               │    │
│     └─────────────────────────────────────────────────────┘    │
│                                                                 │
│  4. MULTI_PAGE (multiple entities, one per page)               │
│     ┌──────────┐  ┌──────────┐  ┌──────────┐                  │
│     │ Tessera  │  │ Tessera  │  │ Tessera  │                  │
│     │ Mario    │  │ Luigi    │  │ Anna     │                  │
│     │ (Page 1) │  │ (Page 2) │  │ (Page 3) │                  │
│     └──────────┘  └──────────┘  └──────────┘                  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

## File Organization

```
EasyVol/
├── public/
│   ├── settings.php (Modified - added restoration button)
│   └── restore_print_templates.php (New - restoration interface)
│
├── src/
│   └── Controllers/
│       └── PrintTemplateController.php (Existing - renders templates)
│
├── seed_print_templates.sql (New - template data)
├── SEED_TEMPLATES_README.md (New - user guide)
├── SOLUZIONE_TEMPLATE.md (New - solution summary)
├── TESTING_INSTRUCTIONS.md (New - test cases)
└── ARCHITECTURE.md (This file)
```

## Security Flow

```
┌────────────────────────────────────────────────────────────┐
│                    SECURITY CHECKS                         │
├────────────────────────────────────────────────────────────┤
│                                                            │
│  Request arrives                                           │
│     │                                                      │
│     ▼                                                      │
│  ┌─────────────────────────────────────┐                  │
│  │ 1. Is user authenticated?           │                  │
│  │    ├─ NO → Redirect to login       │                  │
│  │    └─ YES → Continue                │                  │
│  └─────────────────────────────────────┘                  │
│     │                                                      │
│     ▼                                                      │
│  ┌─────────────────────────────────────┐                  │
│  │ 2. Has settings:edit permission?    │                  │
│  │    ├─ NO → Access denied           │                  │
│  │    └─ YES → Continue                │                  │
│  └─────────────────────────────────────┘                  │
│     │                                                      │
│     ▼                                                      │
│  ┌─────────────────────────────────────┐                  │
│  │ 3. Valid CSRF token? (POST only)    │                  │
│  │    ├─ NO → Security error           │                  │
│  │    └─ YES → Continue                │                  │
│  └─────────────────────────────────────┘                  │
│     │                                                      │
│     ▼                                                      │
│  ┌─────────────────────────────────────┐                  │
│  │ 4. File exists?                     │                  │
│  │    ├─ NO → File not found error     │                  │
│  │    └─ YES → Continue                │                  │
│  └─────────────────────────────────────┘                  │
│     │                                                      │
│     ▼                                                      │
│  ┌─────────────────────────────────────┐                  │
│  │ 5. Execute in transaction           │                  │
│  │    - Prepared statements            │                  │
│  │    - Whitelist validation           │                  │
│  │    - Rollback on error              │                  │
│  └─────────────────────────────────────┘                  │
│     │                                                      │
│     ▼                                                      │
│  ┌─────────────────────────────────────┐                  │
│  │ 6. Log activity                     │                  │
│  │    - User ID                        │                  │
│  │    - Action                         │                  │
│  │    - Timestamp                      │                  │
│  │    - IP address                     │                  │
│  └─────────────────────────────────────┘                  │
│     │                                                      │
│     ▼                                                      │
│  Success                                                   │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

## Data Model

```
┌──────────────────────────────────────────────────────────────┐
│                  print_templates TABLE                       │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  id                  INT         Primary Key                │
│  name                VARCHAR     Template name              │
│  description         TEXT        Description                │
│  template_type       ENUM        single/list/multi/relation │
│  data_scope          ENUM        single/filtered/all        │
│  entity_type         VARCHAR     members/vehicles/etc       │
│  html_content        LONGTEXT    Handlebars template        │
│  css_content         TEXT        Styling                    │
│  relations           JSON        Related tables             │
│  filter_config       JSON        Filter options             │
│  variables           JSON        Available variables        │
│  page_format         VARCHAR     A4/Letter/Custom           │
│  page_orientation    VARCHAR     portrait/landscape         │
│  show_header         BOOLEAN     Header visibility          │
│  show_footer         BOOLEAN     Footer visibility          │
│  header_content      TEXT        Header HTML                │
│  footer_content      TEXT        Footer HTML                │
│  watermark           TEXT        Watermark text             │
│  is_active           BOOLEAN     Template active            │
│  is_default          BOOLEAN     Default for entity         │
│  created_by          INT         User ID (NULL for system)  │
│  created_at          TIMESTAMP   Creation date              │
│  updated_by          INT         Last modifier ID           │
│  updated_at          TIMESTAMP   Last update                │
│                                                              │
└──────────────────────────────────────────────────────────────┘

Relationships:
  members ←──┬─→ member_contacts
             ├─→ member_addresses
             ├─→ member_licenses
             └─→ member_courses

  vehicles ←─┬─→ vehicle_maintenance
             └─→ vehicle_documents

  meetings ←─┬─→ meeting_participants
             └─→ meeting_agenda

  events ←───┬─→ event_participants
             └─→ event_vehicles
```

## Alternative Restoration Methods

```
Method 1: Web UI (Recommended)
  Browser → restore_print_templates.php → Database
  
  Pros: ✓ User-friendly
        ✓ Visual feedback
        ✓ Activity logging
        ✓ Error handling
  
  Cons: ✗ Requires web access
        ✗ Needs admin login

─────────────────────────────────────────────────────────────

Method 2: Command Line
  Terminal → mysql client → Database
  
  Command: mysql -u user -p db < seed_print_templates.sql
  
  Pros: ✓ Fast
        ✓ Scriptable
        ✓ No web access needed
  
  Cons: ✗ Requires SSH/shell access
        ✗ No activity logging
        ✗ Technical knowledge required

─────────────────────────────────────────────────────────────

Method 3: phpMyAdmin
  Browser → phpMyAdmin → Import → Database
  
  Pros: ✓ Visual interface
        ✓ No coding required
        ✓ Error messages visible
  
  Cons: ✗ Requires phpMyAdmin access
        ✗ No activity logging
        ✗ Manual process
```

## Deployment Checklist

```
Before deploying:
  ☐ All files committed to repository
  ☐ .gitignore updated
  ☐ Documentation complete
  ☐ Code reviewed
  ☐ Security validated

After deploying:
  ☐ Test restoration via web
  ☐ Verify all 10 templates present
  ☐ Generate test PDF
  ☐ Check activity logs
  ☐ Verify permissions work
  ☐ Test error scenarios

Production checklist:
  ☐ Backup database before restoration
  ☐ Test on staging first
  ☐ Inform users of new feature
  ☐ Document in release notes
  ☐ Monitor error logs
```

---

**Version**: 1.0  
**Last Updated**: December 2024  
**Maintainer**: GitHub Copilot for cris-deitos
