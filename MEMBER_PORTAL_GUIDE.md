# Portale Soci - Guida Utente

## Panoramica

Il **Portale Soci** è una funzionalità pubblica che permette ai soci maggiorenni e attivi di accedere e aggiornare autonomamente i propri dati personali nel sistema EasyVol.

## Prerequisiti

Per accedere al Portale Soci è necessario:

1. **Essere un socio maggiorenne** (18 anni o più)
2. **Avere stato "Attivo"** nel sistema
3. **Avere un indirizzo email registrato** nel proprio profilo
4. **Conoscere la propria matricola** e il proprio cognome

## Come Accedere

### Step 1: Accesso alla Pagina di Verifica

1. Vai su `http://tuosito.com/public/member_portal_verify.php`
2. Oppure clicca sul link "Portale Soci" dalla pagina di login

### Step 2: Verifica Identità

1. Inserisci la tua **Matricola** (es: 001, 042, ecc.)
2. Inserisci il tuo **Cognome**
3. Clicca su "Procedi"

Se i dati sono corretti e sei un socio attivo maggiorenne, riceverai un codice di verifica via email.

### Step 3: Verifica Codice Email

1. Controlla la tua casella email (anche spam/posta indesiderata)
2. Trova l'email con oggetto "Codice di Verifica - [Nome Associazione]"
3. Copia il codice di 8 caratteri dalla email
4. Inserisci il codice nella pagina di verifica
5. Clicca su "Verifica Codice"

**Note:**
- Il codice scade dopo **15 minuti**
- Puoi richiedere un nuovo codice cliccando su "Invia di nuovo"

### Step 4: Visualizza e Aggiorna i Tuoi Dati

Una volta verificato il codice, accederai alla pagina dei tuoi dati personali.

## Dati Visualizzabili e Modificabili

### Dati Non Modificabili (Solo Visualizzazione)

I seguenti dati **non possono essere modificati** direttamente dal portale. In caso di errori, contatta la Segreteria dell'Associazione:

- Matricola
- Nome e Cognome
- Codice Fiscale
- Data di Nascita
- Luogo di Nascita
- Stato Socio
- Tipo Socio
- Tipo Lavoratore
- Titolo di Studio

### Dati Modificabili

Puoi aggiungere, modificare o rimuovere i seguenti dati:

#### 1. Recapiti
- Cellulare
- Telefono Fisso
- Email
- PEC (Posta Elettronica Certificata)

**Come fare:**
- Clicca su "Aggiungi Recapito" per aggiungere un nuovo contatto
- Seleziona il tipo di recapito
- Inserisci il valore (numero di telefono o indirizzo email)
- Clicca sull'icona del cestino per rimuovere un recapito

#### 2. Indirizzi
- Residenza (Via, Numero, Città, Provincia, CAP)
- Domicilio (se diverso dalla residenza)

**Come fare:**
- Compila i campi dell'indirizzo di residenza
- Se il domicilio è diverso dalla residenza, compila anche quella sezione
- Lascia vuoti i campi del domicilio se coincide con la residenza

#### 3. Corsi
- Nome del corso
- Data di completamento
- Data di scadenza (se applicabile)
- Note

**Come fare:**
- Clicca su "Aggiungi Corso" per aggiungere un nuovo corso
- Inserisci il nome del corso (es: "BLSD", "Antincendio", "Primo Soccorso")
- Inserisci le date di completamento e scadenza
- Aggiungi eventuali note
- Clicca sull'icona del cestino per rimuovere un corso

#### 4. Patenti
- Tipo di patente (es: B, C, Nautica, ecc.)
- Numero patente
- Data di rilascio
- Data di scadenza
- Note

**Come fare:**
- Clicca su "Aggiungi Patente" per aggiungere una nuova patente
- Inserisci il tipo di patente
- Compila i campi con numero, date e note
- Clicca sull'icona del cestino per rimuovere una patente

#### 5. Info Alimentari
- Tipo (Vegano, Vegetariano, Allergie, Intolleranze, Patologie)
- Descrizione dettagliata

**Come fare:**
- Clicca su "Aggiungi Informazione" per aggiungere una nuova informazione alimentare
- Seleziona il tipo dal menu a tendina
- Descrivi l'informazione (es: "Celiachia", "Lattosio", "Allergia ai crostacei")
- Clicca sull'icona del cestino per rimuovere un'informazione

**Importante:** Queste informazioni sono utili per l'organizzazione di eventi e attività che prevedono pasti.

#### 6. Disponibilità Territoriale
- Comunale
- Provinciale
- Regionale
- Nazionale
- Internazionale

**Come fare:**
- Seleziona le caselle corrispondenti ai livelli territoriali per cui sei disponibile
- Aggiungi eventuali note per ogni livello
- Deseleziona le caselle per indicare la non disponibilità

## Salvataggio delle Modifiche

1. Dopo aver completato le modifiche, scorri fino in fondo alla pagina
2. Clicca sul pulsante **"Salva Modifiche"**
3. Conferma l'operazione quando richiesto
4. Riceverai una **conferma via email** con il riepilogo delle modifiche effettuate
5. Una copia della conferma verrà inviata anche alla Segreteria dell'Associazione

## Sicurezza

- Ogni accesso al portale è **tracciato e registrato** nel sistema
- I codici di verifica sono **temporanei** e scadono dopo 15 minuti
- Dopo l'uso, i codici vengono **invalidati** automaticamente
- Tutte le modifiche sono **registrate** con data, ora e dettagli
- La sessione è **protetta** con token CSRF per prevenire attacchi

## Domande Frequenti (FAQ)

### Non riesco ad accedere, perché?

Possibili motivi:
- La matricola o il cognome non sono corretti
- Non sei un socio attivo (verifica il tuo stato con la Segreteria)
- Sei minorenne (il portale è riservato ai maggiorenni)
- Non hai un indirizzo email registrato (contatta la Segreteria)

### Non ho ricevuto il codice via email

1. Controlla la cartella **spam** o **posta indesiderata**
2. Verifica che l'indirizzo email sia corretto (contatta la Segreteria se necessario)
3. Attendi qualche minuto (l'invio potrebbe richiedere tempo)
4. Clicca su "Invia di nuovo" per richiedere un nuovo codice

### Il codice è scaduto

I codici scadono dopo 15 minuti per motivi di sicurezza. Torna alla pagina di verifica del codice e clicca su "Invia di nuovo" per ricevere un nuovo codice.

### Ho trovato errori nei dati non modificabili

I dati anagrafici base (nome, cognome, codice fiscale, data di nascita, ecc.) possono essere modificati solo dalla Segreteria. Contatta la Segreteria dell'Associazione per segnalare l'errore.

### Ho problemi tecnici con il portale

Contatta la Segreteria dell'Associazione o l'amministratore del sistema per assistenza tecnica.

## Supporto

Per qualsiasi domanda, problema o segnalazione:

- **Email:** Contatta la Segreteria dell'Associazione
- **Telefono:** Usa i recapiti forniti dall'Associazione
- **Di persona:** Recati presso la sede durante gli orari di apertura

---

**EasyVol** - Sistema Gestionale per Associazioni di Volontariato
