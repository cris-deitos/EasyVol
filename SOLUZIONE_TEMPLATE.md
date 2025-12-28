# Soluzione: Ripristino Template di Stampa - EasyVol

## 🎯 Problema Risolto
**"Nessun modello di stampa trovato"** - La tabella `print_templates` era vuota.

## ✅ Soluzione Implementata

Ho creato un sistema completo per ripristinare i 10 template di stampa predefiniti con **3 metodi diversi** per massima flessibilità.

---

## 🚀 METODO RACCOMANDATO: Interfaccia Web (Più Facile)

### Passaggi:
1. **Accedi** al pannello EasyVol come amministratore
2. **Vai a**: Impostazioni → Modelli di Stampa
3. **Clicca** sul pulsante blu **"Ripristina Template Predefiniti"**
4. **Conferma** l'operazione
5. **Fatto!** I template saranno immediatamente disponibili

### Caratteristiche:
- ✅ Un solo click
- ✅ Interfaccia grafica intuitiva
- ✅ Messaggi di successo/errore chiari
- ✅ Non sovrascrive template esistenti
- ✅ Registra l'operazione nei log di sistema

---

## 📁 File Creati

### 1. `seed_print_templates.sql` (31 KB)
File SQL con i 10 template predefiniti. Può essere eseguito:
- Via command line: `mysql -u username -p database_name < seed_print_templates.sql`
- Via phpMyAdmin: Import → Scegli file → Esegui
- Automaticamente via interfaccia web

### 2. `public/restore_print_templates.php`
Pagina web per ripristinare i template con un click. Include:
- Form di conferma con CSRF protection
- Lista dei template che verranno ripristinati
- Messaggi di successo/errore dettagliati
- Link rapidi alle impostazioni
- Registrazione attività nei log

### 3. `SEED_TEMPLATES_README.md`
Documentazione completa in italiano con:
- Descrizione di tutti i template
- 3 metodi di ripristino spiegati passo-passo
- Risoluzione problemi comuni
- Note sulla sicurezza e permessi

### 4. Modifiche a `public/settings.php`
Aggiunto pulsante prominente "Ripristina Template Predefiniti" quando la lista è vuota.

### 5. `.gitignore`
Aggiunta eccezione per includere `seed_print_templates.sql` nel repository.

---

## 📋 Template Inclusi (10)

### 👥 Soci / Members (5 template)
1. **Tessera Socio** - Formato carta di credito (8.5cm × 5.4cm)
2. **Scheda Socio** - Dettagli completi con contatti, indirizzi, patenti, corsi
3. **Attestato di Partecipazione** - Certificato personalizzabile
4. **Libro Soci** - Elenco completo formato tabella
5. **Tessere Multiple** - Generazione in blocco per più soci

### 🚗 Mezzi / Vehicles (2 template)
6. **Scheda Mezzo** - Dati tecnici + storico manutenzioni
7. **Elenco Mezzi** - Lista completa parco mezzi

### 📝 Riunioni / Meetings (2 template)
8. **Verbale di Riunione** - Con partecipanti e ordine del giorno
9. **Foglio Presenze** - Foglio firme per assemblee

### 🎯 Eventi / Events (1 template)
10. **Elenco Eventi** - Registro eventi con date e tipologie

---

## 🔒 Sicurezza

- ✅ Richiede permessi di amministratore
- ✅ Protezione CSRF sui form
- ✅ Validazione SQL injection-proof
- ✅ Non sovrascrive template esistenti
- ✅ Transazioni database (rollback su errore)
- ✅ Log di tutte le operazioni

---

## 🧪 Test Consigliati

Dopo il ripristino, verifica che tutto funzioni:

1. **Vai a**: Impostazioni → Modelli di Stampa
2. **Verifica**: Dovresti vedere 10 template nella lista
3. **Testa**: Prova a generare un PDF di test con "Tessera Socio"
4. **Controlla**: I template dovrebbero essere marcati come "Attivi"

---

## 🐛 Risoluzione Problemi

### ❌ "File seed_print_templates.sql non trovato"
**Soluzione**: Il file deve essere nella directory principale del progetto (stessa directory di `database_schema.sql`)

### ❌ "Accesso negato"
**Soluzione**: Verifica di essere autenticato come amministratore e di avere i permessi su "Impostazioni"

### ❌ "Template già presenti"
**Soluzione**: Non è un errore! I template esistenti non vengono sovrascritti. Se vuoi ricominciare da zero, elimina prima i template dalla pagina Impostazioni.

### ❌ "Errore database"
**Soluzione**: 
1. Verifica che la tabella `print_templates` esista
2. Controlla i permessi INSERT sul database
3. Verifica i log di PHP per dettagli

---

## 📊 Statistiche Implementazione

- **Linee di codice**: ~700 linee (PHP + SQL + Docs)
- **Template HTML**: Utilizzano Handlebars-style syntax
- **Compatibilità**: MySQL 5.6+ e MySQL 8.x
- **Formato PDF**: Supporto A4, Landscape, Portrait, Custom
- **Relazioni**: Supporto dati correlati (contatti, indirizzi, etc.)

---

## 🎓 Come Funziona

### Database
```sql
-- La tabella print_templates contiene:
- HTML content (template Handlebars)
- CSS styling
- Configurazione pagina (formato, orientamento)
- Header/Footer personalizzabili
- Relazioni con altre tabelle
```

### Rendering
```php
// Il sistema usa:
1. PrintTemplateController->generate()
2. Sostituisce variabili {{field_name}}
3. Processa loop {{#each array}}
4. Gestisce relazioni (join automatici)
5. Genera PDF con dompdf o wkhtmltopdf
```

---

## 📞 Supporto

Se hai problemi:
1. Leggi `SEED_TEMPLATES_README.md` per dettagli
2. Controlla i log in `/logs/` o nei log Apache/PHP
3. Verifica i permessi del database
4. Assicurati che il file `seed_print_templates.sql` sia presente

---

## ✨ Funzionalità Extra

I template supportano:
- **Variabili dinamiche**: `{{field_name}}`
- **Loop**: `{{#each array}} ... {{/each}}`
- **Condizionali**: `{{#if field}} ... {{/if}}`
- **Formattazione date**: Automatica (gg/mm/aaaa)
- **Relazioni**: Caricamento automatico dati correlati
- **Paginazione**: Header/Footer su ogni pagina
- **Watermark**: Supporto filigrana
- **Export/Import**: Template esportabili/importabili

---

## 📝 Note Finali

- ⚠️ I template sono in italiano
- ⚠️ Personalizzabili dall'editor web
- ⚠️ I template di default NON hanno `created_by` (sono di sistema)
- ✅ Sicuro eseguire multiplo volte (no duplicati)
- ✅ Reversibile (puoi eliminare dopo se necessario)

---

**Versione**: 1.0  
**Data**: Dicembre 2024  
**Autore**: GitHub Copilot per cris-deitos  
**Repository**: cris-deitos/EasyVol  
**Branch**: copilot/restore-print-templates
