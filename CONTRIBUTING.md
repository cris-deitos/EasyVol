# Contribuire a EasyVol

Grazie per il tuo interesse nel contribuire a EasyVol! Questo documento fornisce linee guida per contribuire al progetto.

## Come Contribuire

### Segnalazione Bug

Se trovi un bug:

1. Verifica che non sia già stato segnalato cercando tra le [Issues](https://github.com/cris-deitos/EasyVol/issues)
2. Se non esiste, crea una nuova issue includendo:
   - Descrizione chiara del problema
   - Passi per riprodurre il bug
   - Comportamento atteso vs comportamento attuale
   - Screenshot se applicabile
   - Versione PHP, MySQL, browser
   - Log di errori (se disponibili)

### Richiesta Funzionalità

Per suggerire nuove funzionalità:

1. Verifica che non sia già stata richiesta
2. Crea una issue con tag "enhancement"
3. Descrivi chiaramente:
   - Quale problema risolve
   - Come dovrebbe funzionare
   - Perché sarebbe utile

### Pull Request

1. **Fork il repository**
   ```bash
   git clone https://github.com/cris-deitos/EasyVol.git
   cd EasyVol
   ```

2. **Crea un branch per la tua funzionalità**
   ```bash
   git checkout -b feature/nome-funzionalita
   ```

3. **Sviluppa e testa**
   - Segui gli standard di codifica
   - Aggiungi commenti dove necessario
   - Testa accuratamente le modifiche

4. **Commit con messaggi descrittivi**
   ```bash
   git commit -m "Aggiunta: [descrizione breve]"
   ```

5. **Push e crea Pull Request**
   ```bash
   git push origin feature/nome-funzionalita
   ```

## Standard di Codifica

### PHP

- PSR-12 coding standard
- PHP 8.4+ features
- Type hints dove possibile
- DocBlocks per classi e metodi pubblici

```php
<?php
namespace EasyVol\Controllers;

/**
 * Controller di esempio
 */
class ExampleController {
    /**
     * Metodo di esempio
     * 
     * @param int $id ID dell'elemento
     * @return array Dati dell'elemento
     */
    public function get(int $id): array {
        // Implementazione
    }
}
```

### Database

- Prepared statements sempre
- Transazioni per operazioni multiple
- Indici su colonne ricerca frequente
- Nomi tabelle in inglese, snake_case

### HTML/CSS

- Semantico e accessibile
- Bootstrap 5 per layout
- Responsive design
- Commenti per sezioni complesse

### JavaScript

- ES6+ syntax
- Commenti JSDoc per funzioni
- Validazione lato client
- Event delegation dove possibile

### Sicurezza

- Validazione input server-side
- Sanitizzazione output
- CSRF protection
- XSS prevention
- SQL injection prevention (prepared statements)

## Struttura Commit

Usa messaggi commit chiari e descrittivi:

- `Aggiunta: [nuova funzionalità]`
- `Fix: [descrizione bug risolto]`
- `Miglioramento: [ottimizzazione]`
- `Docs: [aggiornamento documentazione]`
- `Refactor: [refactoring codice]`
- `Test: [aggiunta test]`

Esempi:
```
Aggiunta: Sistema di notifiche push
Fix: Correzione calcolo ore interventi
Miglioramento: Ottimizzazione query membri
Docs: Aggiornamento guida installazione
```

## Testing

Prima di inviare una PR:

1. Testa tutte le funzionalità modificate
2. Verifica che non ci siano errori PHP
3. Testa su diversi browser (Chrome, Firefox, Safari)
4. Testa responsive design
5. Verifica che non ci siano regressioni

## Documentazione

- Aggiorna README.md se necessario
- Aggiorna IMPLEMENTATION_GUIDE.md per nuovi moduli
- Aggiungi commenti in-line per codice complesso
- Crea guide utente per nuove funzionalità

## Processo di Review

1. Un maintainer revisionerà la tua PR
2. Potrebbero essere richieste modifiche
3. Una volta approvata, verrà merged
4. La tua contribuzione sarà creditata

## Riconoscimenti

Tutti i contributori saranno riconosciuti nel file CONTRIBUTORS.md

## Domande?

Hai domande? Puoi:
- Aprire una issue
- Contattare i maintainer
- Consultare la documentazione

## Codice di Condotta

### Il Nostro Impegno

Ci impegniamo a rendere la partecipazione al nostro progetto un'esperienza libera da molestie per tutti, indipendentemente da età, dimensioni corporee, disabilità visibile o invisibile, etnia, caratteristiche sessuali, identità ed espressione di genere, livello di esperienza, istruzione, status socio-economico, nazionalità, aspetto personale, razza, religione o identità e orientamento sessuale.

### I Nostri Standard

Esempi di comportamenti che contribuiscono a creare un ambiente positivo:
- Usare un linguaggio accogliente e inclusivo
- Rispettare punti di vista ed esperienze diverse
- Accettare con grazia le critiche costruttive
- Concentrarsi su ciò che è meglio per la community
- Mostrare empatia verso gli altri membri

Esempi di comportamenti inaccettabili:
- Uso di linguaggio o immagini sessualizzate
- Trolling, commenti insultanti/denigratori
- Molestie pubbliche o private
- Pubblicazione di informazioni private altrui
- Altro comportamento considerato inappropriato

### Applicazione

I casi di comportamento abusivo, molesto o altrimenti inaccettabile possono essere segnalati contattando i maintainer del progetto. Tutte le segnalazioni saranno revisionate e investigate.

## Licenza

Contribuendo a EasyVol, accetti che i tuoi contributi saranno rilasciati sotto la licenza MIT.

---

Grazie per aver dedicato tempo a contribuire a EasyVol! 🙏
