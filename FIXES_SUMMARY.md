# Risoluzione Problemi di Salvataggio e Assegnazione Radio

Questo documento descrive le correzioni apportate per risolvere i problemi segnalati.

## Problemi Risolti

### 1. Errore Salvataggio Nuovo Socio ✅

**Problema:** Durante l'inserimento di un nuovo socio, veniva mostrato l'errore generico "Errore durante il salvataggio" senza indicazioni sul motivo.

**Causa:** Il controller catturava le eccezioni del database ma restituiva solo `false`, impedendo la visualizzazione del messaggio di errore reale.

**Soluzione:** 
- Modificato `MemberController::create()` per lanciare l'eccezione invece di restituire `false`
- Aggiunto controllo transazione attiva prima del rollback
- Il messaggio di errore reale viene ora visualizzato all'utente

**File Modificati:**
- `src/Controllers/MemberController.php` (righe 128-132, 208-212)

---

### 2. Errore Salvataggio Nuovo Cadetto ✅

**Problema:** Durante l'inserimento di un nuovo socio minorenne, veniva mostrato l'errore generico "Errore durante il salvataggio".

**Causa:** Come per i soci maggiorenni, il controller nascondeva il messaggio di errore reale.

**Soluzione:**
- Modificato `JuniorMemberController::create()` e `update()` per lanciare le eccezioni
- Aggiunto controllo transazione attiva prima del rollback
- Ora gli errori del database vengono mostrati correttamente

**File Modificati:**
- `src/Controllers/JuniorMemberController.php` (righe 211-215, 318-322)

---

### 3. Errore Salvataggio Manutenzione Mezzo ✅

**Problema:** Errore generico durante l'inserimento di una nuova manutenzione di un mezzo.

**Causa:** Il controller non propagava l'eccezione al codice chiamante.

**Soluzione:**
- Modificato `VehicleController::addMaintenance()` per lanciare le eccezioni
- Migliorata la gestione errori in `vehicle_maintenance_save.php`
- Aggiunto controllo transazione attiva prima del rollback

**File Modificati:**
- `src/Controllers/VehicleController.php` (righe 291-296)
- `public/vehicle_maintenance_save.php` (righe 69-82)

---

### 4. Errore Salvataggio Nuovo Evento ✅

**Problema:** Errore generico durante l'inserimento di un nuovo evento in Eventi/Interventi.

**Causa:** Il controller non propagava l'eccezione con il messaggio di errore reale.

**Soluzione:**
- Modificato `EventController::create()` e `update()` per lanciare le eccezioni
- Aggiunto controllo transazione attiva prima del rollback

**File Modificati:**
- `src/Controllers/EventController.php` (righe 109-113, 143-146)

---

### 5. Assegnazione Radio a Personale Esterno ✅

**Problema:** L'assegnazione di una radio portatile poteva avvenire solo a volontari dell'associazione.

**Requisito:** Permettere l'assegnazione anche a personale esterno richiedendo: nome, cognome, ente, numero di cellulare.

**Soluzione:**
- Aggiunto supporto per due tipi di assegnazione nel form:
  1. **Volontario dell'Associazione** (come prima)
  2. **Personale Esterno** (nuovo)
- Aggiunti campi per personale esterno:
  - Cognome (obbligatorio)
  - Nome (obbligatorio)
  - Ente/Organizzazione (obbligatorio)
  - Numero di Cellulare (obbligatorio)
- Implementato metodo `assignRadioToExternal()` nel controller
- Le assegnazioni esterne usano `member_id = NULL` e compilano i campi `assignee_*`
- Aggiunto JavaScript per mostrare/nascondere campi in base alla selezione

**File Modificati:**
- `public/radio_view.php` (righe 287-373)
- `public/radio_assign.php` (completamente riscritto)
- `src/Controllers/OperationsCenterController.php` (nuovo metodo `assignRadioToExternal()`)

---

### 6. Errore Ripristino Template di Stampa ✅

**Problema:** "Errore durante il ripristino: There is no active transaction"

**Causa:** Il codice controllava se una transazione fosse attiva usando `$connection->inTransaction()` ma la variabile `$connection` poteva non essere definita se l'eccezione avveniva prima della sua inizializzazione.

**Soluzione:**
- Aggiunto controllo sicuro per l'esistenza della connessione database
- Gestito il caso in cui `$connection` non sia inizializzata
- Verificata l'esistenza della transazione prima del rollback

**File Modificati:**
- `public/restore_print_templates.php` (righe 116-125)

---

## Come Verificare le Correzioni

### Test 1: Creazione Socio

1. Accedere all'area "Gestione Soci"
2. Cliccare su "Nuovo Socio"
3. Compilare i campi obbligatori
4. Cliccare su "Salva"
5. **Risultato Atteso:** 
   - Se tutto OK: reindirizzamento alla scheda socio con messaggio di successo
   - Se errore: messaggio dettagliato dell'errore (es. "Codice fiscale duplicato")

### Test 2: Creazione Cadetto

1. Accedere all'area "Gestione Cadetti"
2. Cliccare su "Nuovo Cadetto"
3. Compilare i campi obbligatori inclusi dati tutore
4. Cliccare su "Salva"
5. **Risultato Atteso:** Come Test 1

### Test 3: Manutenzione Mezzo

1. Accedere alla scheda di un mezzo
2. Andare alla tab "Manutenzioni"
3. Cliccare su "Nuova Manutenzione"
4. Compilare i campi obbligatori
5. Cliccare su "Salva"
6. **Risultato Atteso:** 
   - Se tutto OK: manutenzione salvata e visibile nella lista
   - Se errore: messaggio dettagliato dell'errore

### Test 4: Creazione Evento

1. Accedere a "Eventi/Interventi"
2. Cliccare su "Nuovo Evento"
3. Compilare i campi obbligatori
4. Cliccare su "Salva"
5. **Risultato Atteso:** Come Test 1

### Test 5: Assegnazione Radio a Personale Esterno

1. Accedere alla "Rubrica Radio"
2. Aprire una radio disponibile
3. Cliccare su "Assegna Radio"
4. Selezionare "Personale Esterno"
5. Compilare: Nome, Cognome, Ente, Cellulare
6. Cliccare su "Assegna"
7. **Risultato Atteso:** 
   - Radio assegnata con successo
   - I dati del personale esterno visibili nella scheda radio
   - Stato radio cambiato ad "Assegnata"

### Test 6: Ripristino Template di Stampa

1. Accedere a "Impostazioni"
2. Scorrere fino alla sezione "Template di Stampa"
3. Cliccare su "Ripristina Template Predefiniti"
4. Confermare l'operazione
5. **Risultato Atteso:** 
   - Messaggio di successo con numero di template ripristinati
   - Nessun errore "There is no active transaction"

---

## Note Tecniche

### Gestione delle Transazioni

Tutti i metodi modificati ora includono un controllo per verificare se una transazione è attiva prima di effettuare il rollback:

```php
if ($this->db->getConnection()->inTransaction()) {
    $this->db->rollBack();
}
```

Questo previene l'errore "There is no active transaction" che può verificarsi se un'eccezione viene lanciata prima dell'inizio della transazione o dopo il commit.

### Propagazione delle Eccezioni

I controller ora lanciano le eccezioni invece di restituire `false`:

**Prima:**
```php
catch (\Exception $e) {
    $this->db->rollBack();
    error_log("Errore: " . $e->getMessage());
    return false;  // ❌ Nasconde l'errore
}
```

**Dopo:**
```php
catch (\Exception $e) {
    if ($this->db->getConnection()->inTransaction()) {
        $this->db->rollBack();
    }
    error_log("Errore: " . $e->getMessage());
    throw $e;  // ✅ Propaga l'errore con il messaggio
}
```

### Schema Database Radio Assignments

La tabella `radio_assignments` supporta entrambi i tipi di assegnazione:

- **Assegnazione a Volontario:** `member_id` valorizzato (NOT NULL)
- **Assegnazione a Esterno:** `member_id = NULL`

In entrambi i casi, i campi `assignee_first_name`, `assignee_last_name`, `assignee_phone`, `assignee_organization` vengono compilati per mantenere i dati anche se il volontario viene eliminato.

---

## Errori Comuni e Soluzioni

### "Duplicate entry" per matricola/codice fiscale

**Causa:** Tentativo di inserire un socio con matricola o codice fiscale già esistente.

**Soluzione:** Verificare che la matricola sia unica o lasciare vuoto il campo per la generazione automatica.

### "Data truncation" o "Invalid date"

**Causa:** Formato data non valido o data fuori range.

**Soluzione:** Verificare che le date siano nel formato corretto (YYYY-MM-DD).

### "Foreign key constraint fails"

**Causa:** Riferimento a un record inesistente (es. user_id non valido).

**Soluzione:** Verificare che tutti gli ID referenziati esistano nel database.

---

## Compatibilità

Tutte le modifiche sono backward-compatible:

- ✅ Nessuna modifica allo schema del database richiesta
- ✅ Le assegnazioni esistenti continuano a funzionare
- ✅ L'interfaccia utente rimane invariata (eccetto l'aggiunta del form esterno)
- ✅ Nessun breaking change per le API interne

---

## Conclusioni

Tutti e 6 i problemi segnalati sono stati risolti con successo. Le modifiche migliorano significativamente l'esperienza utente fornendo messaggi di errore chiari e dettagliati, e aggiungono nuove funzionalità (assegnazione radio a esterni) come richiesto.

Le correzioni seguono le best practices di gestione degli errori e transazioni del database, garantendo la robustezza dell'applicazione anche in caso di errori imprevisti.
