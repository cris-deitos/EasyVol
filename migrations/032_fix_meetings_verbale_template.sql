-- Migration: Fix meetings verbale (minutes) print template
-- Date: 2026-02-22
-- Description: Updates the "Verbale Riunione/Assemblea" print template to use
--   enriched variables produced by SimplePdfGenerator::prepareMeetingData():
--   - meeting_type_label / meeting_type_upper (human-readable type, e.g. "Consiglio Direttivo")
--   - location_type_label (formatted location type, e.g. "In presenza")
--   - start_time_hhmm / end_time_hhmm (HH:mm without seconds)
--   - convocator_full (Name Surname (Role) from DB lookup)
--   - full_name, role_label, attendance_label, delegated_to_full_name (enriched participant data)
--   - present_count, absent_count, delegated_count (correct aggregates)
--   - president_full_name, secretary_full_name (for signature block)
--   - voting_outcome_label (APPROVATO / NON APPROVATO / VOTAZIONE NON EFFETTUATA)
--   - Removed "Verbale generato il ..." footer
--   - Closing time now shows "del giorno {{meeting_date}}"

UPDATE `print_templates`
SET
  `html_content` = '<div class="verbale-container">
<div class="header">
<h2>{{association_name}}</h2>
<p class="subtitle">{{association_address}} - {{association_postal_code}} {{association_city}}<br>Codice Fiscale {{association_tax_code}}</p>
<h1>VERBALE DI {{meeting_type_upper}}</h1>
</div>

<div class="verbale-intro">
<p>L''anno <strong>{{meeting_year}}</strong>, il giorno <strong>{{meeting_day}}</strong> del mese di <strong>{{meeting_month}}</strong>,
alle ore <strong>{{start_time_hhmm}}</strong>, presso <strong>{{location}}</strong>{{#if location_address}} ({{location_address}}){{/if}},
si è riunita la <strong>{{meeting_type_label}}</strong>{{#if title}} avente ad oggetto: <em>{{title}}</em>{{/if}}.</p>

<p>La riunione è stata convocata da <strong>{{convocator_full}}</strong>.</p>

{{#if online_details}}
<p>Modalità di partecipazione: <strong>{{location_type_label}}</strong><br>
Dettagli connessione: {{online_details}}</p>
{{/if}}
</div>

<h2>Partecipanti</h2>
<table class="partecipanti-table">
    <thead>
        <tr>
            <th style="width: 30%;">Nome e Cognome</th>
            <th style="width: 20%;">Ruolo</th>
            <th style="width: 15%;">Presenza</th>
            <th style="width: 35%;">Delega a</th>
        </tr>
    </thead>
    <tbody>
        {{#each participants}}
        <tr>
            <td>{{full_name}}</td>
            <td>{{role_label}}</td>
            <td class="center">{{attendance_label}}</td>
            <td>{{delegated_to_full_name}}</td>
        </tr>
        {{/each}}
    </tbody>
</table>

<p class="riepilogo">Presenti: <strong>{{present_count}}</strong> | Assenti: <strong>{{absent_count}}</strong> | Deleghe: <strong>{{delegated_count}}</strong></p>

<h2>Ordine del Giorno</h2>
<ol class="odg-list">
    {{#each agenda}}
    <li>
        <div class="odg-item">
            <strong>{{subject}}</strong>
            {{#if description}}<p class="odg-desc">{{description}}</p>{{/if}}
        </div>
    </li>
    {{/each}}
</ol>

<h2>Svolgimento della Riunione</h2>
{{#each agenda}}
<div class="discussione-item">
    <h3><strong>Punto {{order_number}}</strong>: {{subject}}</h3>
    {{#if discussion}}
    <div class="discussion-text">{{discussion}}</div>
    {{else}}
    <div class="discussion-placeholder">[Discussione non registrata]</div>
    {{/if}}

    {{#if has_voting}}
    <div class="votazione">
        <p><strong>VOTAZIONE:</strong></p>
        <p>Votanti: {{voting_total}} | Favorevoli: {{voting_in_favor}} | Contrari: {{voting_against}} | Astenuti: {{voting_abstained}}</p>
        <p>Esito: <strong>{{voting_outcome_label}}</strong></p>
    </div>
    {{/if}}
</div>
{{/each}}

{{#if minutes_content}}
<h2>Verbale Dettagliato</h2>
<div class="minutes-content">
{{minutes_content}}
</div>
{{/if}}

<div class="chiusura">
<p>Non essendovi altro da discutere, la seduta viene sciolta alle ore <strong>{{end_time_hhmm}}</strong> del giorno <strong>{{meeting_date}}</strong>.</p>

<p>Del che è verbale.</p>
</div>

<div class="firme">
<table class="firme-table">
    <tr>
        <td class="firma-col">
            <p>Il Segretario</p>
            <div class="firma-line"></div>
            <p class="firma-nome">{{secretary_full_name}}</p>
        </td>
        <td class="firma-col">
            <p>Il Presidente</p>
            <div class="firma-line"></div>
            <p class="firma-nome">{{president_full_name}}</p>
        </td>
    </tr>
</table>
</div>
</div>',
  `css_content` = 'body {
    font-family: "Times New Roman", Georgia, serif;
    font-size: 11pt;
    margin: 0;
    padding: 0;
    line-height: 1.5;
}

@page {
    size: A4 portrait;
    margin: 15mm;
}

.verbale-container { padding: 5mm; }

.header {
    text-align: center;
    margin-bottom: 15px;
    border-bottom: 2px solid #333;
    padding-bottom: 10px;
}

.header h1 { margin: 10px 0; font-size: 16pt; color: #333; letter-spacing: 2px; }
.header h2 { margin: 0; font-size: 13pt; color: #333; }
.header .subtitle { margin: 5px 0 0 0; font-size: 9pt; color: #666; }

.verbale-intro {
    margin: 20px 0;
    text-align: justify;
}

h2 {
    font-size: 12pt;
    color: #333;
    border-bottom: 1px solid #999;
    padding-bottom: 3px;
    margin-top: 20px;
    margin-bottom: 10px;
}

h3 {
    font-size: 11pt;
    color: #444;
    margin-top: 15px;
    margin-bottom: 5px;
}

table.partecipanti-table { width: 100%; border-collapse: collapse; font-size: 10pt; margin-bottom: 10px; }
table.partecipanti-table thead { background-color: #e9ecef; }
table.partecipanti-table th { padding: 6px; text-align: left; border: 1px solid #ccc; font-weight: bold; }
table.partecipanti-table td { padding: 5px 6px; border: 1px solid #ccc; }
table.partecipanti-table tbody tr:nth-child(even) { background-color: #f9f9f9; }

.center { text-align: center; }
.riepilogo { font-size: 10pt; color: #555; margin-top: 5px; }

ol.odg-list { margin: 10px 0; padding-left: 25px; }
ol.odg-list li { margin-bottom: 8px; }
.odg-item { margin-left: 5px; }
.odg-desc { margin: 3px 0 0 0; font-size: 10pt; color: #555; font-style: italic; }

.discussione-item { margin-bottom: 15px; padding-left: 10px; border-left: 3px solid #e9ecef; }
.discussion-text { text-align: justify; margin: 5px 0; }
.discussion-placeholder { color: #999; font-style: italic; }

.votazione {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    padding: 8px;
    margin: 10px 0;
    font-size: 10pt;
}

.minutes-content {
    border: 1px solid #ccc;
    padding: 10px;
    background-color: #fafafa;
    text-align: justify;
}

.chiusura {
    margin-top: 25px;
    text-align: justify;
}

.firme { margin-top: 40px; }
.firme-table { width: 100%; }
.firma-col { width: 50%; text-align: center; vertical-align: bottom; padding: 20px; }
.firma-line { border-bottom: 1px solid #333; width: 80%; margin: 30px auto 5px auto; }
.firma-nome { font-size: 10pt; color: #333; }

@media print {
    body { margin: 0; }
    .verbale-container { page-break-inside: auto; }
    .discussione-item { page-break-inside: avoid; }
    .firme { page-break-inside: avoid; }
}'
WHERE `name` = 'Verbale Riunione/Assemblea'
  AND `entity_type` = 'meetings';
