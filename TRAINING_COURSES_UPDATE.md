# Training Courses System Update

## Overview
This document describes the updates made to the training courses (Formazione) section to support the Italian Civil Protection course classification system (SSPC - Sistema di Supporto alla Protezione Civile).

## Changes Made

### 1. Database Schema Updates

#### New Fields Added to `training_courses` Table:
- **`sspc_course_code`** (VARCHAR 50): Codice Corso SSPC - Used to store the official SSPC course code
- **`sspc_edition_code`** (VARCHAR 50): Codice Edizione SSPC - Used to store the edition code for the course

#### Migration File:
- Location: `migrations/add_sspc_course_fields.sql`
- Creates two new columns with appropriate indexes for performance
- To apply: Run `php migrations/run_migration.php migrations/add_sspc_course_fields.sql` after configuring the database

### 2. Course Type Classification

The system now supports the complete Italian Civil Protection course classification:

#### A0 - Informational Courses
- A0: Corso informativo rivolto alla cittadinanza

#### A1 - Base Courses
- A1: Corso base per volontari operativi di Protezione Civile

#### A2 - Specialization Courses (18 types)
- A2-01: ATTIVITA' LOGISTICO GESTIONALI
- A2-02: OPERATORE SEGRETERIA
- A2-03: CUCINA IN EMERGENZA
- A2-04: RADIOCOMUNICAZIONI E PROCESSO COMUNICATIVO IN PROTEZIONE CIVILE
- A2-05: IDROGEOLOGICO: ALLUVIONE
- A2-06: IDROGEOLOGICO: FRANE
- A2-07: IDROGEOLOGICO: SISTEMI DI ALTO POMPAGGIO
- A2-08: USO MOTOSEGA E DECESPUGLIATORE
- A2-09: SICUREZZA IN PROTEZIONE CIVILE: D. Lgs. 81/08
- A2-10: TOPOGRAFIA E GPS
- A2-11: RICERCA DISPERSI
- A2-12: OPERATORE NATANTE IN EMERGENZA DI PROTEZIONE CIVILE
- A2-13: INTERVENTI ZOOTECNICI IN EMERGENZA DI PROTEZIONE CIVILE
- A2-14: PIANO DI PROTEZIONE CIVILE: DIVULGAZIONE E INFORMAZIONE
- A2-15: QUADERNI DI PRESIDIO
- A2-16: EVENTI A RILEVANTE IMPATTO LOCALE
- A2-17: SCUOLA I° CICLO DELL'ISTRUZIONE
- A2-18: SCUOLA SECONDARIA SUPERIORE

#### A3 - Coordination Courses (6 types)
- A3-01: CAPO SQUADRA
- A3-02: COORDINATORE TERRITORIALE DEL VOLONTARIATO
- A3-03: VICE COORDINATORE DI SEGRETERIA E SUPPORTO ALLA SALA OPERATIVA
- A3-04: PRESIDENTE ASSOCIAZIONE e/o COORD. GR. COMUNALE/INTERCOM.
- A3-05: COMPONENTI CCV (eletti)
- A3-06: SUPPORTO ALLA PIANIFICAZIONE

#### A4 - High Specialization Courses (15 types)
- A4-01: SOMMOZZATORI di Protezione civile (1° livello)
- A4-02: SOMMOZZATORI di protezione civile (Alta specializzazione)
- A4-03: ATTIVITA' OPERATORI CINOFILI
- A4-04: ATTIVITA' OPERATORI EQUESTRI
- A4-05: CATTURA IMENOTTERI E BONIFICA
- A4-06: T.S.A. - Tecniche Speleo Alpinistiche
- A4-07: S.R.T. - Swiftwater Rescue Technician
- A4-08: PATENTE PER OPERATORE RADIO AMATORIALE
- A4-09: OPERATORE GRU SU AUTO-CARRO
- A4-10: OPERATORE MULETTO
- A4-11: OPERATORE PER PIATTAFORME DI LAVORO ELEVABILI (PLE)
- A4-12: OPERATORE ESCAVATORE
- A4-13: OPERATORE TRATTORE
- A4-14: OPERATORE DRONI
- A4-15: HACCP

#### A5 - AIB (Antincendio Boschivo) Courses (4 types)
- A5-01: A.I.B. di 1° LIVELLO
- A5-02: A.I.B. AGGIORNAMENTI
- A5-03: CAPOSQUADRA A.I.B.
- A5-04: D.O.S. (in gestione direttamente a RL)

#### Other
- Altro: Altro da specificare (for custom courses)

### 3. Code Changes

#### New Helper Class
**File**: `src/Utils/TrainingCourseTypes.php`
- Centralized repository of all course types
- Methods:
  - `getAll()`: Returns all course types as an associative array
  - `getGrouped()`: Returns course types grouped by category for filters
  - `getName($code)`: Gets full course name by code

#### Updated Files

**TrainingController.php** (`src/Controllers/TrainingController.php`)
- Updated `create()` method to handle `sspc_course_code` and `sspc_edition_code`
- Updated `update()` method to handle the new fields

**training_edit.php** (`public/training_edit.php`)
- Added SSPC code input fields (Codice Corso SSPC, Codice Edizione SSPC)
- Replaced hardcoded course types with comprehensive list using TrainingCourseTypes helper
- Made course name optional - auto-fills from selected course type if left blank
- Improved validation logic
- Added helpful hints for users

**training.php** (`public/training.php`)
- Added "Cod. SSPC" column to course list table
- Updated filters with grouped course types (optgroups) for better usability
- Display SSPC codes in compact format (C: course code, E: edition code)

**training_view.php** (`public/training_view.php`)
- Added display of SSPC codes in course details
- Shows codes as badges for better visibility

**database_schema.sql**
- Updated to reflect the new table structure with SSPC fields

### 4. User Experience Improvements

1. **Course Selection**: Users can now select from the official SSPC course list
2. **Auto-naming**: Course names auto-populate based on selected type, but can be customized
3. **SSPC Tracking**: Official SSPC codes can be tracked for integration with regional systems
4. **Grouped Filters**: Course filters are organized by category (Base, Specialization, Coordination, High Specialization, AIB)
5. **Visual Indicators**: SSPC codes displayed as badges in detail views

## Usage Instructions

### Creating a New Course

1. Navigate to **Formazione** (Training) section
2. Click **Nuovo Corso** (New Course)
3. Select the course type from the dropdown (now with 50+ options)
4. Optionally customize the course name (or leave blank to use standard name)
5. Enter SSPC codes if available:
   - **Codice Corso SSPC**: Official course code (e.g., A1-2025-001)
   - **Codice Edizione SSPC**: Edition code (e.g., ED-001)
6. Fill in other required fields (dates, instructor, location, etc.)
7. Save the course

### Filtering Courses

The course list now supports filtering by:
- Course type (organized in categories)
- Status
- Search by name, instructor, or description

### Viewing SSPC Codes

SSPC codes are displayed:
- In the course list table (compact format)
- In the course detail view (as badges)
- Can be used for reporting and integration with regional systems

## Technical Notes

### Database Migration
To apply the database changes on an existing installation:
```bash
php migrations/run_migration.php migrations/add_sspc_course_fields.sql
```

### Backward Compatibility
- Existing courses remain functional
- SSPC codes are optional fields
- Old course type values are preserved
- No data loss occurs during the update

### Code Maintainability
- Course types centralized in `TrainingCourseTypes` helper class
- Easy to add new course types in the future
- Consistent across all views (list, edit, view, filters)

## Future Enhancements

Possible improvements for future releases:
1. Import/export courses with SSPC codes
2. Integration with regional SSPC systems
3. Certificate generation with SSPC codes
4. Reporting by course categories
5. Course attendance tracking with SSPC compliance

## Testing Checklist

- [ ] Database migration runs successfully
- [ ] Can create new course with SSPC codes
- [ ] Can edit existing course and add SSPC codes
- [ ] Course list displays SSPC codes correctly
- [ ] Course detail view shows SSPC codes
- [ ] Filters work with new course types
- [ ] Auto-naming works when course name is left blank
- [ ] Custom course names can still be used
- [ ] Existing courses display correctly
- [ ] Search functionality works with new fields

## Support

For questions or issues related to this update, please refer to:
- GitHub Issues: https://github.com/cris-deitos/EasyVol/issues
- Documentation: See README.md for general setup instructions
