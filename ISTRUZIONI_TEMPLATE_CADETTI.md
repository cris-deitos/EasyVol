# Istruzioni per Caricare i Template di Stampa per Soci Minorenni

## Per installazioni nuove o aggiornamenti

Se i template di stampa per i soci minorenni (cadetti) non sono presenti nel database, eseguire il seguente comando SQL:

### Via MySQL command line:
```bash
mysql -u username -p database_name < seed_junior_members_print_templates.sql
```

### Via phpMyAdmin o altro strumento di gestione database:
1. Aprire phpMyAdmin
2. Selezionare il database EasyVol
3. Andare alla scheda "SQL" o "Importa"
4. Selezionare il file `seed_junior_members_print_templates.sql`
5. Eseguire l'importazione

## Template Inclusi

Questo file aggiunge 3 nuovi template per la gestione dei soci minorenni:

1. **Libro Soci Cadetti** (ID: 11)
   - Elenco completo di tutti i soci minorenni con campi principali
   - Formato: A4 orizzontale
   - Tipo: Lista

2. **Elenco Contatti Cadetti** (ID: 12)
   - Elenco con contatti del tutore per comunicazioni rapide
   - Include: nome cadetto, tutore, telefono, email
   - Formato: A4 orizzontale
   - Tipo: Lista

3. **Foglio Firma Cadetti** (ID: 13)
   - Foglio presenze per attività o eventi
   - Include: numero, matricola, nome, spazio per firma
   - Formato: A4 verticale
   - Tipo: Lista

## Note

- I template sono già integrati nella pagina `junior_members.php`
- Accessibili tramite il menu "Stampa" nella pagina di gestione soci minorenni
- I template rispettano i filtri applicati nella pagina (status, ricerca, ecc.)
