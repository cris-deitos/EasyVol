# Sistema di Stampa Avanzato - Guida Rapida

## 🚀 Installazione

Il nuovo sistema di stampa avanzato è già installato e pronto all'uso!

### Requisiti
- ✅ PHP 8.3+ (già installato)
- ✅ Composer dependencies (mPDF già incluso)
- ✅ Database configurato
- ✅ Permessi scrittura sulla directory `templates/`

### Verifica Installazione

1. Assicurati che la directory `templates/` sia scrivibile:
   ```bash
   chmod 755 templates/
   ```

2. Verifica che i template di esempio siano presenti:
   ```bash
   ls -la templates/members/
   ls -la templates/junior_members/
   ls -la templates/vehicles/
   ```

## 📋 Utilizzo

### 1. Accesso al Sistema

Accedi al nuovo sistema di stampa tramite:
- URL diretto: `http://tuosito.com/public/enhanced_print.php`
- Oppure aggiungi un link nel menu laterale

### 2. Selezione Template

1. Scegli il tipo di documento (Soci, Cadetti, Mezzi, ecc.)
2. Clicca sul template desiderato
3. Il sistema mostra le opzioni di generazione

### 3. Generazione Documento

#### Per Template Singolo (Single Record)
- Inserisci l'**ID del record** nel campo
- Clicca "Anteprima" per vedere il risultato
- Clicca "Genera PDF" per scaricare
- Clicca "Modifica e Stampa" per aprire l'editor

#### Per Lista (List)
- Opzionale: Applica filtri (stato, tipo, date)
- Clicca "Anteprima" o "Genera PDF"

#### Per Multi-Pagina
- Inserisci IDs separati da virgola: `1,2,3,4,5`
- Oppure usa i filtri per selezione automatica

### 4. Modifica Pre-Stampa

1. Clicca "Modifica e Stampa"
2. Si apre l'editor WYSIWYG (TinyMCE)
3. Modifica il testo, formattazione, tabelle, ecc.
4. Clicca "Salva come PDF" o "Stampa"

## 🎨 Creazione Template

### Template Semplice

Crea un file JSON in `templates/{entity_type}/nome_template.json`:

```json
{
  "name": "Il Mio Template",
  "description": "Descrizione template",
  "type": "single",
  "format": "A4",
  "orientation": "portrait",
  "margins": {
    "top": 15,
    "bottom": 15,
    "left": 15,
    "right": 15
  },
  "html": "<h1>{{first_name}} {{last_name}}</h1><p>Matricola: {{registration_number}}</p>",
  "css": "h1 { color: blue; }"
}
```

### Template con Dati Multi-Tabella

```json
{
  "name": "Scheda con Contatti",
  "type": "single",
  "format": "A4",
  "orientation": "portrait",
  "relations": {
    "contacts": {
      "table": "member_contacts",
      "foreign_key": "member_id"
    }
  },
  "html": "<h1>{{first_name}} {{last_name}}</h1>\n{{#if contacts}}\n<h2>Contatti</h2>\n<ul>\n{{#each contacts}}\n<li>{{contact_type}}: {{contact_value}}</li>\n{{/each}}\n</ul>\n{{/if}}"
}
```

## 🔄 Migrazione Template Database

Se hai template esistenti nel database:

1. Vai a `/public/template_migration.php`
2. Seleziona i template da migrare
3. Clicca "Migra Template Selezionati"
4. I nuovi file JSON saranno creati in `templates/`
5. I template database originali rimangono inalterati

## 🎯 Template Disponibili

### Soci (Members)
- ✅ **Tessera Socio** - Carta associativa formato carta di credito
- ✅ **Scheda Socio Completa** - Con dati anagrafici, contatti, indirizzi, corsi, patenti
- ✅ **Elenco Soci con Contatti** - Lista con email e cellulare

### Cadetti (Junior Members)
- ✅ **Scheda Cadetto Completa** - Con genitori/tutori, contatti, salute

### Mezzi (Vehicles)
- ✅ **Scheda Mezzo Completa** - Con dati tecnici, scadenze, manutenzioni

## 📚 Sintassi Template

### Variabili
```html
{{first_name}}           <!-- Campo semplice -->
{{association.name}}     <!-- Campo annidato -->
{{current_date}}         <!-- Variabile sistema -->
```

### Loop
```html
{{#each contacts}}
  <li>{{contact_type}}: {{contact_value}}</li>
{{/each}}
```

### Condizionali
```html
{{#if email}}
  <p>Email: {{email}}</p>
{{/if}}
```

## 🛠️ Risoluzione Problemi

### Template non visualizzato
```bash
# Verifica permessi
chmod 755 templates/
chmod 644 templates/*/*.json

# Verifica formato JSON
php -r "json_decode(file_get_contents('templates/members/tessera_socio.json'));"
```

### PDF non generato
```bash
# Verifica mPDF
composer show mpdf/mpdf

# Se manca, installa
composer require mpdf/mpdf
```

### Errore memoria PHP
```php
// Aumenta limite in php.ini
memory_limit = 256M
```

## 📞 Supporto

Per domande o problemi:
1. Consulta `templates/README.md` per documentazione completa
2. Controlla i log di errore PHP
3. Verifica che tutte le dipendenze Composer siano installate

## 🎉 Vantaggi del Nuovo Sistema

### vs Sistema Database Precedente
- ✅ **Più veloce**: Nessuna query database per caricare template
- ✅ **Portabile**: File facilmente esportabili e importabili
- ✅ **Versionabile**: Usa Git per tracciare modifiche
- ✅ **Backup automatico**: Incluso nel backup codice
- ✅ **Multi-tabella**: Supporto migliorato per dati correlati
- ✅ **Editor WYSIWYG**: Modifica documenti prima della stampa

### vs Sistema XML Precedente
- ✅ **Formato moderno**: JSON invece di XML
- ✅ **Più semplice**: Sintassi più leggibile
- ✅ **Web-based**: Interfaccia utente moderna
- ✅ **Editor integrato**: Modifica in tempo reale
- ✅ **Multi-tabella**: Supporto nativo per relazioni

## 🔐 Sicurezza

- ✅ Solo utenti autenticati possono generare documenti
- ✅ Permessi verificati in base al tipo di entità
- ✅ SQL injection prevention con whitelist tabelle
- ✅ HTML sanitization per prevenire XSS
- ✅ File template validati prima dell'uso

---

**EasyVol** - Sistema Gestionale per Associazioni di Volontariato
