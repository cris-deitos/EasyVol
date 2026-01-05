# Training Course Participation Integration

## Panoramica

Questa funzionalità integra automaticamente i corsi di formazione completati dai soci nel loro registro personale.

## Come Funziona

### Flusso Automatico

1. **Creazione Corso**: Un corso viene creato nella sezione "Formazione" del sistema
2. **Iscrizione Partecipanti**: I soci vengono aggiunti come partecipanti al corso
3. **Completamento Corso**: Il partecipante completa il corso con successo
4. **Superamento Esame**: Il partecipante supera l'esame (exam_passed = 1) O riceve il certificato (certificate_issued = 1)
5. **Registrazione Automatica**: Il corso viene automaticamente aggiunto alla scheda "Corsi" del socio

### Tabelle Coinvolte

#### training_courses
Contiene i corsi di formazione organizzati dall'associazione.

#### training_participants
Contiene i partecipanti ai corsi con i loro dati di frequenza e risultati.

#### member_courses
Contiene i corsi completati dai soci (sia interni che esterni). 
**Novità**: Ora include un riferimento (`training_course_id`) ai corsi dalla sezione Formazione.

## Dettagli Tecnici

### Modifiche al Database

```sql
ALTER TABLE `member_courses`
ADD COLUMN `training_course_id` int(11) DEFAULT NULL 
    COMMENT 'Reference to training_courses if from organized training',
ADD CONSTRAINT `fk_member_courses_training` 
    FOREIGN KEY (`training_course_id`) 
    REFERENCES `training_courses`(`id`) 
    ON DELETE SET NULL;
```

### Modifiche al Codice

#### TrainingController::updateParticipant()
Quando un partecipante viene aggiornato con:
- `exam_passed = 1` (esame superato)
- O `certificate_issued = 1` (certificato rilasciato)

Viene automaticamente chiamato `addCourseToMemberRecord()` che:
1. Controlla se il corso esiste già nel registro del socio
2. Se esiste, aggiorna i dati
3. Se non esiste, crea una nuova entry con:
   - Nome del corso
   - Tipo di corso
   - Data di completamento (end_date del corso o data corrente)
   - Riferimento al training_course_id

## Interfaccia Utente

### Scheda Corsi del Socio

La scheda "Corsi" nella visualizzazione del socio (`member_view.php`) ora mostra:

1. **Colonna "Fonte"**: 
   - Badge verde "Formazione" per corsi dalla sezione Formazione
   - Badge grigio "Manuale" per corsi inseriti manualmente

2. **Link al Corso**: 
   - I corsi dalla Formazione sono cliccabili e portano alla pagina del corso

3. **Protezione Eliminazione**: 
   - I corsi dalla Formazione non possono essere eliminati dalla scheda Soci
   - Viene mostrata un'icona di lucchetto invece del pulsante Elimina

4. **Alert Informativo**: 
   - Un messaggio informa gli utenti che i corsi completati nella Formazione vengono aggiunti automaticamente

## Casi d'Uso

### Caso 1: Socio Completa Corso Base
1. Il socio viene iscritto al "Corso Base Protezione Civile"
2. Partecipa alle lezioni
3. Supera l'esame finale
4. Il sistema registra automaticamente il corso nella sua scheda personale
5. Il corso appare nella scheda "Corsi" con badge "Formazione"

### Caso 2: Corso Esterno
1. Il socio completa un corso esterno (es. HACCP)
2. L'amministratore può aggiungerlo manualmente nella scheda "Corsi"
3. Questo corso avrà badge "Manuale" e potrà essere eliminato manualmente

### Caso 3: Aggiornamento Corso Completato
1. Un socio ha già un corso nel registro (es. aggiunto manualmente)
2. Successivamente completa lo stesso corso nella sezione Formazione
3. Il sistema aggiorna il record esistente anziché duplicarlo
4. Aggiunge il riferimento al training_course_id

## Protezione Dati

### Prevenzione Eliminazione
La funzione `Member::deleteCourse()` controlla se il corso ha un `training_course_id`:
- Se SÌ: l'eliminazione viene bloccata e ritorna `false`
- Se NO: il corso può essere eliminato normalmente

### Prevenzione Duplicati
La funzione `TrainingController::addCourseToMemberRecord()` controlla se esiste già un record per:
- Stesso member_id
- Stesso training_course_id

Se esiste, aggiorna il record invece di crearne uno nuovo.

## Benefici

1. **Riduzione Errori**: Elimina la necessità di inserimento manuale
2. **Consistenza Dati**: I dati sono sempre sincronizzati tra Formazione e Soci
3. **Tracciabilità**: Ogni corso nel registro ha un link al corso originale
4. **Storico Completo**: Il socio vede tutti i suoi corsi in un unico posto
5. **Audit Trail**: Tutti i cambiamenti sono registrati nel log delle attività

## Migrazione

### Per Nuove Installazioni
La colonna `training_course_id` è già presente nello schema `database_schema.sql`.

### Per Installazioni Esistenti
Eseguire la migration:
```bash
mysql -u username -p database_name < migrations/20260105_add_training_course_link_to_member_courses.sql
```

O utilizzare lo script di migrazione automatico del sistema (se disponibile).

## Limitazioni e Note

1. I corsi inseriti manualmente prima dell'implementazione non avranno `training_course_id`
2. Solo i corsi con esame superato O certificato rilasciato vengono aggiunti automaticamente
3. I corsi non completati o falliti NON vengono aggiunti al registro del socio
4. La data di completamento usa la `end_date` del corso, o la data corrente se non disponibile
5. I corsi dalla Formazione non possono essere modificati o eliminati dalla scheda Soci

## Troubleshooting

### Corso non appare nella scheda del socio
Verificare che:
1. Il partecipante abbia `exam_passed = 1` O `certificate_issued = 1`
2. Il corso abbia un `course_name` valido
3. Non ci siano errori nel log applicativo

### Errore durante aggiornamento partecipante
Controllare:
1. Che la tabella `member_courses` abbia la colonna `training_course_id`
2. Che il vincolo di foreign key sia presente
3. I permessi del database

### Non posso eliminare un corso dalla scheda Soci
Questo è il comportamento corretto se il corso proviene dalla Formazione. 
Per gestire questi corsi, utilizzare la sezione Formazione.
