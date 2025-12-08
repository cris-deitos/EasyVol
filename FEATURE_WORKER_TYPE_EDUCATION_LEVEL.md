# Aggiunta Tipo di Lavoratore e Titolo di Studio

## Descrizione

Questa feature aggiunge due nuovi campi essenziali alla gestione dei soci:

1. **Tipo di Lavoratore** (`worker_type`)
2. **Titolo di Studio** (`education_level`)

## Database - Campi Aggiunti

### Tabella `members`

Due nuovi campi ENUM opzionali:

- **worker_type**: Tipo di lavoratore del socio
  - `studente`
  - `dipendente_privato`
  - `dipendente_pubblico`
  - `lavoratore_autonomo`
  - `disoccupato`
  - `pensionato`

- **education_level**: Titolo di studio del socio
  - `licenza_media`
  - `diploma_maturita`
  - `laurea_triennale`
  - `laurea_magistrale`
  - `dottorato`

Entrambi i campi sono nullable (NULL DEFAULT) per mantenere la compatibilità con i dati esistenti.

## Installazione

### 1. Eseguire la Migration

Eseguire il file di migrazione per aggiornare il database:

```bash
php migrations/run_migration.php migrations/add_worker_type_and_education_level.sql
```

Oppure eseguire manualmente lo script SQL sul database:

```bash
mysql -u [username] -p [database_name] < migrations/add_worker_type_and_education_level.sql
```

### 2. Verificare l'Installazione

Dopo l'esecuzione della migrazione, verificare che i campi siano stati aggiunti correttamente:

```sql
DESCRIBE members;
```

Dovresti vedere i nuovi campi `worker_type` e `education_level` nella struttura della tabella.

## Utilizzo

### 1. Modulo Pubblico di Iscrizione

Nel modulo pubblico di iscrizione (`register_adult.php`), i candidati possono ora selezionare:
- Il loro tipo di lavoratore (studente, dipendente, autonomo, ecc.)
- Il loro titolo di studio (licenza media, diploma, laurea, ecc.)

Questi campi appaiono nella sezione "Informazioni Professionali e Formative" prima della sezione "Datore di Lavoro".

### 2. Gestione Soci (Backend)

#### Creazione/Modifica Socio

In `member_edit.php`, è stata aggiunta una nuova sezione "Informazioni Professionali e Formative" che include:
- Menu a tendina per selezionare il tipo di lavoratore
- Menu a tendina per selezionare il titolo di studio

Entrambi i campi sono opzionali e possono essere lasciati vuoti.

#### Visualizzazione Socio

In `member_view.php`, nella scheda "Dati Anagrafici", è stata aggiunta una sottosezione "Informazioni Professionali e Formative" che mostra:
- Tipo di Lavoratore (con traduzione in italiano)
- Titolo di Studio (con traduzione in italiano)

Se i campi non sono compilati, viene mostrato "N/D".

### 3. Approvazione Domande

Quando una domanda di iscrizione viene approvata in `applications.php`, i valori di `worker_type` e `education_level` vengono automaticamente trasferiti nel record del nuovo socio.

## File Modificati

### Database
- `database_schema.sql` - Schema aggiornato con i nuovi campi
- `migrations/add_worker_type_and_education_level.sql` - Script di migrazione

### Controllers
- `src/Controllers/MemberController.php` - Gestione create/update con i nuovi campi
- `src/Controllers/ApplicationController.php` - Gestione approvazione domande con i nuovi campi

### Views (Frontend)
- `public/member_edit.php` - Form di modifica/creazione socio
- `public/member_view.php` - Visualizzazione dettagli socio
- `public/register_adult.php` - Form pubblico di registrazione

## Compatibilità

- ✅ **Backward Compatible**: I campi sono opzionali (NULL), quindi i record esistenti non sono impattati
- ✅ **MySQL 5.6+**: Compatibile con MySQL 5.6 e versioni successive
- ✅ **Nessuna modifica breaking**: Tutte le funzionalità esistenti continuano a funzionare

## Note Tecniche

### Validazione

I campi sono validati a livello di database tramite ENUM, quindi solo i valori predefiniti sono accettati.

### Indici

Sono stati creati indici sui nuovi campi per ottimizzare le query:
- `idx_worker_type` su `members.worker_type`
- `idx_education_level` su `members.education_level`

### Traduzioni

Le etichette dei valori ENUM sono tradotte in italiano nelle viste:
- `studente` → "Studente"
- `dipendente_privato` → "Dipendente Privato"
- `diploma_maturita` → "Diploma di Maturità"
- ecc.

## Testing

### Test Manuale

1. **Creazione Nuovo Socio**:
   - Accedere a "Gestione Soci" → "Nuovo Socio"
   - Compilare i campi obbligatori
   - Selezionare tipo di lavoratore e titolo di studio
   - Salvare e verificare che i dati siano stati salvati correttamente

2. **Modifica Socio Esistente**:
   - Aprire un socio esistente
   - Cliccare su "Modifica"
   - Aggiungere/modificare tipo di lavoratore e titolo di studio
   - Salvare e verificare la visualizzazione

3. **Registrazione Pubblica**:
   - Aprire il modulo di registrazione pubblico
   - Compilare tutti i campi richiesti
   - Selezionare tipo di lavoratore e titolo di studio
   - Inviare la domanda
   - Approvare la domanda dal backend
   - Verificare che il socio creato abbia i campi compilati

4. **Visualizzazione Socio**:
   - Aprire la scheda di un socio
   - Verificare che i nuovi campi siano visibili nella sezione "Dati Anagrafici"
   - Verificare che le traduzioni siano corrette

## Troubleshooting

### Errore: "Unknown column 'worker_type'"

**Problema**: La migrazione non è stata eseguita.

**Soluzione**: Eseguire la migrazione come descritto nella sezione "Installazione".

### I campi non vengono salvati

**Problema**: Possibile problema con la validazione dei valori ENUM.

**Soluzione**: Verificare che i valori inviati corrispondano esattamente ai valori ENUM definiti (es. `dipendente_privato`, non `Dipendente Privato`).

### I campi non appaiono nel form

**Problema**: Cache del browser o file non aggiornati.

**Soluzione**: 
1. Svuotare la cache del browser (Ctrl+F5)
2. Verificare che tutti i file siano stati aggiornati sul server

## Requisiti

- PHP 8.0+
- MySQL 5.6+ o MySQL 8.x
- Bootstrap 5.3.0 (già incluso)

## Autore

Implementato per EasyVol - Sistema di Gestione Volontari
Data: 2025-12-08
