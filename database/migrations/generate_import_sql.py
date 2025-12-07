#!/usr/bin/env python3
"""
Script per generare automaticamente il file SQL di importazione
dei 175 soci dal vecchio gestionale al nuovo sistema EasyVol.

Uso:
    python3 generate_import_sql.py soci.csv > import_statements.sql
    
Il CSV deve contenere i seguenti campi (header):
matr, tipo_socio, stato, cognome, nome, data_nascita, luogo_nascita, 
prov_nascita, codicefiscale, problemialimentari, grup_sang, anno_iscr, 
mansione, disp_territ, cellulare, tel_fisso, e_mail, ind_resid, 
cap_resid, comun_resid, provincia, note, nuovocampo1, altre_lingue, 
prob_alim, nuovocampo6, created, last_upd
"""

import csv
import sys
import re
from datetime import datetime

def escape_sql(value):
    """Escape valori per SQL, gestisce NULL e virgolette"""
    if value is None or value == '' or value.strip() == '':
        return 'NULL'
    # Escape virgolette singole raddoppiandole
    escaped = str(value).replace("'", "''")
    return f"'{escaped}'"

def format_date(date_str):
    """Converte date in formato SQL (YYYY-MM-DD)"""
    if not date_str or date_str.strip() == '':
        return 'NULL'
    
    # Prova diversi formati comuni
    formats = [
        '%Y-%m-%d',                # YYYY-MM-DD
        '%d/%m/%Y',                # DD/MM/YYYY
        '%d-%m-%Y',                # DD-MM-YYYY
        '%Y/%m/%d',                # YYYY/MM/DD
        '%Y-%m-%d %H:%M:%S',       # YYYY-MM-DD HH:MM:SS
        '%d/%m/%Y %H:%M:%S',       # DD/MM/YYYY HH:MM:SS
    ]
    
    for fmt in formats:
        try:
            dt = datetime.strptime(date_str.strip(), fmt)
            return f"'{dt.strftime('%Y-%m-%d')}'"
        except ValueError:
            continue
    
    # Se nessun formato funziona, restituisce NULL
    print(f"Warning: Could not parse date '{date_str}', using NULL", file=sys.stderr)
    return 'NULL'

def format_timestamp(timestamp_str):
    """Converte timestamp in formato SQL completo"""
    if not timestamp_str or timestamp_str.strip() == '':
        return 'CURRENT_TIMESTAMP'
    
    # Prova diversi formati comuni
    formats = [
        '%Y-%m-%d %H:%M:%S',       # YYYY-MM-DD HH:MM:SS
        '%d/%m/%Y %H:%M:%S',       # DD/MM/YYYY HH:MM:SS
        '%Y-%m-%d',                # YYYY-MM-DD
        '%d/%m/%Y',                # DD/MM/YYYY
    ]
    
    for fmt in formats:
        try:
            dt = datetime.strptime(timestamp_str.strip(), fmt)
            return f"'{dt.strftime('%Y-%m-%d %H:%M:%S')}'"
        except ValueError:
            continue
    
    # Se nessun formato funziona, usa CURRENT_TIMESTAMP
    return 'CURRENT_TIMESTAMP'

def map_member_type(tipo_socio):
    """Mappa il tipo socio dal vecchio gestionale al nuovo"""
    if not tipo_socio:
        return 'ordinario'
    
    tipo_upper = tipo_socio.upper()
    if 'FONDATORE' in tipo_upper:
        return 'fondatore'
    return 'ordinario'

def map_member_status(stato):
    """Mappa lo stato del socio"""
    if not stato:
        return 'attivo'
    
    stato_upper = stato.upper()
    if 'DIMESSO' in stato_upper or '*DIMESSO*' in stato_upper:
        return 'dimesso'
    if 'DECADUTO' in stato_upper or '*DECADUTO*' in stato_upper:
        return 'decaduto'
    return 'attivo'

def map_volunteer_status(member_status, stato_orig):
    """Determina volunteer_status basato su member_status e stato originale"""
    if member_status != 'attivo':
        return 'non_operativo'
    
    if not stato_orig:
        return 'operativo'
    
    stato_upper = stato_orig.upper()
    if 'OPERATIVO' in stato_upper:
        return 'operativo'
    if 'NON OPERATIVO' in stato_upper or 'NON_OPERATIVO' in stato_upper:
        return 'non_operativo'
    
    return 'operativo'

def map_gender(problemialimentari):
    """Mappa il campo problemialimentari in gender (basato su descrizione problema)"""
    if not problemialimentari:
        return 'NULL'
    
    value_upper = problemialimentari.upper()
    if 'MASCHIO' in value_upper or 'UOMO' in value_upper or value_upper == 'M':
        return "'M'"
    if 'FEMMINA' in value_upper or 'DONNA' in value_upper or value_upper == 'F':
        return "'F'"
    
    # Se non riconosciuto, prova a dedurre dal nome o restituisce NULL
    return 'NULL'

def determine_nationality(luogo_nascita):
    """Determina la nazionalità dal luogo di nascita"""
    if not luogo_nascita:
        return "'Italiana'"
    
    luogo_upper = luogo_nascita.upper()
    
    # Paesi esteri comuni
    foreign_countries = {
        'CUBA': 'Cubana',
        'ROMANIA': 'Rumena', 
        'RUMANIA': 'Rumena',
        'GERMANIA': 'Tedesca',
        'FRANCIA': 'Francese',
        'SPAGNA': 'Spagnola',
        'INGHILTERRA': 'Inglese',
        'SVIZZERA': 'Svizzera',
        'AUSTRIA': 'Austriaca',
        'ALBANIA': 'Albanese',
        'MAROCCO': 'Marocchina',
        'TUNISIA': 'Tunisina',
        'EGITTO': 'Egiziana',
        'CINA': 'Cinese',
        'BRASILE': 'Brasiliana',
        'ARGENTINA': 'Argentina',
        'USA': 'Americana',
        'STATI UNITI': 'Americana',
    }
    
    for country, nationality in foreign_countries.items():
        if country in luogo_upper:
            return f"'{nationality}'"
    
    # Default: Italiana
    return "'Italiana'"

def extract_civic_number(indirizzo):
    """Estrae il numero civico dall'indirizzo se possibile"""
    if not indirizzo:
        return '', ''
    
    # Pattern per numero civico alla fine: "VIA ROMA 10", "VIA ROMA, 10"
    match = re.search(r'[,\s]+(\d+[A-Za-z]?)$', indirizzo)
    if match:
        civic = match.group(1)
        street = indirizzo[:match.start()].strip()
        return street, civic
    
    # Se non trovato, ritorna tutto come via
    return indirizzo, ''

def build_notes(disp_territ, altre_lingue, prob_alim, nuovocampo6):
    """Costruisce il campo notes concatenando le informazioni"""
    notes_parts = []
    
    if disp_territ and disp_territ.strip():
        notes_parts.append(f"Disponibilità: {disp_territ.strip()}")
    
    if altre_lingue and altre_lingue.strip():
        notes_parts.append(f"Lingue: {altre_lingue.strip()}")
    
    if prob_alim and prob_alim.strip():
        notes_parts.append(f"Allergie: {prob_alim.strip()}")
    
    if nuovocampo6 and nuovocampo6.strip():
        notes_parts.append(f"Patente: {nuovocampo6.strip()}")
    
    if notes_parts:
        combined = ' - '.join(notes_parts)
        return escape_sql(combined)
    
    return 'NULL'

def generate_member_insert(row, counter):
    """Genera l'INSERT statement per un socio"""
    
    # Estrai e mappa i campi
    matr = row.get('matr', '').strip()
    tipo_socio = row.get('tipo_socio', '').strip()
    stato = row.get('stato', '').strip()
    cognome = row.get('cognome', '').strip().upper()
    nome = row.get('nome', '').strip().upper()
    data_nascita = row.get('data_nascita', '').strip()
    luogo_nascita = row.get('luogo_nascita', '').strip()
    prov_nascita = row.get('prov_nascita', '').strip()
    codicefiscale = row.get('codicefiscale', '').strip()
    problemialimentari = row.get('problemialimentari', '').strip()
    grup_sang = row.get('grup_sang', '').strip()
    anno_iscr = row.get('anno_iscr', '').strip()
    mansione = row.get('mansione', '').strip()
    disp_territ = row.get('disp_territ', '').strip()
    cellulare = row.get('cellulare', '').strip()
    tel_fisso = row.get('tel_fisso', '').strip()
    e_mail = row.get('e_mail', '').strip()
    ind_resid = row.get('ind_resid', '').strip()
    cap_resid = row.get('cap_resid', '').strip()
    comun_resid = row.get('comun_resid', '').strip()
    provincia = row.get('provincia', '').strip()
    note = row.get('note', '').strip()
    nuovocampo1 = row.get('nuovocampo1', '').strip()
    altre_lingue = row.get('altre_lingue', '').strip()
    prob_alim = row.get('prob_alim', '').strip()
    nuovocampo6 = row.get('nuovocampo6', '').strip()
    created = row.get('created', '').strip()
    last_upd = row.get('last_upd', '').strip()
    
    # Mappa i valori
    member_type = map_member_type(tipo_socio)
    member_status = map_member_status(stato)
    volunteer_status = map_volunteer_status(member_status, stato)
    gender = map_gender(problemialimentari)
    nationality = determine_nationality(luogo_nascita)
    notes_combined = build_notes(disp_territ, altre_lingue, prob_alim, nuovocampo6)
    
    # Gestione dismissal per dimessi/decaduti
    dismissal_date = format_date(nuovocampo1) if member_status in ['dimesso', 'decaduto'] else 'NULL'
    dismissal_reason = escape_sql(note) if member_status in ['dimesso', 'decaduto'] and note else 'NULL'
    
    # Format dates and timestamps
    birth_date = format_date(data_nascita)
    registration_date = format_date(anno_iscr)
    created_at = format_timestamp(created) if created else 'CURRENT_TIMESTAMP'
    updated_at = format_timestamp(last_upd) if last_upd else 'CURRENT_TIMESTAMP'
    
    # Estrai numero civico se possibile
    street, civic_number = extract_civic_number(ind_resid)
    if not civic_number and ind_resid:
        street = ind_resid
        civic_number = ''
    
    # Genera ID variabile per questo membro
    member_var = f"@member_{matr.replace('-', '_').replace(' ', '_')}_id"
    
    sql = []
    sql.append(f"-- ----------------------------------------------------------------")
    sql.append(f"-- SOCIO {counter}: {matr} - {cognome} {nome} ({tipo_socio} - {stato})")
    sql.append(f"-- ----------------------------------------------------------------")
    sql.append(f"INSERT INTO members (")
    sql.append(f"    registration_number, member_type, member_status, volunteer_status,")
    sql.append(f"    last_name, first_name, birth_date, birth_place, birth_province,")
    sql.append(f"    tax_code, gender, nationality, blood_type,")
    sql.append(f"    registration_date, qualification, notes,")
    sql.append(f"    dismissal_date, dismissal_reason, photo_path,")
    sql.append(f"    created_at, updated_at")
    sql.append(f") VALUES (")
    sql.append(f"    {escape_sql(matr)}, '{member_type}', '{member_status}', '{volunteer_status}',")
    sql.append(f"    {escape_sql(cognome)}, {escape_sql(nome)}, {birth_date}, {escape_sql(luogo_nascita)}, {escape_sql(prov_nascita)},")
    sql.append(f"    {escape_sql(codicefiscale)}, {gender}, {nationality}, {escape_sql(grup_sang)},")
    sql.append(f"    {registration_date}, {escape_sql(mansione)}, {notes_combined},")
    sql.append(f"    {dismissal_date}, {dismissal_reason}, NULL,")
    sql.append(f"    {created_at}, {updated_at}")
    sql.append(f");")
    sql.append(f"SET {member_var} = LAST_INSERT_ID();")
    sql.append("")
    
    # Inserisci contatti se presenti
    if cellulare:
        sql.append(f"INSERT INTO member_contacts (member_id, contact_type, contact_value, is_primary, notes)")
        sql.append(f"VALUES ({member_var}, 'cellulare', {escape_sql(cellulare)}, 1, NULL);")
        sql.append("")
    
    if tel_fisso:
        primary = '0' if cellulare else '1'  # Primario solo se non c'è cellulare
        sql.append(f"INSERT INTO member_contacts (member_id, contact_type, contact_value, is_primary, notes)")
        sql.append(f"VALUES ({member_var}, 'telefono', {escape_sql(tel_fisso)}, {primary}, NULL);")
        sql.append("")
    
    if e_mail:
        sql.append(f"INSERT INTO member_contacts (member_id, contact_type, contact_value, is_primary, notes)")
        sql.append(f"VALUES ({member_var}, 'email', {escape_sql(e_mail)}, 1, NULL);")
        sql.append("")
    
    # Inserisci indirizzo se presente
    if ind_resid:
        sql.append(f"INSERT INTO member_addresses (member_id, address_type, street, number, city, province, cap, is_primary)")
        sql.append(f"VALUES ({member_var}, 'residenza', {escape_sql(street)}, {escape_sql(civic_number)}, {escape_sql(comun_resid)}, {escape_sql(provincia)}, {escape_sql(cap_resid)}, 1);")
        sql.append("")
    
    return '\n'.join(sql)

def main():
    if len(sys.argv) < 2:
        print("Uso: python3 generate_import_sql.py <file.csv>", file=sys.stderr)
        print("\nQuesto script legge un CSV con i dati dei soci e genera SQL INSERT statements", file=sys.stderr)
        sys.exit(1)
    
    csv_file = sys.argv[1]
    
    try:
        with open(csv_file, 'r', encoding='utf-8') as f:
            reader = csv.DictReader(f)
            
            counter = 1
            for row in reader:
                sql = generate_member_insert(row, counter)
                print(sql)
                counter += 1
            
            print(f"-- =====================================================", file=sys.stderr)
            print(f"-- Generati INSERT per {counter - 1} soci", file=sys.stderr)
            print(f"-- =====================================================", file=sys.stderr)
            
    except FileNotFoundError:
        print(f"Errore: File '{csv_file}' non trovato", file=sys.stderr)
        sys.exit(1)
    except Exception as e:
        print(f"Errore durante l'elaborazione: {e}", file=sys.stderr)
        sys.exit(1)

if __name__ == '__main__':
    main()
