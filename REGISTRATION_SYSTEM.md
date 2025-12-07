# Sistema di Registrazione Pubblica - EasyVol

## Panoramica

Il sistema di registrazione pubblica consente ai nuovi volontari (maggiorenni e minorenni) di registrarsi online all'associazione attraverso moduli web completi. Il sistema gestisce l'intero flusso di domanda di iscrizione, dalla compilazione alla generazione PDF, all'approvazione finale.

## Componenti Principali

### 1. Pagine Pubbliche di Registrazione

#### Registrazione Soci Maggiorenni
**URL:** `public/register_adult.php`

Raccoglie informazioni complete per i soci adulti:
- **Dati Anagrafici**: nome, cognome, codice fiscale, data e luogo di nascita, nazionalità
- **Indirizzi**: residenza e domicilio (se diverso)
- **Recapiti**: telefono, cellulare, email, PEC
- **Patenti e Abilitazioni**: A, B, C, D, E, Nautica, Muletto, Altro
- **Corsi e Specializzazioni**: corsi di Protezione Civile e attestati
- **Informazioni Sanitarie**: allergie, intolleranze, patologie, dieta vegetariana/vegana
- **Datore di Lavoro**: ragione sociale, indirizzo, contatti
- **Dichiarazioni Obbligatorie**: tutte le clausole previste dalla normativa

#### Registrazione Soci Minorenni (Cadetti)
**URL:** `public/register_junior.php`

Raccoglie informazioni per i soci minorenni:
- **Dati Anagrafici del Minore**: nome, cognome, codice fiscale, data e luogo di nascita
- **Indirizzi**: residenza e domicilio (se diverso)
- **Recapiti**: telefono, cellulare, email
- **Informazioni Sanitarie**: allergie, intolleranze, patologie
- **Dati Genitori/Tutori**: padre, madre, e/o tutore con tutti i dati anagrafici
- **Dichiarazioni Obbligatorie**: clausole specifiche per i minorenni

### 2. Sistema di Gestione Domande

**URL:** `public/applications.php`

Interfaccia amministrativa per:
- Visualizzare tutte le domande ricevute
- Filtrare per stato (in attesa, approvate, rifiutate) e tipo (maggiorenne/minorenne)
- Visualizzare dettagli completi delle domande
- Approvare o rifiutare domande
- Scaricare i PDF generati

### 3. Funzionalità

#### Generazione PDF Automatica
- **Codice univoco** assegnato ad ogni domanda
- **PDF completo** con tutti i dati inseriti
- **Dichiarazioni legali** formattate correttamente
- **Spazio per firme** e allegati richiesti
- PDF diversificati per maggiorenni e minorenni

#### Invio Email
- Email all'applicante con PDF allegato
- Email all'associazione con PDF allegato
- Istruzioni per completare la procedura
- Codice domanda per riferimenti futuri

#### Approvazione e Creazione Socio
Quando una domanda viene approvata:
1. Il sistema genera automaticamente un **numero di matricola** crescente
2. Crea il socio/cadetto nelle tabelle appropriate
3. Popola tutte le tabelle correlate:
   - Indirizzi (residenza/domicilio)
   - Contatti (telefono, cellulare, email, PEC)
   - Patenti e abilitazioni
   - Corsi e specializzazioni
   - Informazioni sanitarie
   - Datore di lavoro
   - Genitori/tutori (per minorenni)
4. Invia email di conferma approvazione

## Normativa e Dichiarazioni

### Dichiarazioni per Maggiorenni

1. **Art. 6 - Regolamento Regionale 18 Ottobre 2010**
   - Disponibilità compiti operativi
   - Operatività unica organizzazione

2. **Art. 7 - Regolamento Regionale 18 Ottobre 2010**
   - Dichiarazione assenza condanne penali

3. **D.Lgs. 117/2017 - Codice Terzo Settore**
   - Attività volontaria gratuita
   - Rimborso spese documentate

4. **D.Lgs. 81/2008 - Sicurezza**
   - Obbligo DPI
   - Certificazione medica

5. **Statuto e Regolamento**
   - Rispetto norme interne

6. **Conoscenza Rischi**
   - Pericoli attività Protezione Civile
   - Rischi attrezzature
   - Responsabilità comunicazione problemi salute

7. **Privacy e Foto**
   - Trattamento dati personali (GDPR)
   - Autorizzazione foto/video

### Dichiarazioni per Minorenni

1. **D.Lgs. 117/2017**
   - Attività volontaria gratuita

2. **D.Lgs. 81/2008**
   - Certificazione medica

3. **Statuto e Regolamento**
   - Rispetto norme interne

4. **Conoscenza Rischi**
   - Rischi attività associative

5. **Esenzione Responsabilità**
   - Esonero Associazione/Consiglio/Istruttori

6. **Privacy e Foto**
   - Trattamento dati personali
   - Autorizzazione foto/video

## Configurazione

### CAPTCHA (Google reCAPTCHA)

Il sistema supporta Google reCAPTCHA v2. Per attivarlo:

1. Ottenere chiavi dal [Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin)
2. Configurare in `config/config.php`:

```php
'recaptcha' => [
    'enabled' => true,
    'site_key' => 'YOUR_SITE_KEY',
    'secret_key' => 'YOUR_SECRET_KEY'
]
```

### Email

Configurare il sistema email in `config/config.php`:

```php
'email' => [
    'enabled' => true,
    'method' => 'smtp',
    'smtp_host' => 'smtp.example.com',
    'smtp_port' => 587,
    'smtp_username' => 'noreply@example.com',
    'smtp_password' => 'password',
    'smtp_encryption' => 'tls',
    'from_email' => 'noreply@example.com',
    'from_name' => 'Associazione Nome'
]
```

### Associazione

Configurare i dati dell'associazione:

```php
'association' => [
    'name' => 'Associazione Volontari di Protezione Civile',
    'email' => 'info@associazione.it',
    'address' => 'Via Roma 1',
    'city' => 'Città',
    'phone' => '+39 0461 123456',
    'website' => 'www.associazione.it'
]
```

## Migrazione Database

Per aggiornare il database con i nuovi campi, eseguire:

```sql
-- File: migrations/add_registration_applications_fields.sql
ALTER TABLE `member_applications` 
ADD COLUMN `approved_at` timestamp NULL DEFAULT NULL AFTER `processed_at`,
ADD COLUMN `member_id` int(11) DEFAULT NULL COMMENT 'ID of created member after approval' AFTER `approved_at`;

CREATE INDEX `idx_application_type` ON `member_applications` (`application_type`);
```

## Flusso Operativo

### 1. Compilazione Domanda (Pubblico)
1. L'utente accede a `register_adult.php` o `register_junior.php`
2. Compila tutti i campi obbligatori
3. Accetta tutte le dichiarazioni obbligatorie
4. Completa il CAPTCHA
5. Invia la domanda

### 2. Elaborazione Sistema
1. Sistema genera codice univoco (es. `APP-2025-A1B2C3D4`)
2. Salva dati in formato JSON nella tabella `member_applications`
3. Genera PDF con tutti i dati e dichiarazioni
4. Invia email con PDF a:
   - Richiedente
   - Associazione

### 3. Gestione Interna (Admin)
1. Admin accede a `applications.php`
2. Visualizza domande in sospeso
3. Esamina dettagli completi
4. Scarica PDF
5. Approva o rifiuta domanda

### 4. Approvazione
Quando approvata:
1. Sistema genera matricola (numero crescente es. `000001`)
2. Crea record socio/cadetto
3. Popola tutte tabelle correlate
4. Invia email conferma al richiedente
5. Stato domanda diventa "Approvata"

### 5. Completamento
Il richiedente deve:
1. Stampare il PDF ricevuto via email
2. Firmarlo (minori: firma anche dei genitori)
3. Allegare documenti richiesti:
   - Attestati/Specializzazioni Protezione Civile
   - Copie patenti speciali
4. Consegnare in sede

## Struttura Dati

### Tabella `member_applications`

```sql
CREATE TABLE `member_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_code` varchar(50) UNIQUE NOT NULL,
  `application_type` enum('adult', 'junior') NOT NULL,
  `status` enum('pending', 'approved', 'rejected') DEFAULT 'pending',
  `application_data` longtext NOT NULL COMMENT 'JSON data',
  `pdf_file` varchar(255),
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` timestamp NULL,
  `processed_by` int(11),
  `approved_at` timestamp NULL,
  `member_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `application_type` (`application_type`)
);
```

### JSON Structure

#### Adult Application
```json
{
  "last_name": "Rossi",
  "first_name": "Mario",
  "tax_code": "RSSMRA80A01H501Z",
  "birth_date": "1980-01-01",
  "birth_place": "Trento",
  "birth_province": "TN",
  "gender": "M",
  "nationality": "Italiana",
  "residence_street": "Via Roma",
  "residence_number": "1",
  "residence_city": "Trento",
  "residence_province": "TN",
  "residence_cap": "38100",
  "mobile": "3401234567",
  "email": "mario.rossi@example.com",
  "licenses": [
    {
      "type": "B",
      "number": "TN123456",
      "issue_date": "2000-01-01",
      "expiry_date": "2030-01-01"
    }
  ],
  "courses": [
    {
      "name": "Corso Base Protezione Civile",
      "completion_date": "2020-06-01",
      "expiry_date": "2025-06-01"
    }
  ],
  "health_allergies": "Nessuna",
  "employer_name": "Azienda SRL",
  "art6_operativo": true,
  "art6_unica_org": true,
  "art7_condanne": true,
  "dlgs_volontariato": true,
  "privacy_accepted": true,
  "compilation_place": "Trento",
  "compilation_date": "2025-12-07"
}
```

#### Junior Application
```json
{
  "last_name": "Bianchi",
  "first_name": "Luca",
  "tax_code": "BNCLCA10A01H501Z",
  "birth_date": "2010-01-01",
  "birth_place": "Trento",
  "birth_province": "TN",
  "gender": "M",
  "residence_street": "Via Milano",
  "residence_number": "10",
  "residence_city": "Trento",
  "residence_province": "TN",
  "residence_cap": "38100",
  "mobile": "3409876543",
  "email": "luca.bianchi@example.com",
  "guardians": [
    {
      "type": "padre",
      "last_name": "Bianchi",
      "first_name": "Giuseppe",
      "tax_code": "BNCGPP75A01H501Z",
      "phone": "0461123456",
      "email": "giuseppe.bianchi@example.com"
    },
    {
      "type": "madre",
      "last_name": "Verdi",
      "first_name": "Anna",
      "tax_code": "VRDNNA78A01H501Z",
      "phone": "0461654321",
      "email": "anna.verdi@example.com"
    }
  ],
  "dlgs_volontariato": true,
  "privacy_accepted": true,
  "compilation_place": "Trento",
  "compilation_date": "2025-12-07"
}
```

## Permessi

Per gestire le domande di iscrizione, gli utenti devono avere i seguenti permessi:
- `applications` - `view`: visualizzare le domande
- `applications` - `edit`: approvare/rifiutare domande

## Troubleshooting

### PDF non generati
- Verificare che la directory `uploads/applications/` esista e sia scrivibile (chmod 755)
- Verificare che la libreria mPDF sia installata: `composer require mpdf/mpdf`

### Email non inviate
- Verificare configurazione SMTP in `config/config.php`
- Controllare i log degli errori PHP
- Verificare che la libreria PHPMailer sia installata: `composer require phpmailer/phpmailer`

### CAPTCHA non funzionante
- Verificare che le chiavi reCAPTCHA siano corrette
- Verificare che il sito sia accessibile da internet (reCAPTCHA richiede connessione)
- Controllare la console browser per errori JavaScript

### Errori approvazione domanda
- Verificare che tutte le tabelle correlate esistano (member_addresses, member_contacts, etc.)
- Controllare i log degli errori PHP per dettagli specifici
- Verificare permessi utente nel database

## Supporto

Per supporto o segnalazione bug, contattare lo sviluppatore o aprire una issue su GitHub.
