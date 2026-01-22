# Sistema Template XML - Guida Completa

Il nuovo sistema di template XML permette di creare e gestire template di stampa in modo strutturato, con editor visuale e anteprima live.

## Caratteristiche

- Editor XML con sintassi evidenziata (CodeMirror)
- Validazione in tempo reale
- Anteprima live con dati di esempio
- Supporto variabili, cicli e condizioni
- Formattazione automatica

## Esempio Base

Vedi `templates/example_xml_member_card.xml` per un esempio completo.

## Elementi Disponibili

1. `<variable name="campo" />` - Inserisce campo dati
2. `<loop source="array">...</loop>` - Itera su array
3. `<condition test="campo">...</condition>` - Contenuto condizionale
4. `<section class="...">...</section>` - Contenitore con stile
5. `<table>...</table>` - Tabella HTML

## Come Usare

1. Template Stampe → Nuovo Template XML
2. Modifica codice XML
3. Clicca "Aggiorna" per anteprima
4. Salva template
5. Usa da schede soci/mezzi/riunioni
