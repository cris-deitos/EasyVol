# Index - Database Migrations

## 📋 Navigazione Rapida

### Per Iniziare Subito
👉 **[QUICK_REFERENCE.md](QUICK_REFERENCE.md)** - Comandi essenziali per import rapido

### Per Implementazione Completa
👉 **[USAGE_GUIDE.md](USAGE_GUIDE.md)** - Guida passo-passo per tutto il processo

### Per Riferimento Tecnico
👉 **[README.md](README.md)** - Documentazione tecnica dettagliata

### Per Panoramica Generale
👉 **[IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md)** - Riepilogo completo dell'implementazione

---

## 📁 File nel Package

### Script Principali

| File | Descrizione | Dimensione |
|------|-------------|------------|
| **import_soci_completo.sql** | Script SQL principale per import | 15 KB |
| **generate_import_sql.py** | Generatore automatico INSERT da CSV | 13 KB |
| **soci_example.csv** | File CSV di esempio con 5 soci | 1.6 KB |

### Documentazione

| File | Contenuto | Dimensione |
|------|-----------|------------|
| **USAGE_GUIDE.md** | Guida operativa step-by-step | 7.6 KB |
| **README.md** | Documentazione tecnica completa | 8.3 KB |
| **IMPLEMENTATION_SUMMARY.md** | Riepilogo implementazione | 8.6 KB |
| **QUICK_REFERENCE.md** | Riferimento comandi rapidi | 2.7 KB |
| **INDEX.md** | Questo file (navigazione) | - |

---

## 🎯 Cosa Fare Adesso?

### Scenario 1: "Voglio importare i 175 soci ORA"
1. Leggi **[QUICK_REFERENCE.md](QUICK_REFERENCE.md)**
2. Prepara il tuo CSV (usa `soci_example.csv` come riferimento)
3. Esegui: `python3 generate_import_sql.py tuoi_soci.csv > inserts.sql`
4. Segui i comandi in QUICK_REFERENCE per creare lo script finale
5. Fai backup e esegui

### Scenario 2: "Voglio capire tutto il processo prima di iniziare"
1. Leggi **[USAGE_GUIDE.md](USAGE_GUIDE.md)** per il workflow completo
2. Leggi **[README.md](README.md)** per i dettagli tecnici
3. Testa con `soci_example.csv` su database di sviluppo
4. Procedi con i tuoi dati reali

### Scenario 3: "Ho un problema/errore"
1. Controlla **[QUICK_REFERENCE.md](QUICK_REFERENCE.md)** → "Gestione Errori Comuni"
2. Consulta **[README.md](README.md)** → sezione "Troubleshooting"
3. Verifica **[USAGE_GUIDE.md](USAGE_GUIDE.md)** → "Risoluzione Problemi Comuni"

### Scenario 4: "Voglio capire cosa è stato implementato"
1. Leggi **[IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md)**
2. Esamina `import_soci_completo.sql` per vedere la struttura
3. Prova `generate_import_sql.py` con `soci_example.csv`

---

## 🔍 Cerca per Argomento

### Mappatura Campi CSV → Database
- **[README.md](README.md)** → "Mappatura Campi CSV → Database" (tabella completa)
- **[QUICK_REFERENCE.md](QUICK_REFERENCE.md)** → "Mappatura Rapida Campi" (sintesi)

### Gestione Errori
- **[QUICK_REFERENCE.md](QUICK_REFERENCE.md)** → "Gestione Errori Comuni"
- **[README.md](README.md)** → "Gestione Errori"
- **[USAGE_GUIDE.md](USAGE_GUIDE.md)** → "Risoluzione Problemi Comuni"

### Comandi SQL
- **[QUICK_REFERENCE.md](QUICK_REFERENCE.md)** → tutti i comandi essenziali
- **[README.md](README.md)** → "Esecuzione dello Script"
- **[USAGE_GUIDE.md](USAGE_GUIDE.md)** → "Step 6: Eseguire l'Import"

### Validazione Import
- **[QUICK_REFERENCE.md](QUICK_REFERENCE.md)** → "Verifiche Rapide"
- **[README.md](README.md)** → "Validazione Post-Importazione"
- **[USAGE_GUIDE.md](USAGE_GUIDE.md)** → "Checklist Post-Import"

### Script Python
- **[README.md](README.md)** → "OPZIONE B: Script Automatico"
- **[USAGE_GUIDE.md](USAGE_GUIDE.md)** → "Step 4: Generare INSERT Statements"
- Codice sorgente: `generate_import_sql.py`

### Database Schema
- **[IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md)** → "Fase 1: Schema Updates"
- Script SQL: `import_soci_completo.sql` (righe 23-62)

---

## 📊 Statistiche Package

- **Totale file**: 8 (3 script + 5 documentazione)
- **Dimensione totale**: ~56 KB
- **Righe codice**: ~800 (SQL + Python)
- **Righe documentazione**: ~550
- **Campi CSV mappati**: 28
- **Query verifica**: 8
- **Esempi soci forniti**: 5

---

## ✅ Checklist Utilizzo

Prima di iniziare, assicurati di avere:
- [ ] MySQL/MariaDB installato e configurato
- [ ] Python 3.x installato
- [ ] File CSV con i 175 soci del vecchio gestionale
- [ ] Backup del database corrente
- [ ] Accesso al database di test per prove

---

## 🆘 Supporto

In caso di problemi:

1. **Errori durante import SQL**
   - → QUICK_REFERENCE.md → Gestione Errori
   
2. **Errori script Python**
   - → README.md → Risoluzione Problemi
   - → Verifica encoding CSV con `file -i nomefile.csv`
   
3. **Date non riconosciute**
   - → README.md → "Problema: Date non riconosciute"
   - → Modificare `generate_import_sql.py` riga ~40
   
4. **Caratteri speciali corrotti**
   - → USAGE_GUIDE.md → "Caratteri speciali corrotti"
   - → Convertire CSV in UTF-8

5. **Problemi generici**
   - → USAGE_GUIDE.md → "Supporto"
   - → Aprire issue su GitHub con dettagli errore

---

## 📖 Ordine di Lettura Consigliato

### Per Utenti Operativi (non tecnici)
1. Questo file (INDEX.md) per orientarsi
2. USAGE_GUIDE.md per il processo completo
3. QUICK_REFERENCE.md per i comandi essenziali

### Per Sviluppatori/Amministratori Database
1. IMPLEMENTATION_SUMMARY.md per overview tecnica
2. README.md per dettagli implementazione
3. Codice sorgente (import_soci_completo.sql, generate_import_sql.py)
4. QUICK_REFERENCE.md come riferimento rapido

### Per Chi Vuole Solo Fare Import Veloce
1. QUICK_REFERENCE.md
2. Eseguire comandi
3. Fine

---

**Versione Package**: 1.0  
**Data**: 2025-12-07  
**Compatibilità**: MySQL 5.6+, MySQL 8.x, MariaDB 10.x  
**Status**: ✅ Completo e testato
