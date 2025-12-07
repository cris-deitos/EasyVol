# Sistema di Stampa e Generazione PDF - EasyVol

## Panoramica

Il sistema di stampa e generazione PDF di EasyVol è una soluzione completa che permette di creare, gestire e generare documenti personalizzati per soci, mezzi, riunioni e altri moduli del sistema.

## Caratteristiche Principali

### ✅ Tipi di Template Supportati

1. **SINGOLO (single)**: Genera 1 documento da 1 record
   - Esempio: Certificato di iscrizione, Tessera socio
   
2. **LISTA (list)**: Genera 1 documento tabellare da N record
   - Esempio: Libro soci, Elenco telefonico
   
3. **MULTI-PAGINA (multi_page)**: Genera N pagine in 1 PDF (una per record)
   - Esempio: Tessere multiple, Certificati multipli
   
4. **RELAZIONALE (relational)**: Genera 1 documento con dati da tabelle correlate
   - Esempio: Scheda completa socio con contatti, indirizzi, patenti

### ✅ Editor WYSIWYG con TinyMCE

- Editor visuale per creare template HTML
- Pulsante "Inserisci Variabile" per aggiungere placeholder
- Supporto completo per formattazione testo, tabelle, immagini
- Preview in tempo reale del template

### ✅ Variabili e Loop

**Variabili Semplici:**
```html
{{first_name}} {{last_name}}
{{registration_number}}
{{birth_date}}
```

**Loop per Array (Handlebars-style):**
```html
{{#each member_contacts}}
  <p>{{contact_type}}: {{value}}</p>
{{/each}}
```

### ✅ Relazioni Supportate

**MEMBERS:**
- member_contacts (contatti)
- member_addresses (indirizzi)
- member_licenses (patenti)
- member_courses (corsi)
- member_roles (ruoli)
- member_employment (datore lavoro)
- member_education (istruzione)
- member_health (salute)
- member_fees (quote)
- member_notes (note)

**JUNIOR_MEMBERS:**
- junior_member_guardians (genitori/tutori)
- junior_member_contacts (contatti)
- junior_member_addresses (indirizzi)
- junior_member_health (salute)
- junior_member_fees (quote)

**VEHICLES:**
- vehicle_maintenance (manutenzioni)
- vehicle_documents (documenti)

**MEETINGS:**
- meeting_participants (partecipanti)
- meeting_agenda (ordine del giorno)
- meeting_attachments (allegati)

### ✅ Filtri Dinamici

Per template di tipo LISTA, è possibile applicare filtri su:

**Members:**
- member_status (attivo/sospeso/dimesso/decaduto)
- member_type (ordinario/fondatore)
- registration_date (range di date)

**Vehicles:**
- vehicle_type
- status

**Meetings:**
- meeting_date (range di date)

## Struttura Database

### Tabella print_templates

```sql
CREATE TABLE print_templates (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  template_type ENUM('single', 'list', 'multi_page', 'relational'),
  data_scope ENUM('single', 'filtered', 'all', 'custom'),
  entity_type VARCHAR(100) NOT NULL,
  html_content LONGTEXT NOT NULL,
  css_content TEXT,
  relations JSON,
  filter_config JSON,
  variables JSON,
  page_format ENUM('A4', 'A3', 'Letter') DEFAULT 'A4',
  page_orientation ENUM('portrait', 'landscape') DEFAULT 'portrait',
  show_header TINYINT(1) DEFAULT 1,
  show_footer TINYINT(1) DEFAULT 1,
  header_content TEXT,
  footer_content TEXT,
  watermark VARCHAR(255),
  is_active TINYINT(1) DEFAULT 1,
  is_default TINYINT(1) DEFAULT 0,
  created_by INT,
  created_at TIMESTAMP,
  updated_by INT,
  updated_at TIMESTAMP
);
```

## Pagine del Sistema

### 1. print_templates.php
**Gestione Template**

Funzionalità:
- Lista di tutti i template
- Filtri per tipo entità e tipo template
- Azioni: Modifica, Anteprima, Esporta, Elimina
- Importazione template da JSON

### 2. print_template_editor.php
**Editor Template**

Funzionalità:
- Editor WYSIWYG TinyMCE
- Pannello variabili disponibili
- Configurazione relazioni (per template relazionali)
- Impostazioni pagina (formato, orientamento)
- Header/Footer personalizzabili
- CSS personalizzato
- Watermark opzionale

### 3. print_generate.php
**Endpoint Generazione**

Parametri:
- `template_id`: ID del template
- `record_id`: ID del record (per single/relational)
- `record_ids`: Array di ID (per multi_page)
- `filters`: Filtri per lista
- `entity`: Tipo entità

Ritorna:
- HTML completo del documento
- CSS applicato
- Header e footer

### 4. print_preview.php
**Anteprima Documento**

Funzionalità:
- Visualizzazione anteprima documento
- Pulsante "Stampa" (window.print)
- Pulsante "Download PDF" (html2pdf.js)
- Pulsante "Modifica" (va a print_edit.php)

### 5. print_edit.php
**Modifica Pre-Stampa**

Funzionalità:
- Editor contenteditable per modifiche last-minute
- Salva e stampa
- Salva e anteprima

## Controller: PrintTemplateController

### Metodi Principali

#### getAll($filters)
Lista tutti i template con filtri opzionali

#### getById($id)
Ottiene un singolo template

#### create($data, $userId)
Crea nuovo template

#### update($id, $data, $userId)
Aggiorna template esistente

#### delete($id)
Elimina template

#### generate($templateId, $options)
Genera documento da template
- Chiama generateSingle/generateList/generateMultiPage/generateRelational

#### loadRecord($entityType, $recordId)
Carica singolo record da database

#### loadRecords($entityType, $filters)
Carica lista di record con filtri

#### loadRelatedData($entityType, $recordId, $relationTable)
Carica dati da tabelle correlate

#### replaceVariables($content, $data)
Sostituisce {{variabili}} con valori reali

#### renderHandlebars($content, $data)
Processa loop {{#each array}}

#### getAvailableVariables($entityType)
Ottiene lista variabili disponibili per tipo entità

#### getAvailableRelations($entityType)
Ottiene lista relazioni disponibili per tipo entità

#### exportTemplate($templateId)
Esporta template in formato JSON

#### importTemplate($templateData, $userId)
Importa template da JSON

## Template Predefiniti

### Soci (Members)

1. **Certificato di Iscrizione** (single)
   - Certificato ufficiale di appartenenza all'associazione
   - Formato: A4 verticale

2. **Tessera Socio** (single)
   - Tessera identificativa formato card (8.5x5.4cm)
   - Formato: A4 verticale

3. **Scheda Completa Socio** (relational)
   - Dati anagrafici completi
   - Contatti, indirizzi, patenti, corsi
   - Multi-pagina con sezioni
   - Formato: A4 verticale

4. **Libro Soci** (list)
   - Elenco completo di tutti i soci
   - Tutti i campi principali in formato tabellare
   - Formato: A4 orizzontale

5. **Elenco Telefonico** (list)
   - Lista soci con contatti
   - Matricola, nome, telefono, email
   - Formato: A4 verticale

6. **Tessere Multiple** (multi_page)
   - Genera più tessere in un unico PDF
   - Una tessera per pagina
   - Formato: A4 verticale

### Mezzi (Vehicles)

7. **Scheda Tecnica Mezzo** (relational)
   - Dati identificativi mezzo
   - Storico manutenzioni
   - Formato: A4 verticale

8. **Elenco Mezzi** (list)
   - Lista completa dei mezzi
   - Targa, tipo, marca/modello, anno, stato
   - Formato: A4 verticale

### Riunioni (Meetings)

9. **Verbale di Riunione** (relational)
   - Verbale ufficiale
   - Partecipanti e ordine del giorno
   - Spazi per firme
   - Formato: A4 verticale

10. **Foglio Presenze** (relational)
    - Foglio firme presenza
    - Lista partecipanti con spazio firma
    - Formato: A4 verticale

## Utilizzo

### Creazione Nuovo Template

1. Vai su **Impostazioni** → **Template Stampe**
2. Clicca su **Nuovo Template**
3. Compila i dati base:
   - Nome template
   - Descrizione
   - Tipo entità (members, vehicles, meetings, ecc.)
   - Tipo template (single, list, multi_page, relational)
   - Scope dati
4. Scrivi il contenuto HTML usando l'editor TinyMCE
5. Inserisci variabili usando il pannello a destra o il pulsante dell'editor
6. Per template relazionali, seleziona le tabelle correlate da includere
7. Configura header/footer se necessario
8. Aggiungi CSS personalizzato se richiesto
9. Salva il template

### Generazione Documento

#### Da Scheda Singolo Record (member_view.php, vehicle_view.php, ecc.)

1. Apri la scheda del record
2. Clicca sul menu dropdown **Stampa**
3. Seleziona il tipo di documento:
   - Opzioni predefinite (es: Certificato, Tessera, Scheda Completa)
   - "Scegli Template..." per selezionare manualmente
4. Si aprirà una nuova finestra con l'anteprima
5. Usa i pulsanti:
   - **Stampa**: apre dialog di stampa browser
   - **Download PDF**: genera e scarica PDF
   - **Modifica**: permette modifiche last-minute
   - **Chiudi**: chiude la finestra

#### Da Pagina Lista (members.php, vehicles.php)

1. Applica eventuali filtri alla lista
2. Clicca sul menu dropdown **Stampa**
3. Seleziona il tipo di report:
   - Libro Soci, Elenco Telefonico, ecc.
4. Il documento includerà tutti i record filtrati
5. Procedi con stampa o download

### Modifica Template Esistente

1. Vai su **Impostazioni** → **Template Stampe**
2. Trova il template nella lista
3. Clicca sull'icona **Modifica** (matita)
4. Modifica i campi necessari
5. Clicca **Salva**

### Esportazione/Importazione Template

**Esportazione:**
1. Vai alla lista template
2. Clicca sull'icona **Esporta** (download) per il template desiderato
3. Verrà scaricato un file JSON con il template

**Importazione:**
1. Clicca su **Importa**
2. Seleziona il file JSON del template
3. Clicca **Importa**
4. Il template verrà aggiunto (eventualmente rinominato se esiste già)

## CSS per Stampa

Il file `assets/css/print.css` contiene stili ottimizzati per la stampa:

### Media Query Print
```css
@media print {
  /* Nasconde elementi non necessari */
  .no-print, .navbar, .sidebar { display: none !important; }
  
  /* Ottimizza tabelle */
  thead { display: table-header-group; }
  tr { page-break-inside: avoid; }
}
```

### Formati Pagina
```css
@page {
  size: A4 portrait;
  margin: 2cm;
}
```

### Classi Utility
- `.page-break`: forza interruzione pagina
- `.avoid-page-break`: evita interruzione dentro elemento
- `.watermark`: aggiunge watermark
- `.certificate`: stile certificato
- `.member-card`: stile tessera

## Variabili Template

### Sintassi Base

**Variabile semplice:**
```html
<p>Nome: {{first_name}}</p>
<p>Cognome: {{last_name}}</p>
```

**Loop array:**
```html
<ul>
  {{#each member_contacts}}
    <li>{{contact_type}}: {{value}}</li>
  {{/each}}
</ul>
```

**Tabella con loop:**
```html
<table>
  <thead>
    <tr>
      <th>Tipo</th>
      <th>Valore</th>
    </tr>
  </thead>
  <tbody>
    {{#each member_contacts}}
    <tr>
      <td>{{contact_type}}</td>
      <td>{{value}}</td>
    </tr>
    {{/each}}
  </tbody>
</table>
```

### Variabili per Entità

#### Members
- `id`, `registration_number`, `member_type`, `member_status`
- `volunteer_status`, `first_name`, `last_name`
- `birth_date`, `birth_place`, `birth_province`
- `tax_code`, `gender`, `nationality`
- `registration_date`, `approval_date`
- `notes`, `created_at`, `updated_at`

#### Vehicles
- `id`, `license_plate`, `vehicle_type`, `brand`, `model`
- `year`, `chassis_number`, `status`
- `insurance_expiry`, `inspection_expiry`
- `notes`, `created_at`, `updated_at`

#### Meetings
- `id`, `meeting_date`, `meeting_time`
- `location`, `meeting_type`
- `notes`, `created_at`, `updated_at`

### Formattazione Date

Le date vengono automaticamente formattate in formato italiano (dd/mm/yyyy):
```html
{{registration_date}} <!-- Output: 15/03/2023 -->
```

## API e Integrazione

### Generazione Programmatica

```php
use EasyVol\Controllers\PrintTemplateController;

$controller = new PrintTemplateController($db, $config);

// Genera documento singolo
$result = $controller->generate($templateId, [
    'record_id' => 123
]);

// Genera lista con filtri
$result = $controller->generate($templateId, [
    'filters' => [
        'member_status' => 'attivo',
        'member_type' => 'ordinario'
    ]
]);

// Genera multi-pagina
$result = $controller->generate($templateId, [
    'record_ids' => [1, 2, 3, 4, 5]
]);

// Risultato contiene:
// - html: contenuto HTML
// - css: CSS personalizzato
// - header: header del documento
// - footer: footer del documento
// - watermark: testo watermark
// - page_format: formato pagina
// - page_orientation: orientamento
```

### Endpoint REST

**Generazione documento:**
```
GET /print_generate.php?template_id=1&record_id=123&entity=members
```

**Formato JSON:**
```
GET /print_generate.php?template_id=1&record_id=123&format=json
```

## Sicurezza

### Controlli Permessi

Il sistema verifica i permessi dell'utente:
- **Gestione template**: richiede permesso `settings.edit`
- **Generazione documenti**: richiede permesso `[entity].view`
  - members: `members.view`
  - vehicles: `vehicles.view`
  - meetings: `meetings.view`

### Sanificazione

- Tutti i valori inseriti nelle variabili vengono sanificati con `htmlspecialchars()`
- L'HTML dei template è responsabilità dell'utente che crea il template
- Solo utenti con permessi amministrativi possono creare/modificare template

## Download PDF

Il sistema usa **html2pdf.js** per generare PDF lato client:

```javascript
function downloadPDF() {
    const element = document.getElementById('previewContent');
    const opt = {
        margin: 1,
        filename: 'documento_' + new Date().getTime() + '.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'cm', format: 'a4', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(element).save();
}
```

## Troubleshooting

### Il template non mostra i dati

1. Verifica che le variabili siano scritte correttamente: `{{nome_variabile}}`
2. Controlla che il tipo entità sia corretto
3. Per template relazionali, verifica che le relazioni siano selezionate

### La stampa non include header/footer

1. Verifica che "Mostra Header" e "Mostra Footer" siano attivi
2. Controlla che header_content e footer_content non siano vuoti
3. Alcuni browser potrebbero nascondere header/footer nella stampa

### Le tabelle vengono interrotte male

Aggiungi nel CSS personalizzato:
```css
table { page-break-inside: auto; }
tr { page-break-inside: avoid; }
```

### Il PDF è troppo grande

1. Riduci la qualità delle immagini nell'opzione html2pdf
2. Usa immagini più piccole nel template
3. Ottimizza il CSS rimuovendo stili non necessari

## Best Practices

### Creazione Template

1. **Usa nomi descrittivi** per i template
2. **Testa su dati reali** prima di attivare il template
3. **Includi header con logo** dell'associazione
4. **Usa tabelle per dati strutturati** (contatti, patenti, ecc.)
5. **Aggiungi page-break** per documenti multi-pagina
6. **Usa CSS print** per ottimizzare la stampa

### CSS

1. **Usa unità cm** per dimensioni fisiche di stampa
2. **Testa formati pagina** (A4 verticale/orizzontale)
3. **Evita colori scuri** come sfondo (spreco inchiostro)
4. **Usa font standard** (Arial, Times New Roman)
5. **Testa su diversi browser** (Chrome, Firefox, Safari)

### Performance

1. **Non caricare troppe relazioni** se non necessarie
2. **Limita il numero di record** in template multi-pagina
3. **Ottimizza query** nel controller se lento
4. **Usa cache** per template frequentemente usati

## Esempi Pratici

### Esempio 1: Certificato Semplice

```html
<div style="text-align: center; margin-top: 3cm;">
  <h1 style="border-bottom: 2px solid #333;">CERTIFICATO</h1>
  
  <p style="margin-top: 2cm; font-size: 14pt;">
    Si certifica che
  </p>
  
  <p style="font-size: 18pt; font-weight: bold; margin: 1cm 0;">
    {{first_name}} {{last_name}}
  </p>
  
  <p style="font-size: 14pt;">
    è socio di questa associazione dal {{registration_date}}
  </p>
  
  <div style="text-align: right; margin-top: 3cm;">
    <p>Il Presidente</p>
    <p style="margin-top: 2cm;">_________________</p>
  </div>
</div>
```

### Esempio 2: Lista con Loop

```html
<h1 style="text-align: center;">ELENCO SOCI</h1>

<table style="width: 100%; border-collapse: collapse; margin-top: 2cm;">
  <thead>
    <tr style="background: #333; color: white;">
      <th style="padding: 0.5cm; border: 1px solid #000;">Matricola</th>
      <th style="padding: 0.5cm; border: 1px solid #000;">Nome</th>
      <th style="padding: 0.5cm; border: 1px solid #000;">Stato</th>
    </tr>
  </thead>
  <tbody>
    {{#each records}}
    <tr>
      <td style="padding: 0.3cm; border: 1px solid #ccc;">{{registration_number}}</td>
      <td style="padding: 0.3cm; border: 1px solid #ccc;">{{first_name}} {{last_name}}</td>
      <td style="padding: 0.3cm; border: 1px solid #ccc;">{{member_status}}</td>
    </tr>
    {{/each}}
  </tbody>
</table>
```

### Esempio 3: Scheda con Relazioni

```html
<h1>SCHEDA SOCIO: {{first_name}} {{last_name}}</h1>

<h2>Dati Anagrafici</h2>
<table style="width: 100%;">
  <tr>
    <td style="width: 30%; font-weight: bold;">Matricola:</td>
    <td>{{registration_number}}</td>
  </tr>
  <tr>
    <td style="font-weight: bold;">Data di Nascita:</td>
    <td>{{birth_date}}</td>
  </tr>
  <tr>
    <td style="font-weight: bold;">Codice Fiscale:</td>
    <td>{{tax_code}}</td>
  </tr>
</table>

<div style="page-break-after: always;"></div>

<h2>Contatti</h2>
<table style="width: 100%; border-collapse: collapse;">
  <thead>
    <tr style="background: #333; color: white;">
      <th style="padding: 0.3cm;">Tipo</th>
      <th style="padding: 0.3cm;">Valore</th>
    </tr>
  </thead>
  <tbody>
    {{#each member_contacts}}
    <tr>
      <td style="padding: 0.2cm; border: 1px solid #ccc;">{{contact_type}}</td>
      <td style="padding: 0.2cm; border: 1px solid #ccc;">{{value}}</td>
    </tr>
    {{/each}}
  </tbody>
</table>

<h2>Patenti</h2>
<table style="width: 100%; border-collapse: collapse;">
  <thead>
    <tr style="background: #333; color: white;">
      <th style="padding: 0.3cm;">Tipo</th>
      <th style="padding: 0.3cm;">Numero</th>
      <th style="padding: 0.3cm;">Scadenza</th>
    </tr>
  </thead>
  <tbody>
    {{#each member_licenses}}
    <tr>
      <td style="padding: 0.2cm; border: 1px solid #ccc;">{{license_type}}</td>
      <td style="padding: 0.2cm; border: 1px solid #ccc;">{{license_number}}</td>
      <td style="padding: 0.2cm; border: 1px solid #ccc;">{{expiry_date}}</td>
    </tr>
    {{/each}}
  </tbody>
</table>
```

## Supporto e Manutenzione

Per problemi o domande:
1. Verifica questa documentazione
2. Controlla i log di errore PHP
3. Testa in ambiente di sviluppo prima di produzione
4. Contatta il team di sviluppo per problemi non risolti

## Changelog

### Versione 1.0.0 (2025-12-07)
- Release iniziale del sistema di stampa
- 10 template predefiniti
- Supporto per single, list, multi_page, relational
- Editor TinyMCE integrato
- Export/Import template JSON
- Integrazione completa con tutte le pagine view e list
