#!/usr/bin/env python3
"""
CSV to SQL Converter for EasyVol Import
=========================================

This script converts the CSV files (soci.csv and cadetti.csv) from the old
system into SQL INSERT statements for the new EasyVol database.

Usage:
    python3 csv_to_sql_converter.py soci.csv > import_soci_adulti_completo.sql
    python3 csv_to_sql_converter.py cadetti.csv > import_cadetti_completo.sql

Requirements:
    - Python 3.6+
    - No external dependencies (uses only standard library)

Security Note:
    This script is designed for one-time data migration from controlled CSV files.
    It uses basic SQL escaping which is sufficient for this use case. For ongoing
    data imports or user-facing applications, consider using parameterized queries
    or an ORM instead.

Author: EasyVol Project
Date: 2025-12-07
"""

import csv
import sys
from datetime import datetime
from typing import Dict, List, Optional


def escape_sql_string(value: Optional[str]) -> str:
    """Escape a string for SQL insertion.
    
    Note: This function is designed for one-time CSV data migration where the source
    data is controlled and trusted. It handles the most common cases (quotes, backslashes).
    
    For production applications with user input, always use parameterized queries.
    
    Security assumptions:
    - Input comes from controlled CSV files (not user input)
    - CSV data doesn't contain binary data or null bytes
    - Newlines/carriage returns in data are intentional multiline text
    """
    if value is None or value == '':
        return 'NULL'
    # First escape backslashes (must be done before quotes)
    escaped = value.replace("\\", "\\\\")
    # Then escape single quotes
    escaped = escaped.replace("'", "\\'")
    # Note: Newlines and carriage returns are preserved as-is for multiline text fields
    # like notes. If your data contains these unintentionally, clean the CSV first.
    return f"'{escaped}'"


def parse_date(date_str: Optional[str]) -> str:
    """Parse and format a date string."""
    if not date_str or date_str.strip() == '':
        return 'NULL'
    
    # Try common date formats
    formats = ['%Y-%m-%d', '%d/%m/%Y', '%d-%m-%Y']
    for fmt in formats:
        try:
            dt = datetime.strptime(date_str.strip(), fmt)
            return f"'{dt.strftime('%Y-%m-%d')}'"
        except ValueError:
            continue
    
    # If no format matched, return as-is (might be NULL or needs manual fix)
    return escape_sql_string(date_str)


def build_notes_field(data: Dict[str, str], fields: List[str]) -> str:
    """Build the notes field from multiple CSV columns."""
    notes_parts = []
    
    for field, label in fields:
        value = data.get(field, '').strip()
        if value:
            notes_parts.append(f"{label}: {value}")
    
    return '\n'.join(notes_parts)


def convert_member_type(tipo_socio: str) -> str:
    """Convert tipo_socio to member_type."""
    tipo = tipo_socio.upper().strip()
    if 'FONDATORE' in tipo:
        return 'fondatore'
    return 'ordinario'


def convert_member_status(stato: str, tipo_socio: str) -> tuple:
    """Convert stato to member_status and volunteer_status.
    
    Returns:
        (member_status, volunteer_status)
    """
    stato_upper = stato.upper().strip()
    tipo_upper = tipo_socio.upper().strip()
    
    # Check for dismissal/expiration in either field
    if '*DIMESSO*' in stato_upper or '*DIMESSO*' in tipo_upper:
        return ('dimesso', 'non_operativo')
    if '*DECADUTO*' in stato_upper or '*DECADUTO*' in tipo_upper:
        return ('decaduto', 'non_operativo')
    
    # Active members
    if 'OPERATIVO' in stato_upper:
        return ('attivo', 'operativo')
    if 'NON OPERATIVO' in stato_upper:
        return ('attivo', 'non_operativo')
    
    # Default
    return ('attivo', 'in_formazione')


def convert_gender(sesso: str) -> str:
    """Convert sesso to M/F."""
    if not sesso:
        return ''
    sesso_upper = sesso.upper().strip()
    if 'MASCHIO' in sesso_upper or sesso_upper == 'M':
        return 'M'
    if 'FEMMINA' in sesso_upper or sesso_upper == 'F':
        return 'F'
    return sesso


def generate_adult_member_sql(row: Dict[str, str], index: int) -> str:
    """Generate SQL for a single adult member."""
    
    # Parse member type and status
    tipo_socio = row.get('tipo_socio', 'SOCIO ORDINARIO')
    stato = row.get('stato', 'OPERATIVO')
    member_type = convert_member_type(tipo_socio)
    member_status, volunteer_status = convert_member_status(stato, tipo_socio)
    
    # Build notes field
    notes_fields = [
        ('prov_nascita', 'Provincia nascita'),
        ('sesso', 'Sesso'),
        ('grup_sang', 'Gruppo sanguigno'),
        ('mansione', 'Qualifica'),
        ('disponibilita_territoriale', 'Disponibilità'),
        ('altre_lingue', 'Lingue'),
        ('problemi_alimentari', 'Allergie'),
        ('nuovocampo4', 'Patente'),
        ('titolo_di_studio', 'Titolo studio'),
    ]
    
    notes = build_notes_field(row, notes_fields)
    
    # Add work info if present
    tipologia_lavoro = row.get('tipologia_lavoro', '').strip()
    ente_azienda = row.get('ente_azienda', '').strip()
    if tipologia_lavoro or ente_azienda:
        work_info = f"Lavoro: {tipologia_lavoro}"
        if ente_azienda:
            work_info += f" presso {ente_azienda}"
        notes += f"\n{work_info}"
    
    # Add dismissal info if applicable
    if member_status in ['dimesso', 'decaduto']:
        dismissal_date = row.get('nuovocampo', '').strip()
        dismissal_reason = row.get('note', '').strip()
        if dismissal_date:
            notes += f"\nData {member_status}: {dismissal_date}"
        if dismissal_reason:
            notes += f"\nMotivo {member_status}: {dismissal_reason}"
    
    # Generate SQL
    sql = f"""
-- {member_type.upper()}: {row.get('cognome', '')} {row.get('nome', '')} - Matr. {row.get('matr', '')} - {member_status.upper()}
INSERT INTO `members` (
    `registration_number`,
    `member_type`,
    `member_status`,
    `volunteer_status`,
    `last_name`,
    `first_name`,
    `birth_date`,
    `birth_place`,
    `tax_code`,
    `registration_date`,
    `notes`,
    `created_at`,
    `updated_at`
) VALUES (
    {escape_sql_string(row.get('matr'))},
    '{member_type}',
    '{member_status}',
    '{volunteer_status}',
    {escape_sql_string(row.get('cognome'))},
    {escape_sql_string(row.get('nome'))},
    {parse_date(row.get('data_nascita'))},
    {escape_sql_string(row.get('luogo_nascita'))},
    {escape_sql_string(row.get('codicefiscale'))},
    {parse_date(row.get('anno_iscrizione'))},
    {escape_sql_string(notes)},
    {escape_sql_string(row.get('created')) if row.get('created') else 'NULL'},
    {escape_sql_string(row.get('last_upd')) if row.get('last_upd') else 'NULL'}
);
SET @member_id = LAST_INSERT_ID();
"""
    
    # Add contacts
    contacts = []
    if row.get('cellulare', '').strip():
        contacts.append(f"(@member_id, 'cellulare', {escape_sql_string(row['cellulare'])})")
    if row.get('telefono_fisso', '').strip():
        contacts.append(f"(@member_id, 'telefono_fisso', {escape_sql_string(row['telefono_fisso'])})")
    if row.get('e_mail', '').strip():
        contacts.append(f"(@member_id, 'email', {escape_sql_string(row['e_mail'])})")
    if row.get('e_mail_lavoro', '').strip():
        contacts.append(f"(@member_id, 'email', {escape_sql_string(row['e_mail_lavoro'])})")
    
    if contacts:
        sql += "\n-- Contatti\n"
        sql += "INSERT INTO `member_contacts` (`member_id`, `contact_type`, `value`) VALUES\n"
        sql += ",\n".join(f"    {c}" for c in contacts)
        sql += ";\n"
    
    # Add addresses
    addresses = []
    
    # Residence address
    if row.get('ind_resid', '').strip():
        street = row['ind_resid'].strip()
        number = ''
        # Try to extract number from street (simple heuristic)
        # Note: This may incorrectly extract numbers from street names like "Via Porta 2000"
        # If your data has complex street names, consider extracting numbers manually in CSV
        parts = street.rsplit(' ', 1)
        if len(parts) == 2 and parts[1].replace(',', '').replace('.', '').isdigit():
            street = parts[0]
            number = parts[1].replace(',', '').replace('.', '')
        
        addresses.append(
            f"(@member_id, 'residenza', {escape_sql_string(street)}, "
            f"{escape_sql_string(number)}, {escape_sql_string(row.get('comun_resid'))}, "
            f"{escape_sql_string(row.get('provincia_residenza'))}, {escape_sql_string(row.get('cap_resid'))})"
        )
    
    # Work address
    if row.get('indirizzo_lavoro', '').strip():
        street = row['indirizzo_lavoro'].strip()
        number = ''
        parts = street.rsplit(' ', 1)
        if len(parts) == 2 and parts[1].replace(',', '').replace('.', '').isdigit():
            street = parts[0]
            number = parts[1].replace(',', '').replace('.', '')
        
        addresses.append(
            f"(@member_id, 'domicilio', {escape_sql_string(street)}, "
            f"{escape_sql_string(number)}, {escape_sql_string(row.get('comune_lav'))}, "
            f"{escape_sql_string(row.get('prov_lavoro'))}, {escape_sql_string(row.get('cap_lavoro'))})"
        )
    
    if addresses:
        sql += "\n-- Indirizzi\n"
        sql += "INSERT INTO `member_addresses` (`member_id`, `address_type`, `street`, `number`, `city`, `province`, `cap`) VALUES\n"
        sql += ",\n".join(f"    {a}" for a in addresses)
        sql += ";\n"
    
    sql += "\n-- =====================================================\n"
    
    return sql


def generate_junior_member_sql(row: Dict[str, str], index: int) -> str:
    """Generate SQL for a single junior member."""
    
    # Parse status
    stato = row.get('nuovocampo64', 'SOCIO ORDINARIO')
    member_status = 'decaduto' if '*DECADUTO*' in stato.upper() else 'attivo'
    
    # Build notes field
    notes_parts = []
    
    # Basic info
    if row.get('nuovocampo5'):  # birth_province
        notes_parts.append(f"Provincia nascita: {row['nuovocampo5']}")
    if row.get('nuovocampo3'):  # gender
        gender = convert_gender(row['nuovocampo3'])
        notes_parts.append(f"Sesso: {gender}")
    if row.get('nuovocampo8'):  # blood_type
        notes_parts.append(f"Gruppo sanguigno: {row['nuovocampo8']}")
    if row.get('nuovocampo25'):  # anno_corso
        notes_parts.append(f"Anno corso: {row['nuovocampo25']}")
    if row.get('nuovocampo17'):  # lingue_cadetto
        notes_parts.append(f"Lingue cadetto: {row['nuovocampo17']}")
    if row.get('nuovocampo18'):  # allergie_cadetto
        notes_parts.append(f"Allergie cadetto: {row['nuovocampo18']}")
    if row.get('nuovocampo58'):  # allergie_genitore
        notes_parts.append(f"Allergie genitore: {row['nuovocampo58']}")
    
    # Dismissal info for decaduti
    if member_status == 'decaduto':
        if row.get('nuovocampo63'):
            notes_parts.append(f"Data decadenza: {row['nuovocampo63']}")
        if row.get('nuovocampo62'):
            notes_parts.append(f"Motivo decadenza: {row['nuovocampo62']}")
    
    # Mother info
    madre_nome = f"{row.get('nuovocampo46', '')} {row.get('nuovocampo47', '')}".strip()
    if madre_nome:
        notes_parts.append("\nMADRE:")
        notes_parts.append(f"Nome: {madre_nome}")
        if row.get('nuovocampo48'):
            luogo = f"{row['nuovocampo48']}"
            if row.get('nuovocampo49'):
                luogo += f" ({row['nuovocampo49']})"
            if row.get('nuovocampo50'):
                luogo += f" il {row['nuovocampo50']}"
            notes_parts.append(f"Nata a: {luogo}")
        if row.get('nuovocampo51'):
            notes_parts.append(f"CF: {row['nuovocampo51']}")
        if row.get('nuovocampo52'):
            indirizzo = row['nuovocampo52']
            if row.get('nuovocampo53'):
                indirizzo += f", {row['nuovocampo53']}"
            if row.get('nuovocampo54'):
                indirizzo += f" {row['nuovocampo54']}"
            if row.get('nuovocampo55'):
                indirizzo += f" ({row['nuovocampo55']})"
            notes_parts.append(f"Indirizzo: {indirizzo}")
        tels = []
        if row.get('nuovocampo59'):
            tels.append(row['nuovocampo59'])
        if row.get('nuovocampo60'):
            tels.append(row['nuovocampo60'])
        if tels:
            notes_parts.append(f"Tel: {' / '.join(tels)}")
        if row.get('nuovocampo56'):
            notes_parts.append(f"Email: {row['nuovocampo56']}")
    
    # Father info
    notes_parts.append("\nPADRE:")
    if row.get('nuovocampo35'):
        luogo = row['nuovocampo35']
        if row.get('nuovocampo36'):
            luogo += f" ({row['nuovocampo36']})"
        if row.get('nuovocampo37'):
            luogo += f" il {row['nuovocampo37']}"
        notes_parts.append(f"Nato a: {luogo}")
    if row.get('nuovocampo39'):
        indirizzo = row['nuovocampo39']
        if row.get('nuovocampo40'):
            indirizzo += f", {row['nuovocampo40']}"
        if row.get('nuovocampo41'):
            indirizzo += f" {row['nuovocampo41']}"
        if row.get('nuovocampo42'):
            indirizzo += f" ({row['nuovocampo42']})"
        notes_parts.append(f"Indirizzo: {indirizzo}")
    if row.get('nuovocampo43'):
        notes_parts.append(f"Tel fisso: {row['nuovocampo43']}")
    
    notes = '\n'.join(notes_parts)
    
    # Generate SQL
    sql = f"""
-- CADETTO: {row.get('nuovocampo1', '')} {row.get('nuovocampo2', '')} - Matr. {row.get('nuovocampo', '')} - {member_status.upper()}
INSERT INTO `junior_members` (
    `registration_number`,
    `member_status`,
    `last_name`,
    `first_name`,
    `birth_date`,
    `birth_place`,
    `tax_code`,
    `registration_date`,
    `notes`,
    `created_at`,
    `updated_at`
) VALUES (
    {escape_sql_string(row.get('nuovocampo'))},
    '{member_status}',
    {escape_sql_string(row.get('nuovocampo1'))},
    {escape_sql_string(row.get('nuovocampo2'))},
    {parse_date(row.get('nuovocampo6'))},
    {escape_sql_string(row.get('nuovocampo4'))},
    {escape_sql_string(row.get('nuovocampo7'))},
    {parse_date(row.get('nuovocampo61'))},
    {escape_sql_string(notes)},
    NOW(),
    NOW()
);
SET @junior_id = LAST_INSERT_ID();
"""
    
    # Add guardian (father)
    if row.get('nuovocampo33') or row.get('nuovocampo34'):
        sql += f"""
-- Tutore principale (Padre)
INSERT INTO `junior_member_guardians` (
    `junior_member_id`,
    `guardian_type`,
    `last_name`,
    `first_name`,
    `tax_code`,
    `phone`,
    `email`
) VALUES (
    @junior_id,
    'padre',
    {escape_sql_string(row.get('nuovocampo33'))},
    {escape_sql_string(row.get('nuovocampo34'))},
    {escape_sql_string(row.get('nuovocampo38'))},
    {escape_sql_string(row.get('nuovocampo44'))},
    {escape_sql_string(row.get('nuovocampo45'))}
);
"""
    
    # Add contacts
    contacts = []
    if row.get('nuovocampo14', '').strip():
        contacts.append(f"(@junior_id, 'cellulare', {escape_sql_string(row['nuovocampo14'])})")
    if row.get('nuovocampo15', '').strip():
        contacts.append(f"(@junior_id, 'email', {escape_sql_string(row['nuovocampo15'])})")
    
    if contacts:
        sql += "\n-- Contatti cadetto\n"
        sql += "INSERT INTO `junior_member_contacts` (`junior_member_id`, `contact_type`, `value`) VALUES\n"
        sql += ",\n".join(f"    {c}" for c in contacts)
        sql += ";\n"
    
    # Add addresses
    addresses = []
    
    # Junior residence
    if row.get('nuovocampo9', '').strip():
        street = row['nuovocampo9'].strip()
        number = ''
        parts = street.rsplit(' ', 1)
        if len(parts) == 2 and parts[1].replace(',', '').replace('.', '').isdigit():
            street = parts[0]
            number = parts[1].replace(',', '').replace('.', '')
        
        addresses.append(
            f"(@junior_id, 'residenza', {escape_sql_string(street)}, "
            f"{escape_sql_string(number)}, {escape_sql_string(row.get('nuovocampo11'))}, "
            f"{escape_sql_string(row.get('nuovocampo12'))}, {escape_sql_string(row.get('nuovocampo10'))})"
        )
    
    # Father's address (if different)
    if row.get('nuovocampo39', '').strip():
        father_addr = row['nuovocampo39'].strip()
        junior_addr = row.get('nuovocampo9', '').strip()
        if father_addr != junior_addr:
            street = father_addr
            number = ''
            parts = street.rsplit(' ', 1)
            if len(parts) == 2 and parts[1].replace(',', '').replace('.', '').isdigit():
                street = parts[0]
                number = parts[1].replace(',', '').replace('.', '')
            
            addresses.append(
                f"(@junior_id, 'domicilio', {escape_sql_string(street)}, "
                f"{escape_sql_string(number)}, {escape_sql_string(row.get('nuovocampo41'))}, "
                f"{escape_sql_string(row.get('nuovocampo42'))}, {escape_sql_string(row.get('nuovocampo40'))})"
            )
    
    # Mother's address (if different)
    if row.get('nuovocampo52', '').strip():
        mother_addr = row['nuovocampo52'].strip()
        junior_addr = row.get('nuovocampo9', '').strip()
        if mother_addr != junior_addr:
            street = mother_addr
            number = ''
            parts = street.rsplit(' ', 1)
            if len(parts) == 2 and parts[1].replace(',', '').replace('.', '').isdigit():
                street = parts[0]
                number = parts[1].replace(',', '').replace('.', '')
            
            addresses.append(
                f"(@junior_id, 'domicilio', {escape_sql_string(street)}, "
                f"{escape_sql_string(number)}, {escape_sql_string(row.get('nuovocampo54'))}, "
                f"{escape_sql_string(row.get('nuovocampo55'))}, {escape_sql_string(row.get('nuovocampo53'))})"
            )
    
    if addresses:
        sql += "\n-- Indirizzi\n"
        sql += "INSERT INTO `junior_member_addresses` (`junior_member_id`, `address_type`, `street`, `number`, `city`, `province`, `cap`) VALUES\n"
        sql += ",\n".join(f"    {a}" for a in addresses)
        sql += ";\n"
    
    sql += "\n-- =====================================================\n"
    
    return sql


def main():
    """Main conversion function."""
    if len(sys.argv) < 2:
        print("Usage: python3 csv_to_sql_converter.py <csv_file>", file=sys.stderr)
        print("Example: python3 csv_to_sql_converter.py soci.csv > import_soci_adulti_completo.sql", file=sys.stderr)
        sys.exit(1)
    
    csv_file = sys.argv[1]
    is_cadetti = 'cadetti' in csv_file.lower()
    
    # Determine file type and print header
    if is_cadetti:
        total_records = 53
        title = "CADETTI"
        table_name = "junior_members"
    else:
        total_records = 175
        title = "SOCI ADULTI"
        table_name = "members"
    
    # Print SQL header
    print(f"""-- =====================================================
-- IMPORT {title} DA VECCHIO GESTIONALE
-- Totale record: {total_records}
-- Data: {datetime.now().strftime('%Y-%m-%d')}
-- Generato automaticamente da csv_to_sql_converter.py
-- =====================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
START TRANSACTION;

-- =====================================================
-- DATI IMPORTATI DA {csv_file}
-- =====================================================
""")
    
    # Read and process CSV
    # Note: Using errors='strict' to fail fast on encoding issues
    # If your CSV has encoding problems, convert it to UTF-8 first
    try:
        with open(csv_file, 'r', encoding='utf-8', errors='strict') as f:
            reader = csv.DictReader(f)
            for i, row in enumerate(reader, 1):
                if is_cadetti:
                    print(generate_junior_member_sql(row, i))
                else:
                    print(generate_adult_member_sql(row, i))
    except FileNotFoundError:
        print(f"Error: File '{csv_file}' not found", file=sys.stderr)
        sys.exit(1)
    except UnicodeDecodeError as e:
        print(f"Error: File encoding issue - {e}", file=sys.stderr)
        print("Try converting the CSV file to UTF-8 encoding first", file=sys.stderr)
        sys.exit(1)
    except csv.Error as e:
        print(f"Error: Invalid CSV format - {e}", file=sys.stderr)
        sys.exit(1)
    except Exception as e:
        print(f"Error processing CSV: {e}", file=sys.stderr)
        sys.exit(1)
    
    # Print SQL footer
    print("""
-- =====================================================
-- FINE INSERIMENTI
-- =====================================================

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- STATISTICHE FINALI
-- =====================================================
""")
    
    if is_cadetti:
        print("""SELECT 'IMPORT CADETTI COMPLETATO' AS Stato,
    COUNT(*) AS Totale,
    SUM(CASE WHEN member_status='attivo' THEN 1 ELSE 0 END) AS Attivi,
    SUM(CASE WHEN member_status='decaduto' THEN 1 ELSE 0 END) AS Decaduti
FROM `junior_members`;

-- Verifica tutori
SELECT 'TUTORI IMPORTATI' AS Tipo, COUNT(*) AS Totale
FROM `junior_member_guardians`;

-- Verifica contatti
SELECT 'CONTATTI IMPORTATI' AS Tipo, COUNT(*) AS Totale
FROM `junior_member_contacts`;

-- Verifica indirizzi
SELECT 'INDIRIZZI IMPORTATI' AS Tipo, COUNT(*) AS Totale
FROM `junior_member_addresses`;
""")
    else:
        print("""SELECT 'IMPORT SOCI ADULTI COMPLETATO' AS Stato,
    COUNT(*) AS Totale,
    SUM(CASE WHEN member_status='attivo' THEN 1 ELSE 0 END) AS Attivi,
    SUM(CASE WHEN member_status='dimesso' THEN 1 ELSE 0 END) AS Dimessi,
    SUM(CASE WHEN member_status='decaduto' THEN 1 ELSE 0 END) AS Decaduti,
    SUM(CASE WHEN volunteer_status='operativo' THEN 1 ELSE 0 END) AS Operativi,
    SUM(CASE WHEN volunteer_status='non_operativo' THEN 1 ELSE 0 END) AS NonOperativi,
    SUM(CASE WHEN member_type='fondatore' THEN 1 ELSE 0 END) AS Fondatori,
    SUM(CASE WHEN member_type='ordinario' THEN 1 ELSE 0 END) AS Ordinari
FROM `members`;

-- Verifica contatti
SELECT 'CONTATTI IMPORTATI' AS Tipo, COUNT(*) AS Totale
FROM `member_contacts`;

-- Verifica indirizzi
SELECT 'INDIRIZZI IMPORTATI' AS Tipo, COUNT(*) AS Totale
FROM `member_addresses`;
""")


if __name__ == '__main__':
    main()
