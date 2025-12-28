# Gestione Reperibilità Volontari - Documentazione

## Panoramica

Questa funzionalità consente una gestione completa della reperibilità dei volontari, sia per gli operatori della Centrale Operativa che per i volontari stessi attraverso un portale self-service.

## Funzionalità Implementate

### 1. Ricerca Volontari con Autocomplete

**Pagina:** Centrale Operativa (`operations_center.php`)

- Invece del menu a tendina con tutti i volontari, ora è presente un campo di ricerca intelligente
- Cerca volontari per:
  - Numero matricola (badge_number o registration_number)
  - Nome
  - Cognome
- Mostra risultati mentre si digita (minimo 1 carattere)
- Utile per associazioni con oltre 100 volontari

**Endpoint AJAX:** `members_search_ajax.php`

### 2. Visualizzazione Dettagliata Volontari Reperibili

**Pagina:** Centrale Operativa (`operations_center.php`)

Per ogni volontario reperibile vengono ora visualizzati:
- Nome e cognome
- Numero di matricola
- **Numero di cellulare** (se presente)
- **Radio assegnata** (nome e identificativo, se presente)
- **Note sulla reperibilità**
- Data e ora inizio/fine reperibilità

**Controller:** `OperationsCenterController::getAvailableVolunteers()`

### 3. Modifica e Eliminazione Reperibilità

**Pagina:** Centrale Operativa (`operations_center.php`)

Gli operatori possono ora:
- **Modificare** una reperibilità esistente:
  - Data e ora inizio
  - Data e ora fine
  - Note
- **Eliminare** una reperibilità

**Funzionalità:**
- Pulsante "Modifica" (icona matita) apre una modale
- Pulsante "Elimina" (icona cestino) con conferma
- Validazione per evitare sovrapposizioni
- Controllo permessi (solo utenti autorizzati)

**Endpoint AJAX:** 
- `on_call_ajax.php?action=update_on_call`
- `on_call_ajax.php?action=remove_on_call`

### 4. Portale Pubblico per Volontari

**Pagina:** Portale Soci Reperibilità (`member_portal_on_call.php`)

I volontari possono gestire autonomamente le proprie reperibilità:

#### Accesso:
- Login con matricola e cognome (stessa modalità del pagamento quota)
- Non richiede verifica email (accesso semplificato)
- Accessibile solo a soci maggiorenni e attivi

#### Funzionalità per i volontari:
1. **Inserire nuove reperibilità:**
   - Data e ora inizio
   - Data e ora fine
   - Note opzionali (es: "Disponibile per emergenze", "Non disponibile dopo le 22:00")

2. **Visualizzare le proprie reperibilità:**
   - **In corso:** Reperibilità attive al momento
   - **Future:** Reperibilità programmate
   - **Passate:** Storico delle reperibilità completate

3. **Modificare reperibilità future o in corso:**
   - Cambiare date e orari
   - Aggiornare note

4. **Eliminare reperibilità:**
   - Rimuovere reperibilità non più valide

#### Integrazione con Centrale Operativa:
- Le reperibilità inserite dai volontari appaiono automaticamente nella Centrale Operativa
- **Importante:** Vengono mostrate SOLO se in corso (tra data/ora inizio e data/ora fine)
- Le reperibilità future non vengono visualizzate in Centrale Operativa fino al loro inizio

**Controller:** `MemberPortalController` con nuovi metodi:
- `getOnCallSchedules()` - Recupera le reperibilità di un volontario
- `addOnCallSchedule()` - Aggiunge una nuova reperibilità
- `updateOnCallSchedule()` - Modifica una reperibilità esistente
- `deleteOnCallSchedule()` - Elimina una reperibilità

## Storico Reperibilità

**Pagina:** `on_call_history.php`

Aggiornata per mostrare anche:
- Numero di cellulare
- Radio assegnata (se presente)
- Colonna dedicata nella tabella

## Database

La tabella `on_call_schedule` contiene:
- `id` - Identificativo univoco
- `member_id` - ID del volontario
- `start_datetime` - Data e ora inizio reperibilità
- `end_datetime` - Data e ora fine reperibilità
- `notes` - Note sulla reperibilità
- `created_by` - ID utente che ha creato la reperibilità
- `created_at` - Data creazione
- `updated_at` - Data ultimo aggiornamento

## Sicurezza

### Validazioni implementate:
1. **CSRF Protection:** Token CSRF su tutti i form
2. **Controllo permessi:** Solo utenti autorizzati possono gestire reperibilità in Centrale Operativa
3. **Validazione date:** La data di fine deve essere successiva alla data di inizio
4. **Prevenzione sovrapposizioni:** Un volontario non può avere reperibilità sovrapposte
5. **Verifica identità:** Nel portale pubblico, ogni volontario può modificare solo le proprie reperibilità

### Logging:
- Tutte le operazioni vengono registrate tramite `AutoLogger`
- Include: aggiunta, modifica, eliminazione reperibilità

## URL e Accesso

### Per gli operatori:
- **Centrale Operativa:** `/public/operations_center.php`
  - Richiede: Login e permesso `operations_center:view`
  - Modifica richiede: permesso `operations_center:edit`

- **Storico Reperibilità:** `/public/on_call_history.php`
  - Richiede: Login e permesso `operations_center:view`

### Per i volontari:
- **Portale Reperibilità:** `/public/member_portal_on_call.php`
  - Accesso pubblico con autenticazione matricola + cognome
  - Disponibile per soci maggiorenni e attivi

## Esempio di Utilizzo

### Scenario 1: Operatore aggiunge reperibilità
1. L'operatore va su Centrale Operativa
2. Clicca "Aggiungi" nella sezione Volontari Reperibili
3. Cerca il volontario digitando nome, cognome o matricola
4. Seleziona il volontario dai risultati
5. Imposta data/ora inizio, fine e note
6. Salva - il volontario appare immediatamente se la reperibilità è già iniziata

### Scenario 2: Volontario si mette in reperibilità
1. Il volontario accede a `/member_portal_on_call.php`
2. Inserisce matricola e cognome
3. Compila il form "Aggiungi Nuova Reperibilità"
4. Imposta data/ora inizio, fine e note (es: "Reperibile per turno notturno")
5. Salva - se la reperibilità è in corso, appare in Centrale Operativa

### Scenario 3: Modifica reperibilità in corso
1. L'operatore vede una reperibilità in Centrale Operativa
2. Clicca sul pulsante "Modifica" (icona matita)
3. Modifica le date o aggiunge/modifica note
4. Salva - le modifiche sono immediate

## Note Tecniche

### Performance:
- Query ottimizzate con LEFT JOIN per includere radio e telefono
- Indici su `start_datetime` e `end_datetime` per ricerca rapida
- Ricerca autocomplete limitata a 20 risultati

### Compatibilità:
- Bootstrap 5.3.0
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.2+

### Browser supportati:
- Chrome, Firefox, Safari, Edge (versioni recenti)
- Richiede JavaScript abilitato per autocomplete e modali

## Possibili Estensioni Future

1. **Notifiche:**
   - Email/SMS quando un volontario viene messo in reperibilità
   - Promemoria prima dell'inizio della reperibilità

2. **Calendario:**
   - Vista calendario delle reperibilità
   - Esportazione iCal/Google Calendar

3. **Statistiche:**
   - Report ore di reperibilità per volontario
   - Grafici copertura reperibilità

4. **Turni automatici:**
   - Generazione automatica turni di reperibilità
   - Rotazione equa tra volontari disponibili

## Supporto

Per problemi o domande contattare l'amministratore di sistema.
