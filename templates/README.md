# Sistema di Template Avanzato per Stampe e PDF

## 📋 Panoramica

Il sistema di template file-based sostituisce il vecchio sistema basato su database, offrendo maggiore flessibilità, portabilità e facilità di gestione. Supporta dati da tabelle multiple e include un editor WYSIWYG per modificare i documenti prima della stampa.

## ✨ Caratteristiche Principali

### 🎯 Template File-Based
- **Formato JSON**: Template memorizzati come file JSON leggibili
- **Organizzazione**: Organizzati per tipo di entità (`members`, `junior_members`, `vehicles`, ecc.)
- **Portabilità**: Facili da esportare, importare e versionare
- **Backup**: Inclusi nel repository, sempre disponibili

### 🔗 Supporto Multi-Tabella
- **Relazioni Configurabili**: Carica dati da tabelle correlate
- **Dati Annidati**: Gestisce strutture dati complesse
- **Filtri**: Applica filtri ai dati correlati

### 📝 Editor WYSIWYG
- **TinyMCE**: Editor ricco di funzionalità
- **Modifica Pre-Stampa**: Modifica il contenuto prima di generare il PDF
- **Anteprima in Tempo Reale**: Vedi le modifiche immediatamente

### 📄 Generazione PDF
- **mPDF**: Libreria potente per PDF di alta qualità
- **Formati Personalizzati**: A4, A3, Letter, formati custom
- **Orientamento**: Portrait o Landscape
- **Margini Configurabili**: Controllo completo sui margini

## 📂 Struttura Directory

```
templates/
├── members/                    # Template per soci
│   ├── tessera_socio.json
│   ├── scheda_socio_completa.json
│   └── elenco_soci_contatti.json
├── junior_members/             # Template per cadetti
│   └── scheda_cadetto_completa.json
├── vehicles/                   # Template per mezzi
│   └── scheda_mezzo_completa.json
├── meetings/                   # Template per riunioni
├── events/                     # Template per eventi
└── applications/               # Template per domande
```

## 📝 Formato Template

### Struttura Base

```json
{
  "name": "Nome Template",
  "description": "Descrizione del template",
  "type": "single|list|multi_page",
  "format": "A4|A3|Letter|custom",
  "orientation": "portrait|landscape",
  "margins": {
    "top": 15,
    "bottom": 15,
    "left": 15,
    "right": 15
  },
  "html": "Contenuto HTML del template",
  "css": "CSS personalizzato (opzionale)"
}
```

### Tipi di Template

#### 1. Single Record (`type: "single"`)
Genera un documento per un singolo record.

**Esempio**: Tessera socio, Scheda individuale

**Opzioni**: `record_id` (obbligatorio)

#### 2. List (`type: "list"`)
Genera un documento con lista di record (tabella o lista).

**Esempio**: Elenco soci, Lista mezzi

**Opzioni**: `filters` (opzionale)

#### 3. Multi-Page (`type: "multi_page"`)
Genera un documento con una pagina per ogni record.

**Esempio**: Tesserini multipli, Libro soci

**Opzioni**: `record_ids` o `filters`

### Relazioni Multi-Tabella

```json
{
  "relations": {
    "contacts": {
      "table": "member_contacts",
      "foreign_key": "member_id",
      "filters": {
        "is_primary": 1
      },
      "order_by": "contact_type ASC"
    },
    "addresses": {
      "table": "member_addresses",
      "foreign_key": "member_id"
    }
  }
}
```

## 🎨 Sintassi Template

### Variabili Semplici

```html
<p>Nome: {{first_name}}</p>
<p>Cognome: {{last_name}}</p>
<p>Matricola: {{registration_number}}</p>
```

### Variabili Annidate

```html
<p>{{association.name}}</p>
<p>{{association.address}}</p>
```

### Loop con `#each`

```html
{{#each contacts}}
<tr>
  <td>{{contact_type}}</td>
  <td>{{contact_value}}</td>
  <td>{{is_primary}}</td>
</tr>
{{/each}}
```

#### Indice nei Loop

- `{{@index}}` - Indice base-1 (1, 2, 3, ...)
- `{{@index0}}` - Indice base-0 (0, 1, 2, ...)

### Condizionali con `#if`

```html
{{#if email}}
<p>Email: {{email}}</p>
{{/if}}

{{#if contacts}}
<h3>Contatti</h3>
<ul>
  {{#each contacts}}
  <li>{{contact_value}}</li>
  {{/each}}
</ul>
{{/if}}
```

### Variabili di Sistema

- `{{current_date}}` - Data corrente (formato: dd/mm/yyyy)
- `{{current_year}}` - Anno corrente
- `{{association.name}}` - Nome associazione
- `{{association.logo_path}}` - Percorso logo
- `{{association.address}}` - Indirizzo associazione
- `{{association.city}}` - Città associazione
- `{{association.phone}}` - Telefono associazione
- `{{association.email}}` - Email associazione
- `{{association.website}}` - Sito web associazione

## 🚀 Utilizzo

### 1. Via Interfaccia Web

Accedi a `/public/enhanced_print.php` e segui questi passaggi:

1. **Seleziona Tipo Documento**: Scegli l'entità (soci, cadetti, mezzi, ecc.)
2. **Seleziona Template**: Clicca sul template desiderato
3. **Configura Opzioni**: Inserisci ID record o filtri
4. **Genera**:
   - **Anteprima**: Vedi il risultato in browser
   - **PDF**: Scarica direttamente il PDF
   - **Modifica**: Apri l'editor per modificare prima di stampare

### 2. Via Codice PHP

```php
use EasyVol\Controllers\EnhancedPrintController;

$controller = new EnhancedPrintController($db, $config);

// Genera documento HTML
$document = $controller->generate(
    'file_tessera_socio.json',  // Template ID
    'members',                    // Entity type
    ['record_id' => 123]         // Options
);

// Genera PDF diretto
$controller->generatePdf(
    'file_scheda_socio_completa.json',
    'members',
    ['record_id' => 123],
    'D'  // D=Download, I=Inline, F=File, S=String
);
```

## 📋 Esempi Template

### Esempio 1: Tessera Socio (Single)

```json
{
  "name": "Tessera Socio",
  "description": "Tessera associativa formato carta",
  "type": "single",
  "format": "custom",
  "orientation": "landscape",
  "page_size": {
    "width": 85,
    "height": 54,
    "unit": "mm"
  },
  "html": "<div style=\"width: 8.5cm; height: 5.4cm; border: 2px solid #333; padding: 0.3cm;\">\n  <h3>{{association.name}}</h3>\n  <p><strong>Nome:</strong> {{first_name}} {{last_name}}</p>\n  <p><strong>Matricola:</strong> {{registration_number}}</p>\n  <p><strong>Valida fino al:</strong> 31/12/{{current_year}}</p>\n</div>"
}
```

### Esempio 2: Elenco Soci con Contatti (List)

```json
{
  "name": "Elenco Soci con Contatti",
  "type": "list",
  "format": "A4",
  "orientation": "landscape",
  "relations": {
    "contacts": {
      "table": "member_contacts",
      "foreign_key": "member_id",
      "filters": {"is_primary": 1}
    }
  },
  "html": "<h1>Elenco Soci</h1>\n<table>\n  <tr>\n    <th>Matricola</th>\n    <th>Nome</th>\n    <th>Email</th>\n    <th>Telefono</th>\n  </tr>\n  {{#each records}}\n  <tr>\n    <td>{{registration_number}}</td>\n    <td>{{first_name}} {{last_name}}</td>\n    <td>{{email}}</td>\n    <td>{{mobile_phone}}</td>\n  </tr>\n  {{/each}}\n</table>"
}
```

### Esempio 3: Scheda con Dati Multi-Tabella (Single + Relations)

```json
{
  "name": "Scheda Socio Completa",
  "type": "single",
  "format": "A4",
  "relations": {
    "contacts": {
      "table": "member_contacts",
      "foreign_key": "member_id"
    },
    "addresses": {
      "table": "member_addresses",
      "foreign_key": "member_id"
    },
    "courses": {
      "table": "member_courses",
      "foreign_key": "member_id",
      "order_by": "completion_date DESC"
    }
  },
  "html": "<h1>Scheda Socio</h1>\n<h2>Dati Anagrafici</h2>\n<p>{{first_name}} {{last_name}}</p>\n<p>CF: {{tax_code}}</p>\n\n{{#if contacts}}\n<h2>Contatti</h2>\n<ul>\n  {{#each contacts}}\n  <li>{{contact_type}}: {{contact_value}}</li>\n  {{/each}}\n</ul>\n{{/if}}\n\n{{#if addresses}}\n<h2>Indirizzi</h2>\n{{#each addresses}}\n<p><strong>{{address_type}}:</strong> {{street_address}}, {{city}}</p>\n{{/each}}\n{{/if}}\n\n{{#if courses}}\n<h2>Corsi</h2>\n<table>\n  {{#each courses}}\n  <tr>\n    <td>{{course_name}}</td>\n    <td>{{completion_date}}</td>\n  </tr>\n  {{/each}}\n</table>\n{{/if}}"
}
```

## 🔧 Creazione Nuovi Template

### Metodo 1: Manuale

1. Crea un file JSON nella directory appropriata in `templates/`
2. Usa la struttura template descritta sopra
3. Testa con l'interfaccia web

### Metodo 2: Via Interfaccia (TODO)

Un editor di template sarà disponibile in una versione futura.

### Metodo 3: Migrazione da DB

```php
$controller = new EnhancedPrintController($db, $config);
$filename = $controller->migrateDbTemplateToFile($dbTemplateId);
```

## 🎯 Best Practices

### 1. Organizzazione
- Un template per file
- Nome file descrittivo (es: `scheda_socio_completa.json`)
- Organizza per entità

### 2. HTML
- Usa HTML semantico
- Includi stili inline per controllo completo
- Testa su diversi formati pagina

### 3. CSS
- Usa `@page` per configurazione pagina stampa
- Includi regole `@media print` per stampa ottimizzata
- Font web-safe per compatibilità

### 4. Performance
- Limita dati correlati se non necessari
- Usa filtri per ridurre il carico
- Per liste grandi, considera la paginazione

## 🐛 Troubleshooting

### Template non visualizzato
- Verifica che il file JSON sia valido
- Controlla i permessi della directory `templates/`
- Verifica il nome del file

### Dati correlati non mostrati
- Verifica la configurazione `relations`
- Controlla che il `foreign_key` sia corretto
- Verifica che la tabella esista

### PDF non generato
- Verifica che mPDF sia installato via Composer
- Controlla i log di errore PHP
- Verifica la memoria disponibile per PDF grandi

### Variabili non sostituite
- Verifica la sintassi: `{{variable}}` (doppia parentesi graffa)
- Verifica che il campo esista nel database
- Usa `{{#if variable}}` per variabili opzionali

## 📚 Riferimenti

- [mPDF Documentation](https://mpdf.github.io/)
- [TinyMCE Documentation](https://www.tiny.cloud/docs/)
- [HTML to PDF Best Practices](https://mpdf.github.io/html-support/html-support.html)

## 🔄 Compatibilità

Il sistema è **retrocompatibile** con i template database esistenti:
- I template DB esistenti continuano a funzionare
- Nuovi template usano il sistema file-based
- Migrazione graduale possibile

## 🚀 Roadmap

- [ ] Editor template via interfaccia web
- [ ] Libreria template predefiniti più ampia
- [ ] Import/Export template in batch
- [ ] Anteprima con dati campione
- [ ] Versioning template
- [ ] Template condivisi tra utenti

---

**EasyVol** - Sistema Gestionale per Associazioni di Volontariato
