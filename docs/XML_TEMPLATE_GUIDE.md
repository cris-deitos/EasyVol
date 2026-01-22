# Sistema Template XML - Guida Completa

Il nuovo sistema di template XML permette di creare e gestire template di stampa in modo strutturato, con editor visuale e anteprima live.

## Formati Supportati

Il sistema supporta **DUE formati XML**:

### 1. Formato Nuovo (EasyVol)
Template strutturati con elementi `<template>`, `<variable>`, `<loop>`, `<condition>`.
Esempio: `templates/example_xml_member_card.xml`

### 2. Formato Legacy (GestionaleWeb)
Template dal sistema precedente con elementi `<pdf>`, `<paragraph>`, `<image>`.
Variabili: `${nome}`, `${cognome}`, etc.
Esempio: `templates/legacy_libro_soci.xml`

**Il sistema rileva automaticamente il formato e usa il processore corretto!**

## Caratteristiche

- Editor XML con sintassi evidenziata (CodeMirror)
- Validazione in tempo reale
- Anteprima live con dati di esempio
- Supporto variabili, cicli e condizioni (formato nuovo)
- Supporto posizionamento assoluto/relativo (formato legacy)
- Formattazione automatica

## Formato Nuovo - Esempio

Vedi `templates/example_xml_member_card.xml` per un esempio completo.

### Elementi Disponibili

1. `<variable name="campo" />` - Inserisce campo dati
2. `<loop source="array">...</loop>` - Itera su array
3. `<condition test="campo">...</condition>` - Contenuto condizionale
4. `<section class="...">...</section>` - Contenitore con stile
5. `<table>...</table>` - Tabella HTML

## Formato Legacy - Esempio

Vedi `templates/legacy_libro_soci.xml` per un esempio completo dal sistema precedente.

### Elementi Supportati

1. `<paragraph>` - Testo con posizionamento assoluto/relativo
2. `<image>` - Immagini con posizionamento
3. Variabili: `${matricola}`, `${nome}`, `${cognome}`
4. Blocchi: `<!-- $BeginBlock -->` ... `<!-- $EndBlock -->`
5. Include: `<!-- $Include path -->`

### Mappatura Variabili Legacy

Il sistema mappa automaticamente le variabili del formato legacy ai campi del database:

- `${matricola}` → badge_number
- `${nome}` → first_name
- `${cognome}` → last_name
- `${data di nascita}` → birth_date
- `${luogo di nascita}` → birth_place
- `${codice fiscale}` → tax_code
- `${data iscrizione}` → member_since
- ... e molti altri

## Come Usare

1. **Creare template**: Template Stampe → "Nuovo Template XML"
2. **Modificare**: Editor XML con anteprima live
3. **Importare template esistenti**: Il formato legacy viene riconosciuto automaticamente
4. **Stampare**: Da scheda socio/mezzo/riunione → Menu Stampa → Seleziona template

## Migrazione da Sistema Precedente

I vostri template XML esistenti (formato GestionaleWeb) funzionano direttamente:

1. Caricate il file XML esistente nell'editor
2. Il sistema lo riconosce automaticamente come formato legacy
3. L'anteprima mostra come verrà renderizzato
4. Salvate e usate come qualsiasi altro template

Non è necessaria conversione manuale!
