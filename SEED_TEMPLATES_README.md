# Ripristino Template di Stampa - EasyVol

## Problema
Se visualizzi il messaggio "Nessun modello di stampa trovato" nella sezione Impostazioni > Modelli di Stampa, significa che la tabella `print_templates` nel database è vuota.

## Soluzione
Questo repository contiene un file di seed (`seed_print_templates.sql`) che ripristina i 10 template di stampa predefiniti del sistema.

## Template Inclusi

### Soci (Members)
1. **Tessera Socio** - Tessera associativa singola (formato carta di credito)
2. **Scheda Socio** - Scheda dettagliata con contatti, indirizzi, patenti e corsi
3. **Attestato di Partecipazione** - Attestato personalizzabile per eventi
4. **Libro Soci** - Elenco completo di tutti i soci
5. **Tessere Multiple** - Genera tessere in blocco per più soci

### Mezzi (Vehicles)
6. **Scheda Mezzo** - Scheda tecnica con storico manutenzioni
7. **Elenco Mezzi** - Lista completa del parco mezzi

### Riunioni (Meetings)
8. **Verbale di Riunione** - Verbale ufficiale con partecipanti e ordine del giorno
9. **Foglio Presenze Riunione** - Foglio firme per assemblee

### Eventi (Events)
10. **Elenco Eventi** - Registro di tutti gli eventi

## Come Ripristinare i Template

### Metodo 1: Via Command Line (Raccomandato)

```bash
# Dalla directory principale del progetto
mysql -u [username] -p [database_name] < seed_print_templates.sql
```

Sostituisci:
- `[username]` con il tuo username MySQL (es. `root`)
- `[database_name]` con il nome del tuo database (es. `easyvol`)

Esempio:
```bash
mysql -u root -p easyvol < seed_print_templates.sql
```

### Metodo 2: Via phpMyAdmin

1. Accedi a phpMyAdmin
2. Seleziona il database EasyVol
3. Clicca sulla tab "SQL"
4. Apri il file `seed_print_templates.sql` con un editor di testo
5. Copia tutto il contenuto
6. Incolla nel campo SQL di phpMyAdmin
7. Clicca su "Esegui" o "Go"

### Metodo 3: Via altri client MySQL

Se usi altri strumenti (MySQL Workbench, HeidiSQL, DBeaver, etc.):
1. Apri la connessione al database EasyVol
2. Esegui il file SQL `seed_print_templates.sql`
3. Verifica che i template siano stati inseriti

## Verifica

Dopo aver eseguito il seed:

1. Vai su **Impostazioni** > **Modelli di Stampa**
2. Dovresti vedere 10 template elencati
3. Prova a generare una stampa di prova per verificare

## Note Importanti

- ⚠️ Questo seed NON sovrascrive template esistenti con lo stesso nome
- ⚠️ Se vuoi ricominciare da zero, prima esegui: `DELETE FROM print_templates;`
- ✅ È sicuro eseguire questo seed su un'installazione nuova
- ✅ È sicuro eseguire questo seed su un'installazione esistente (non duplicherà i template)
- ✅ I template vengono inseriti con `created_by = NULL` (template di sistema)

## Risoluzione Problemi

### Errore: "Table 'print_templates' doesn't exist"
Il database non è stato inizializzato correttamente. Esegui prima:
```bash
mysql -u [username] -p [database_name] < database_schema.sql
```

### I template non compaiono dopo l'importazione
1. Verifica che l'importazione sia andata a buon fine senza errori
2. Controlla che i template siano stati inseriti:
   ```sql
   SELECT COUNT(*) FROM print_templates;
   ```
3. Svuota la cache del browser (Ctrl+F5)
4. Ricarica la pagina Impostazioni

### Permessi insufficienti
Assicurati che l'utente MySQL abbia i permessi INSERT sulla tabella `print_templates`:
```sql
GRANT INSERT ON easyvol.print_templates TO 'username'@'localhost';
FLUSH PRIVILEGES;
```

## Personalizzazione

Dopo il ripristino, puoi:
- Modificare i template esistenti dalla UI
- Crearne di nuovi
- Disattivare quelli non necessari
- Esportare template personalizzati per backup

## Supporto

Per problemi o domande:
1. Controlla i log del database per errori SQL
2. Verifica che la versione del database schema sia corretta
3. Consulta la documentazione di EasyVol

---

**Versione:** 1.0  
**Data:** Dicembre 2024  
**Compatibilità:** EasyVol con MySQL 5.6+ e MySQL 8.x
