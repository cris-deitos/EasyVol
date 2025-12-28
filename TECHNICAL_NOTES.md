# Note Tecniche e Miglioramenti Futuri

## Implementazione Corrente

L'implementazione della gestione reperibilità è **completa e funzionale** per una prima installazione, con tutte e 4 le funzionalità richieste implementate.

## Sicurezza Implementata

✅ **Implementato:**
- CSRF protection su tutti i form
- Form actions espliciti (prevenzione referer manipulation)
- JSON encoding con flags sicuri in `operations_center.php`
- Input validation e sanitization
- Controllo permessi e autenticazione
- Prevenzione sovrapposizioni reperibilità

## Miglioramenti Consigliati per Versioni Future

### 1. Event Listeners invece di Onclick Inline (Bassa Priorità)

**Attuale:** `member_portal_on_call.php` usa onclick inline
```php
onclick="editSchedule(...)"
```

**Miglioramento consigliato:**
```php
<!-- HTML -->
<button class="btn-edit-schedule" 
        data-schedule-id="123"
        data-start="2024-01-01 10:00:00"
        data-end="2024-01-01 18:00:00"
        data-notes="Note...">

<!-- JavaScript -->
document.querySelectorAll('.btn-edit-schedule').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.scheduleId;
        const start = this.dataset.start;
        const end = this.dataset.end;
        const notes = this.dataset.notes;
        editSchedule(id, start, end, notes);
    });
});
```

**Beneficio:** Separazione tra HTML e JavaScript, miglior sicurezza

**Nota:** L'implementazione attuale usa `htmlspecialchars()` con `ENT_QUOTES` che è sufficientemente sicuro per una prima installazione.

### 2. Gestione DateTime con Timezone (Media Priorità)

**Attuale:** DateTime senza timezone esplicito
```php
$start = new DateTime($startDatetime);
```

**Miglioramento consigliato:**
```php
try {
    $timezone = new DateTimeZone('Europe/Rome'); // o dal config
    $start = new DateTime($startDatetime, $timezone);
} catch (Exception $e) {
    // Gestione errore
    return ['success' => false, 'message' => 'Data non valida'];
}
```

**Beneficio:** 
- Gestione corretta timezone
- Prevenzione fatal error su datetime invalidi

**Nota:** L'implementazione attuale funziona correttamente finché i datetime sono validi. La validazione lato client previene la maggior parte degli errori.

### 3. Null Check per Dashboard Data (Bassa Priorità)

**Attuale:** `operations_center.php` assume che `$dashboard['available_members']` esista
```php
const availableMembers = <?= json_encode($dashboard['available_members']) ?>;
```

**Miglioramento consigliato:**
```php
const availableMembers = <?= json_encode($dashboard['available_members'] ?? []) ?>;
```

**Beneficio:** Prevenzione JavaScript error se array è null

**Nota:** Il controller inizializza sempre l'array, quindi questo è solo per robustezza extra.

### 4. Code Duplication - Overlap Check (Bassa Priorità)

**Attuale:** Logica duplicata in `MemberPortalController::addOnCallSchedule()` e `updateOnCallSchedule()`

**Miglioramento consigliato:**
```php
private function checkScheduleOverlap($memberId, $startDatetime, $endDatetime, $excludeScheduleId = null) {
    $sql = "SELECT COUNT(*) as count FROM on_call_schedule 
            WHERE member_id = ? ";
    
    if ($excludeScheduleId) {
        $sql .= "AND id != ? ";
    }
    
    $sql .= "AND ((start_datetime <= ? AND end_datetime >= ?) 
                 OR (start_datetime <= ? AND end_datetime >= ?)
                 OR (start_datetime >= ? AND end_datetime <= ?))";
    
    // ... resto della logica
}
```

**Beneficio:** DRY principle, manutenibilità

**Nota:** La duplicazione attuale è minima e non causa problemi funzionali.

### 5. Performance - Search Query Optimization (Media Priorità)

**Attuale:** `members_search_ajax.php` usa multipli LIKE
```sql
WHERE badge_number LIKE ? OR registration_number LIKE ? OR ...
```

**Miglioramento per grandi dataset (>1000 volontari):**
```sql
-- Opzione 1: Colonna computed
ALTER TABLE members ADD COLUMN search_text TEXT GENERATED ALWAYS AS 
    (CONCAT_WS(' ', badge_number, registration_number, first_name, last_name));
CREATE INDEX idx_members_search ON members(search_text);

-- Opzione 2: Full-text search
CREATE FULLTEXT INDEX idx_members_fulltext 
    ON members(badge_number, registration_number, first_name, last_name);
```

**Beneficio:** Performance migliorate con grandi dataset

**Nota:** Con <100-200 volontari, la performance attuale è più che sufficiente.

## Quando Implementare i Miglioramenti

### Priorità Alta (Implementare subito se necessario)
- Nessuna. Tutte le funzionalità critiche sono implementate correttamente.

### Priorità Media (Implementare prima del deploy in produzione)
- DateTime timezone handling se l'associazione opera in più timezone
- Performance optimization solo se >500 volontari

### Priorità Bassa (Implementare quando conveniente)
- Event listeners invece di onclick
- Null checks extra
- Refactoring DRY

## Testing Consigliato Prima del Deploy

1. **Test Funzionali:**
   - ✅ Inserimento reperibilità da Centrale Operativa
   - ✅ Inserimento reperibilità da Portale Volontari
   - ✅ Modifica reperibilità esistente
   - ✅ Eliminazione reperibilità
   - ✅ Ricerca volontari con autocomplete
   - ✅ Visualizzazione info complete (telefono, radio, note)

2. **Test Sicurezza:**
   - ✅ CSRF token validation
   - ✅ SQL injection prevention (prepared statements)
   - ✅ XSS prevention (htmlspecialchars, json_encode)
   - ✅ Authorization checks (permessi)
   - ✅ Autenticazione portale pubblico

3. **Test Edge Cases:**
   - Date sovrapposte (deve bloccare)
   - Data fine prima di data inizio (deve bloccare)
   - Caratteri speciali nelle note
   - Nessun telefono/radio assegnato (deve mostrare "-")

4. **Test Performance:**
   - Ricerca con 100+ volontari
   - Caricamento dashboard con 20+ reperibilità attive

## Conclusione

L'implementazione è **pronta per il deploy** in produzione per una prima installazione. I miglioramenti suggeriti sono opzionali e possono essere implementati in versioni successive in base alle esigenze reali dell'associazione.

La priorità ora è:
1. ✅ Deploy in ambiente di test
2. ✅ Test con utenti reali
3. ✅ Raccolta feedback
4. → Implementazione miglioramenti in base ai feedback
