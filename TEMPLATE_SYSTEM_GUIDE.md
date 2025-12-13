# Guida al Sistema Template di Stampa EasyVol

## Panoramica

Il sistema di gestione template di EasyVol permette di creare documenti personalizzati in formato HTML/PDF per tutte le entità del sistema (soci, cadetti, mezzi, eventi, riunioni).

## Installazione Template Predefiniti

Per installare tutti i template standard nel database, eseguire le seguenti migrazioni SQL nell'ordine indicato:

1. `migrations/add_print_templates_table.sql` - Crea la tabella print_templates
2. `migrations/insert_default_print_templates.sql` - Inserisce i primi 10 template base
3. `migrations/insert_additional_print_templates.sql` - Inserisce i template aggiuntivi richiesti

```bash
# Esempio con MySQL CLI
mysql -u username -p database_name < migrations/add_print_templates_table.sql
mysql -u username -p database_name < migrations/insert_default_print_templates.sql
mysql -u username -p database_name < migrations/insert_additional_print_templates.sql
```

## Tipi di Template

### 1. **Single** - Documento Singolo
Template per un singolo record (es. certificato di iscrizione, scheda socio)
- **Data Scope**: `single`
- **Richiede**: `record_id` come parametro
- **Esempio**: Certificato di Iscrizione, Tessera Socio

### 2. **List** - Lista/Elenco
Template per elenchi tabulari di più record
- **Data Scope**: `all`, `filtered`, `custom`
- **Utilizza**: Loop `{{#each records}}`
- **Esempio**: Libro Soci, Elenco Mezzi, Elenco Eventi

### 3. **Multi-page** - Multi-pagina
Genera un documento con più pagine (una per record)
- **Data Scope**: `filtered`
- **Richiede**: `record_ids` o `filters`
- **Esempio**: Tessere Multiple

### 4. **Relational** - Relazionale
Template con dati correlati da tabelle relazionate

#### Single Record con Relazioni
- **Data Scope**: `single`
- **Richiede**: `record_id`
- **Esempio**: Scheda Completa Socio (con contatti, indirizzi, patenti)

#### Lista con Relazioni
- **Data Scope**: `filtered` o `all`
- **Richiede**: `filters` opzionali
- **Esempio**: Elenco Soci con Ruoli, Elenco con Intolleranze

## Template Disponibili per Entità

### SOCI (members)
1. ✅ **Libro Soci** - Elenco completo con tutti i campi (list, landscape)
2. ✅ **Scheda Completa Socio** - Scheda dettagliata con relazioni (relational, single)
3. ✅ **Certificato di Iscrizione** - Certificato ufficiale (single)
4. ✅ **Tessera Socio** - Formato card (single)
5. ✅ **Elenco Soci Attivi** - Solo soci attivi (list)
6. ✅ **Elenco Soci Sospesi** - Soci sospesi/in congedo/aspettativa (list)
7. ✅ **Elenco Soci Attivi con Contatti** - Con cellulare ed email (list)
8. ✅ **Elenco Soci Attivi con Ruoli** - Mostra ruoli ricoperti (relational, filtered)
9. ✅ **Elenco Soci con Intolleranze Alimentari** - Con info salute (relational, filtered)
10. ✅ **Foglio Firma Assemblea con Deleghe** - Per presenze assemblee (list, landscape)
11. ✅ **Elenco Telefonico Soci** - Lista con contatti (list)
12. ✅ **Tessere Soci Multiple** - Più tessere in un PDF (multi_page)

### SOCI MINORENNI / CADETTI (junior_members)
1. ✅ **Libro Soci Minorenni** - Elenco completo cadetti (list, landscape)
2. ✅ **Scheda Socio Minorenne Completa** - Con genitori/tutori (relational, single)

### MEZZI (vehicles)
1. ✅ **Scheda Tecnica Mezzo** - Scheda completa con manutenzioni (relational, single)
2. ✅ **Elenco Mezzi** - Lista completa mezzi (list)
3. ✅ **Elenco Mezzi con Scadenze** - Con scadenze assicurazione e revisione (list, landscape)

### EVENTI (events)
1. ✅ **Elenco Eventi** - Lista con tipologia, date e orari (list, landscape)
2. ✅ **Scheda Evento con Interventi** - Dettaglio evento con interventi (relational, single)

### RIUNIONI/ASSEMBLEE (meetings)
1. ✅ **Verbale di Riunione** - Verbale ufficiale (relational, single)
2. ✅ **Foglio Presenze Riunione** - Foglio firme (relational, single)
3. ✅ **Avviso di Assemblea** - Convocazione ufficiale (single)
4. ✅ **Ordine del Giorno Riunione** - ODG con punti (relational, single)

## Sintassi Template (Handlebars-like)

### Variabili Semplici
```html
{{nome_campo}}
```
Esempio: `{{first_name}}`, `{{last_name}}`, `{{registration_number}}`

### Loop (Iterazione Array)
```html
{{#each nome_array}}
  {{campo1}}
  {{campo2}}
  {{@index}}  <!-- Indice 1-based -->
{{/each}}
```

Esempio per contatti:
```html
{{#each member_contacts}}
  <li>{{contact_type}}: {{value}}</li>
{{/each}}
```

### Condizionali
```html
{{#if campo}}
  Contenuto mostrato solo se campo esiste e non è vuoto
{{/if}}
```

### Helper Speciali
- `{{@index}}` - Indice corrente nel loop (1-based per visualizzazione)

## Relazioni Disponibili

### Members (Soci)
- `member_contacts` - Contatti
- `member_addresses` - Indirizzi
- `member_licenses` - Patenti
- `member_courses` - Corsi e Qualifiche
- `member_roles` - Ruoli ricoperti
- `member_employment` - Datore di lavoro
- `member_education` - Istruzione
- `member_health` - Salute e intolleranze
- `member_fees` - Quote associative
- `member_notes` - Note
- `member_sanctions` - Sanzioni

### Junior Members (Cadetti)
- `junior_member_guardians` - Genitori/Tutori
- `junior_member_contacts` - Contatti
- `junior_member_addresses` - Indirizzi
- `junior_member_health` - Salute e intolleranze
- `junior_member_fees` - Quote
- `junior_member_notes` - Note
- `junior_member_sanctions` - Sanzioni

### Vehicles (Mezzi)
- `vehicle_maintenance` - Manutenzioni
- `vehicle_documents` - Documenti

### Meetings (Riunioni)
- `meeting_participants` - Partecipanti
- `meeting_agenda` - Ordine del giorno
- `meeting_attachments` - Allegati

### Events (Eventi)
- `interventions` - Interventi registrati
- `event_members` - Membri coinvolti
- `event_vehicles` - Mezzi utilizzati

## Formattazione Automatica

Il sistema formatta automaticamente:
- **Date**: I campi con `_date` vengono formattati come `dd/mm/yyyy`
- **DateTime**: I campi con `_time` vengono formattati come `dd/mm/yyyy hh:mm`
- **HTML Escape**: Tutti i valori sono automaticamente escaped per sicurezza

## CSS Personalizzato

Ogni template può avere CSS personalizzato per la stampa:

```css
body { font-family: Arial, sans-serif; }
h1 { color: #333; }

@media print {
  thead { display: table-header-group; }
  tr { page-break-inside: avoid; }
  .page-break { page-break-after: always; }
}
```

## Generazione Documenti

### Da Interfaccia Web
1. Vai su **Gestione Template Stampe**
2. Seleziona il template desiderato
3. Clicca su "Anteprima" per vedere l'output
4. Usa "Modifica" per editare prima della stampa
5. Usa "Stampa" o "Download PDF"

### Da Codice PHP
```php
use EasyVol\Controllers\PrintTemplateController;

$controller = new PrintTemplateController($db, $config);

// Per template singolo
$result = $controller->generate($templateId, [
    'record_id' => 123
]);

// Per template lista con filtri
$result = $controller->generate($templateId, [
    'filters' => [
        'member_status' => 'attivo',
        'member_type' => 'ordinario'
    ]
]);

// Result contiene:
// - html: Contenuto HTML generato
// - css: CSS personalizzato
// - header: Contenuto header
// - footer: Contenuto footer
// - watermark: Testo watermark
// - page_format: Formato pagina (A4, A3, Letter)
// - page_orientation: Orientamento (portrait, landscape)
```

## Esportazione/Importazione Template

### Esportazione
1. Vai su template da esportare
2. Clicca "Esporta"
3. Scarica file JSON

### Importazione
1. Clicca "Importa" nella lista template
2. Seleziona file JSON precedentemente esportato
3. Il template viene importato con suffisso "(importato)" se esiste già

## Best Practices

1. **Testa sempre**: Usa l'anteprima prima di stampare documenti ufficiali
2. **Backup**: Esporta i template personalizzati come backup
3. **Naming**: Usa nomi descrittivi per i template
4. **CSS Print**: Usa `@media print` per stili specifici della stampa
5. **Page Breaks**: Usa `page-break-after: always` per forzare nuove pagine
6. **Performance**: I template con molte relazioni possono richiedere più tempo

## Risoluzione Problemi

### Variabili non sostituite
- Verifica che il nome del campo sia corretto
- Controlla che il campo esista nella tabella
- Usa `getAvailableVariables()` per vedere i campi disponibili

### Loop non funzionano
- Verifica la sintassi: `{{#each array_name}}...{{/each}}`
- Controlla che la relazione sia configurata correttamente
- Verifica che il data_scope sia corretto per il tipo di template

### Errori di generazione
- Controlla i log per messaggi di errore specifici
- Verifica i permessi utente per l'entità
- Controlla che i record esistano nel database

## Supporto Tecnico

Per problemi o domande:
- Verifica la documentazione tecnica in `/src/Controllers/PrintTemplateController.php`
- Consulta gli esempi nei template predefiniti
- Controlla i log di sistema per errori specifici

---

**Versione Sistema**: 1.0  
**Data Ultimo Aggiornamento**: 2025-12-13  
**Autore**: EasyVol Development Team
