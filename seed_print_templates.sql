-- =============================================
-- SEED FILE: Default Print Templates for EasyVol
-- =============================================
-- This file contains all default print templates for a fresh installation
-- Run this file if print templates are missing from the database
--
-- Usage: mysql -u username -p database_name < seed_print_templates.sql
-- Or import via phpMyAdmin or other database management tool
-- =============================================
-- Updated for simplified schema (migration 023)

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- =============================================
-- MEMBERS TEMPLATES
-- =============================================

-- 1. Tessera Socio (single)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `is_active`, `is_default`
) VALUES (
    'Tessera Socio',
    'Tessera associativa per singolo socio',
    'single', 'single', 'members',
    '<div style="width: 8.5cm; height: 5.4cm; border: 2px solid #333; padding: 0.3cm; display: flex; flex-direction: column;">
        <div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 0.2cm; margin-bottom: 0.2cm;">
            <h3 style="margin: 0; font-size: 14pt;">TESSERA ASSOCIATIVA</h3>
            <p style="margin: 0; font-size: 9pt;">{{association_name}}</p>
        </div>
        
        <table style="width: 100%; font-size: 9pt;">
            <tr>
                <td style="width: 40%; padding: 0.1cm;"><strong>Nome:</strong></td>
                <td style="padding: 0.1cm;">{{first_name}} {{last_name}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Matricola:</strong></td>
                <td style="padding: 0.1cm;">{{registration_number}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Data Nasc.:</strong></td>
                <td style="padding: 0.1cm;">{{birth_date}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Tipo:</strong></td>
                <td style="padding: 0.1cm;">{{member_type}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Valida fino al:</strong></td>
                <td style="padding: 0.1cm;">31/12/{{current_year}}</td>
            </tr>
        </table>
        
        <div style="margin-top: auto; text-align: center; font-size: 7pt; color: #666;">
            Emessa il {{current_date}}
        </div>
    </div>',
    'body { font-family: Arial, sans-serif; }',
    'A4', 'portrait', 1, 1
);

-- 2. Scheda Socio (single - with related data loops)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `is_active`, `is_default`
) VALUES (
    'Scheda Socio',
    'Scheda completa del socio con tutti i dati',
    'single', 'single', 'members',
    '<div style="margin: 1cm;">
        <h1 style="border-bottom: 3px solid #333; padding-bottom: 0.3cm;">SCHEDA SOCIO</h1>
        
        <h2 style="margin-top: 1cm;">Dati Anagrafici</h2>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 1cm;">
            <tr>
                <td style="width: 30%; padding: 0.2cm; font-weight: bold;">Matricola:</td>
                <td style="padding: 0.2cm;">{{registration_number}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Nome e Cognome:</td>
                <td style="padding: 0.2cm;">{{first_name}} {{last_name}}</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Data di Nascita:</td>
                <td style="padding: 0.2cm;">{{birth_date}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Luogo di Nascita:</td>
                <td style="padding: 0.2cm;">{{birth_place}} ({{birth_province}})</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Codice Fiscale:</td>
                <td style="padding: 0.2cm;">{{tax_code}}</td>
            </tr>
        </table>

        <h2>Informazioni Associative</h2>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 1cm;">
            <tr>
                <td style="width: 30%; padding: 0.2cm; font-weight: bold;">Tipo Socio:</td>
                <td style="padding: 0.2cm;">{{member_type}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Stato:</td>
                <td style="padding: 0.2cm;">{{member_status}}</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Data Iscrizione:</td>
                <td style="padding: 0.2cm;">{{registration_date}}</td>
            </tr>
        </table>

        <h2>Contatti</h2>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-bottom: 1cm;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; text-align: left;">Tipo</th>
                    <th style="padding: 0.3cm; text-align: left;">Valore</th>
                </tr>
            </thead>
            <tbody>
                {{#each contacts}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{contact_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{value}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>

        <h2>Patenti</h2>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-bottom: 1cm;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; text-align: left;">Tipo</th>
                    <th style="padding: 0.3cm; text-align: left;">Numero</th>
                    <th style="padding: 0.3cm; text-align: left;">Scadenza</th>
                </tr>
            </thead>
            <tbody>
                {{#each licenses}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{license_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{license_number}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{expiry_date}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>',
    'body { font-family: Arial, sans-serif; font-size: 11pt; }
    h1 { font-size: 18pt; color: #333; }
    h2 { font-size: 14pt; color: #555; margin-top: 0.8cm; margin-bottom: 0.3cm; }',
    'A4', 'portrait', 1, 0
);

-- 3. Libro Soci (list)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `is_active`, `is_default`
) VALUES (
    'Libro Soci',
    'Elenco completo di tutti i soci',
    'list', 'all', 'members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">LIBRO SOCI</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 9pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000;">Matr.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Cognome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Nome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Data Nasc.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Codice Fiscale</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Tipo</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Stato</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{registration_number}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{last_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{first_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{birth_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{tax_code}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{member_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{member_status}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
        <p style="margin-top: 1cm; font-size: 10pt;">Totale soci: {{total}} - Stampato il {{current_date}}</p>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    'A4', 'landscape', 1, 0
);

-- =============================================
-- VEHICLES TEMPLATES
-- =============================================

-- 4. Elenco Mezzi (list)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `is_active`, `is_default`
) VALUES (
    'Elenco Mezzi',
    'Lista completa dei mezzi',
    'list', 'all', 'vehicles',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ELENCO MEZZI</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000;">Targa</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Tipo</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Marca/Modello</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Anno</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Stato</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{license_plate}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{vehicle_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{brand}} {{model}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{year}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{status}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
        <p style="margin-top: 1cm; font-size: 10pt;">Totale mezzi: {{total}} - Stampato il {{current_date}}</p>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    'A4', 'portrait', 1, 1
);

-- 5. Scheda Mezzo (single)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `is_active`, `is_default`
) VALUES (
    'Scheda Mezzo',
    'Scheda completa del mezzo con storico manutenzioni',
    'single', 'single', 'vehicles',
    '<div style="margin: 1cm;">
        <h1 style="border-bottom: 3px solid #333; padding-bottom: 0.3cm;">SCHEDA TECNICA MEZZO</h1>
        
        <h2 style="margin-top: 1cm;">Dati Identificativi</h2>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 1cm;">
            <tr>
                <td style="width: 30%; padding: 0.2cm; font-weight: bold;">Targa:</td>
                <td style="padding: 0.2cm;">{{license_plate}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Tipo Mezzo:</td>
                <td style="padding: 0.2cm;">{{vehicle_type}}</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Marca e Modello:</td>
                <td style="padding: 0.2cm;">{{brand}} {{model}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Anno:</td>
                <td style="padding: 0.2cm;">{{year}}</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Stato:</td>
                <td style="padding: 0.2cm;">{{status}}</td>
            </tr>
        </table>

        <h2>Storico Manutenzioni</h2>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; text-align: left;">Data</th>
                    <th style="padding: 0.3cm; text-align: left;">Tipo</th>
                    <th style="padding: 0.3cm; text-align: left;">Descrizione</th>
                    <th style="padding: 0.3cm; text-align: left;">Costo</th>
                </tr>
            </thead>
            <tbody>
                {{#each maintenance}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{maintenance_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{maintenance_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{description}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">€ {{cost}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>',
    'body { font-family: Arial, sans-serif; font-size: 11pt; }
    h1 { font-size: 18pt; color: #333; }
    h2 { font-size: 14pt; color: #555; margin-top: 0.8cm; margin-bottom: 0.3cm; }',
    'A4', 'portrait', 1, 0
);

-- =============================================
-- EVENTS TEMPLATES
-- =============================================

-- 6. Elenco Eventi (list)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `is_active`, `is_default`
) VALUES (
    'Elenco Eventi',
    'Lista eventi con tipologia, date e orari',
    'list', 'all', 'events',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ELENCO EVENTI</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 10pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000;">Tipo</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Titolo</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Data Inizio</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Data Fine</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Stato</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{event_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{title}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{start_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{end_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{status}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
        <p style="margin-top: 1cm; font-size: 10pt;">Totale eventi: {{total}} - Stampato il {{current_date}}</p>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    'A4', 'landscape', 1, 1
);

-- =============================================
-- JUNIOR MEMBERS TEMPLATES
-- =============================================

-- 7. Libro Soci Cadetti (list)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `is_active`, `is_default`
) VALUES (
    'Libro Soci Cadetti',
    'Elenco completo di tutti i soci minorenni',
    'list', 'all', 'junior_members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">LIBRO SOCI MINORENNI</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 9pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000;">Cognome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Nome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Data Nasc.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Tutore</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Telefono</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Data Iscr.</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{last_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{first_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{birth_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{guardian_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{guardian_phone}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{registration_date}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
        <p style="margin-top: 1cm; font-size: 10pt;">Totale: {{total}} - Stampato il {{current_date}}</p>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    'A4', 'landscape', 1, 1
);

-- =============================================
-- MEETINGS TEMPLATES
-- =============================================

-- 8. Verbale Riunione (single)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `is_active`, `is_default`
) VALUES (
    'Verbale di Riunione',
    'Verbale ufficiale della riunione con partecipanti',
    'single', 'single', 'meetings',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">VERBALE DI RIUNIONE</h1>
        
        <div style="margin-top: 1cm;">
            <p><strong>Data:</strong> {{meeting_date}}</p>
            <p><strong>Tipo:</strong> {{meeting_type}}</p>
            <p><strong>Luogo:</strong> {{location}}</p>
        </div>

        <h2 style="margin-top: 1cm;">Partecipanti</h2>
        <ul>
            {{#each participants}}
            <li>{{member_name}} - {{role}}</li>
            {{/each}}
        </ul>

        <h2 style="margin-top: 1cm;">Ordine del Giorno</h2>
        <ol>
            {{#each agenda}}
            <li style="margin-bottom: 0.5cm;">
                <strong>{{title}}</strong>
                <p style="margin-left: 1cm;">{{description}}</p>
            </li>
            {{/each}}
        </ol>

        <h2 style="margin-top: 1cm;">Resoconto</h2>
        <div style="border: 1px solid #ccc; padding: 0.5cm; min-height: 5cm; background: #f9f9f9;">
            {{notes}}
        </div>

        <div style="margin-top: 2cm;">
            <table style="width: 100%;">
                <tr>
                    <td style="width: 50%;">
                        <p><strong>Il Segretario</strong></p>
                        <p style="margin-top: 2cm;">_________________________</p>
                    </td>
                    <td style="width: 50%; text-align: right;">
                        <p><strong>Il Presidente</strong></p>
                        <p style="margin-top: 2cm;">_________________________</p>
                    </td>
                </tr>
            </table>
        </div>
    </div>',
    'body { font-family: "Times New Roman", serif; font-size: 12pt; line-height: 1.5; }
    h1 { font-size: 18pt; }
    h2 { font-size: 14pt; color: #333; }',
    'A4', 'portrait', 1, 1
);


-- =============================================
-- ADDITIONAL MEMBERS TEMPLATES
-- =============================================

-- 9. Fogli Firma Assemblee Soci (list - landscape)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `is_active`, `is_default`
) VALUES (
    'Fogli Firma Assemblee',
    'Foglio firme per assemblee soci maggiorenni (solo soci attivi)',
    'list', 'filtered', 'members',
    '<div class="header">
<h2>{{association_name}}</h2>
<p class="subtitle">{{association_address}} - {{association_postal_code}} {{association_city}}<br>Codice Fiscale {{association_tax_code}}</p>
<h1>FOGLIO FIRME ASSEMBLEA</h1>
<p class="meeting-info">Data Assemblea: __________________ Tipo: __________________</p>
</div>
<table class="firma-table">
    <thead>
        <tr>
            <th class="center" style="width: 6%;">Matr.</th>
            <th class="center" style="width: 12%;">Nome</th>
            <th class="center" style="width: 14%;">Cognome</th>
            <th class="center" style="width: 8%;">Stato</th>
            <th class="center" style="width: 10%;">Quota {{current_year}}</th>
            <th class="center" style="width: 25%;">Firma</th>
            <th class="center" style="width: 25%;">Delega</th>
        </tr>
    </thead>
    <tbody>
        {{#each records}}
        <tr>
            <td class="center">{{registration_number}}</td>
            <td class="center">{{first_name}}</td>
            <td class="center">{{last_name}}</td>
            <td class="center status-{{member_status}}">{{member_status}}</td>
            <td class="center">{{#if fee_paid}}✓{{else}}✗{{/if}}</td>
            <td class="firma-cell"></td>
            <td class="delega-cell"></td>
        </tr>
        {{/each}}
    </tbody>
</table>
<div class="footer">
<p>Totale Soci Presenti: ______ di {{total}} - Generato il {{current_date}}</p>
</div>',
    'body {
    font-family: Arial, sans-serif;
    font-size: 8pt;
    margin: 0;
    padding: 0;
}

@page {
    size: A4 landscape;
    margin: 10mm;
}

.header {
    text-align: center;
    margin-bottom: 5px;
    border-bottom: 2px solid #333;
    padding-bottom: 5px;
}

.header h1 {
    margin: 0;
    font-size: 15pt;
    color: #333;
}

.header h2 {
    margin: 0;
    font-size: 13pt;
    color: #333;
}

.header .subtitle {
    margin: 5px 0 0 0;
    font-size: 9pt;
    color: #666;
}

.meeting-info {
    margin-top: 10px;
    font-size: 10pt;
}

table.firma-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 7pt;
    margin-bottom: 10px;
}

table.firma-table thead {
    background-color: #333;
    color: white;
}

table.firma-table th {
    padding: 6px 4px;
    text-align: left;
    font-weight: bold;
    border: 1px solid #333;
    font-size: 7.5pt;
}

.center {
    text-align: center;
}

table.firma-table td {
    padding: 5px 4px;
    border: 1px solid #ccc;
    vertical-align: middle;
}

table.firma-table tbody tr:nth-child(even) {
    background-color: #f9f9f9;
}

.firma-cell, .delega-cell {
    height: 25px;
    background-color: #fafafa;
}

.status-attivo {
    color: #28a745;
    font-weight: bold;
}

.status-sospeso, .status-in_aspettativa, .status-in_congedo {
    color: #ffc107;
    font-weight: bold;
}

.status-dimesso, .status-decaduto, .status-escluso {
    color: #dc3545;
    font-weight: bold;
}

.footer {
    margin-top: 20px;
    text-align: center;
    font-size: 8pt;
    color: #666;
    padding: 5px 0;
    border-top: 1px solid #ccc;
}

@media print {
    body { margin: 0; }
    .header { page-break-after: avoid; }
    table.firma-table { page-break-inside: auto; }
    table.firma-table tr { page-break-inside: avoid; page-break-after: auto; }
    table.firma-table thead { display: table-header-group; }
}',
    'A4', 'landscape', 1, 0
);

-- 10. Elenco Soci con Email e Cellulare (list - portrait)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `is_active`, `is_default`
) VALUES (
    'Elenco Soci - Email e Cellulare',
    'Elenco soci con contatti email e cellulare (solo soci attivi)',
    'list', 'filtered', 'members',
    '<div class="header">
<h2>{{association_name}}</h2>
<p class="subtitle">{{association_address}} - {{association_postal_code}} {{association_city}}<br>Codice Fiscale {{association_tax_code}}</p>
<h1>ELENCO SOCI - CONTATTI</h1>
</div>
<table class="contact-table">
    <thead>
        <tr>
            <th class="center" style="width: 10%;">Matr.</th>
            <th class="center" style="width: 18%;">Nome</th>
            <th class="center" style="width: 18%;">Cognome</th>
            <th class="center" style="width: 30%;">Email</th>
            <th class="center" style="width: 24%;">Cellulare</th>
        </tr>
    </thead>
    <tbody>
        {{#each records}}
        <tr>
            <td class="center">{{registration_number}}</td>
            <td>{{first_name}}</td>
            <td>{{last_name}}</td>
            <td>{{email}}</td>
            <td class="center">{{cellulare}}</td>
        </tr>
        {{/each}}
    </tbody>
</table>
<div class="footer">
<p>Totale Soci: {{total}} - Generato il {{current_date}}</p>
</div>',
    'body {
    font-family: Arial, sans-serif;
    font-size: 9pt;
    margin: 0;
    padding: 0;
}

@page {
    size: A4 portrait;
    margin: 10mm;
}

.header {
    text-align: center;
    margin-bottom: 10px;
    border-bottom: 2px solid #333;
    padding-bottom: 5px;
}

.header h1 {
    margin: 0;
    font-size: 14pt;
    color: #333;
}

.header h2 {
    margin: 0;
    font-size: 12pt;
    color: #333;
}

.header .subtitle {
    margin: 5px 0 0 0;
    font-size: 8pt;
    color: #666;
}

table.contact-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 8pt;
    margin-bottom: 10px;
}

table.contact-table thead {
    background-color: #333;
    color: white;
}

table.contact-table th {
    padding: 6px 4px;
    text-align: left;
    font-weight: bold;
    border: 1px solid #333;
    font-size: 8pt;
}

.center {
    text-align: center;
}

table.contact-table td {
    padding: 4px;
    border: 1px solid #ccc;
    vertical-align: top;
}

table.contact-table tbody tr:nth-child(even) {
    background-color: #f9f9f9;
}

.footer {
    margin-top: 15px;
    text-align: center;
    font-size: 8pt;
    color: #666;
    padding: 5px 0;
    border-top: 1px solid #ccc;
}

@media print {
    body { margin: 0; }
    .header { page-break-after: avoid; }
    table.contact-table { page-break-inside: auto; }
    table.contact-table tr { page-break-inside: avoid; page-break-after: auto; }
    table.contact-table thead { display: table-header-group; }
}',
    'A4', 'portrait', 1, 0
);

-- 11. Elenco Soci con Codice Fiscale (list - portrait)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `is_active`, `is_default`
) VALUES (
    'Elenco Soci - Codice Fiscale',
    'Elenco soci con codice fiscale (solo soci attivi)',
    'list', 'filtered', 'members',
    '<div class="header">
<h2>{{association_name}}</h2>
<p class="subtitle">{{association_address}} - {{association_postal_code}} {{association_city}}<br>Codice Fiscale {{association_tax_code}}</p>
<h1>ELENCO SOCI - CODICI FISCALI</h1>
</div>
<table class="cf-table">
    <thead>
        <tr>
            <th class="center" style="width: 12%;">Matr.</th>
            <th class="center" style="width: 25%;">Nome</th>
            <th class="center" style="width: 25%;">Cognome</th>
            <th class="center" style="width: 38%;">Codice Fiscale</th>
        </tr>
    </thead>
    <tbody>
        {{#each records}}
        <tr>
            <td class="center">{{registration_number}}</td>
            <td>{{first_name}}</td>
            <td>{{last_name}}</td>
            <td class="center mono">{{tax_code}}</td>
        </tr>
        {{/each}}
    </tbody>
</table>
<div class="footer">
<p>Totale Soci: {{total}} - Generato il {{current_date}}</p>
</div>',
    'body {
    font-family: Arial, sans-serif;
    font-size: 9pt;
    margin: 0;
    padding: 0;
}

@page {
    size: A4 portrait;
    margin: 10mm;
}

.header {
    text-align: center;
    margin-bottom: 10px;
    border-bottom: 2px solid #333;
    padding-bottom: 5px;
}

.header h1 {
    margin: 0;
    font-size: 14pt;
    color: #333;
}

.header h2 {
    margin: 0;
    font-size: 12pt;
    color: #333;
}

.header .subtitle {
    margin: 5px 0 0 0;
    font-size: 8pt;
    color: #666;
}

table.cf-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 9pt;
    margin-bottom: 10px;
}

table.cf-table thead {
    background-color: #333;
    color: white;
}

table.cf-table th {
    padding: 6px 4px;
    text-align: left;
    font-weight: bold;
    border: 1px solid #333;
    font-size: 9pt;
}

.center {
    text-align: center;
}

.mono {
    font-family: "Courier New", monospace;
    letter-spacing: 1px;
}

table.cf-table td {
    padding: 5px 4px;
    border: 1px solid #ccc;
    vertical-align: top;
}

table.cf-table tbody tr:nth-child(even) {
    background-color: #f9f9f9;
}

.footer {
    margin-top: 15px;
    text-align: center;
    font-size: 8pt;
    color: #666;
    padding: 5px 0;
    border-top: 1px solid #ccc;
}

@media print {
    body { margin: 0; }
    .header { page-break-after: avoid; }
    table.cf-table { page-break-inside: auto; }
    table.cf-table tr { page-break-inside: avoid; page-break-after: auto; }
    table.cf-table thead { display: table-header-group; }
}',
    'A4', 'portrait', 1, 0
);

-- 12. Elenco Soci con Residenza e Domicilio (list - landscape)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `is_active`, `is_default`
) VALUES (
    'Elenco Soci - Residenza e Domicilio',
    'Elenco soci con indirizzi di residenza e domicilio (solo soci attivi)',
    'list', 'filtered', 'members',
    '<div class="header">
<h2>{{association_name}}</h2>
<p class="subtitle">{{association_address}} - {{association_postal_code}} {{association_city}}<br>Codice Fiscale {{association_tax_code}}</p>
<h1>ELENCO SOCI - RESIDENZA E DOMICILIO</h1>
</div>
<table class="address-table">
    <thead>
        <tr>
            <th class="center" style="width: 6%;">Matr.</th>
            <th class="center" style="width: 12%;">Nome</th>
            <th class="center" style="width: 12%;">Cognome</th>
            <th class="center" style="width: 35%;">Residenza</th>
            <th class="center" style="width: 35%;">Domicilio</th>
        </tr>
    </thead>
    <tbody>
        {{#each records}}
        <tr>
            <td class="center">{{registration_number}}</td>
            <td>{{first_name}}</td>
            <td>{{last_name}}</td>
            <td>{{residenza_street}} {{residenza_number}}, {{residenza_cap}} {{residenza_city}} ({{residenza_province}})</td>
            <td>{{domicilio_street}} {{domicilio_number}}, {{domicilio_cap}} {{domicilio_city}} ({{domicilio_province}})</td>
        </tr>
        {{/each}}
    </tbody>
</table>
<div class="footer">
<p>Totale Soci: {{total}} - Generato il {{current_date}}</p>
</div>',
    'body {
    font-family: Arial, sans-serif;
    font-size: 8pt;
    margin: 0;
    padding: 0;
}

@page {
    size: A4 landscape;
    margin: 10mm;
}

.header {
    text-align: center;
    margin-bottom: 5px;
    border-bottom: 2px solid #333;
    padding-bottom: 5px;
}

.header h1 {
    margin: 0;
    font-size: 14pt;
    color: #333;
}

.header h2 {
    margin: 0;
    font-size: 12pt;
    color: #333;
}

.header .subtitle {
    margin: 5px 0 0 0;
    font-size: 8pt;
    color: #666;
}

table.address-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 7pt;
    margin-bottom: 10px;
}

table.address-table thead {
    background-color: #333;
    color: white;
}

table.address-table th {
    padding: 5px 3px;
    text-align: left;
    font-weight: bold;
    border: 1px solid #333;
    font-size: 7pt;
}

.center {
    text-align: center;
}

table.address-table td {
    padding: 4px 3px;
    border: 1px solid #ccc;
    vertical-align: top;
}

table.address-table tbody tr:nth-child(even) {
    background-color: #f9f9f9;
}

.footer {
    margin-top: 15px;
    text-align: center;
    font-size: 8pt;
    color: #666;
    padding: 5px 0;
    border-top: 1px solid #ccc;
}

@media print {
    body { margin: 0; }
    .header { page-break-after: avoid; }
    table.address-table { page-break-inside: auto; }
    table.address-table tr { page-break-inside: avoid; page-break-after: auto; }
    table.address-table thead { display: table-header-group; }
}',
    'A4', 'landscape', 1, 0
);

-- 13. Elenco Soci con Intolleranze e Allergie (list - landscape)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `is_active`, `is_default`
) VALUES (
    'Elenco Soci - Intolleranze e Allergie',
    'Elenco soci con intolleranze, allergie e scelte alimentari (solo soci attivi)',
    'list', 'filtered', 'members',
    '<div class="header">
<h2>{{association_name}}</h2>
<p class="subtitle">{{association_address}} - {{association_postal_code}} {{association_city}}<br>Codice Fiscale {{association_tax_code}}</p>
<h1>ELENCO SOCI - INTOLLERANZE E ALLERGIE ALIMENTARI</h1>
</div>
<table class="health-table">
    <thead>
        <tr>
            <th class="center" style="width: 6%;">Matr.</th>
            <th class="center" style="width: 12%;">Nome</th>
            <th class="center" style="width: 12%;">Cognome</th>
            <th class="center" style="width: 22%;">Intolleranze</th>
            <th class="center" style="width: 22%;">Allergie</th>
            <th class="center" style="width: 8%;">Vegano</th>
            <th class="center" style="width: 8%;">Vegetariano</th>
        </tr>
    </thead>
    <tbody>
        {{#each records}}
        <tr>
            <td class="center">{{registration_number}}</td>
            <td>{{first_name}}</td>
            <td>{{last_name}}</td>
            <td>{{intolleranze}}</td>
            <td>{{allergie}}</td>
            <td class="center">{{#if vegano}}✓{{/if}}</td>
            <td class="center">{{#if vegetariano}}✓{{/if}}</td>
        </tr>
        {{/each}}
    </tbody>
</table>
<div class="footer">
<p>Totale Soci: {{total}} - Generato il {{current_date}}</p>
</div>',
    'body {
    font-family: Arial, sans-serif;
    font-size: 8pt;
    margin: 0;
    padding: 0;
}

@page {
    size: A4 landscape;
    margin: 10mm;
}

.header {
    text-align: center;
    margin-bottom: 5px;
    border-bottom: 2px solid #333;
    padding-bottom: 5px;
}

.header h1 {
    margin: 0;
    font-size: 14pt;
    color: #333;
}

.header h2 {
    margin: 0;
    font-size: 12pt;
    color: #333;
}

.header .subtitle {
    margin: 5px 0 0 0;
    font-size: 8pt;
    color: #666;
}

table.health-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 7pt;
    margin-bottom: 10px;
}

table.health-table thead {
    background-color: #333;
    color: white;
}

table.health-table th {
    padding: 5px 3px;
    text-align: left;
    font-weight: bold;
    border: 1px solid #333;
    font-size: 7pt;
}

.center {
    text-align: center;
}

table.health-table td {
    padding: 4px 3px;
    border: 1px solid #ccc;
    vertical-align: top;
}

table.health-table tbody tr:nth-child(even) {
    background-color: #f9f9f9;
}

.footer {
    margin-top: 15px;
    text-align: center;
    font-size: 8pt;
    color: #666;
    padding: 5px 0;
    border-top: 1px solid #ccc;
}

@media print {
    body { margin: 0; }
    .header { page-break-after: avoid; }
    table.health-table { page-break-inside: auto; }
    table.health-table tr { page-break-inside: avoid; page-break-after: auto; }
    table.health-table thead { display: table-header-group; }
}',
    'A4', 'landscape', 1, 0
);

-- 14. Scheda Socio Completa con Foto (single - portrait)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `is_active`, `is_default`
) VALUES (
    'Scheda Socio Completa',
    'Scheda completa del socio con foto e tutte le informazioni (stampa singola)',
    'single', 'single', 'members',
    '<div class="scheda-container">
<div class="header">
<h2>{{association_name}}</h2>
<p class="subtitle">{{association_address}} - {{association_postal_code}} {{association_city}}<br>Codice Fiscale {{association_tax_code}}</p>
<h1>SCHEDA SOCIO</h1>
</div>

<div class="main-content">
<div class="photo-section">
    {{#if photo_path}}
    <img src="{{photo_path}}" alt="Foto Socio" class="member-photo"/>
    {{else}}
    <div class="photo-placeholder">FOTO</div>
    {{/if}}
</div>

<div class="info-section">
<h2>Dati Anagrafici</h2>
<table class="info-table">
    <tr><td class="label">Matricola:</td><td>{{registration_number}}</td></tr>
    <tr><td class="label">Cognome:</td><td>{{last_name}}</td></tr>
    <tr><td class="label">Nome:</td><td>{{first_name}}</td></tr>
    <tr><td class="label">Data di Nascita:</td><td>{{birth_date}}</td></tr>
    <tr><td class="label">Luogo di Nascita:</td><td>{{birth_place}} ({{birth_province}})</td></tr>
    <tr><td class="label">Codice Fiscale:</td><td class="mono">{{tax_code}}</td></tr>
    <tr><td class="label">Sesso:</td><td>{{gender}}</td></tr>
    <tr><td class="label">Nazionalità:</td><td>{{nationality}}</td></tr>
</table>

<h2>Informazioni Associative</h2>
<table class="info-table">
    <tr><td class="label">Tipo Socio:</td><td>{{member_type}}</td></tr>
    <tr><td class="label">Stato Socio:</td><td class="status-{{member_status}}">{{member_status}}</td></tr>
    <tr><td class="label">Stato Volontario:</td><td>{{volunteer_status}}</td></tr>
    <tr><td class="label">Data Iscrizione:</td><td>{{registration_date}}</td></tr>
    <tr><td class="label">Data Approvazione:</td><td>{{approval_date}}</td></tr>
    {{#if termination_date}}
    <tr><td class="label">Data Cessazione:</td><td>{{termination_date}}</td></tr>
    {{/if}}
</table>

<h2>Residenza</h2>
<table class="info-table">
    <tr><td class="label">Indirizzo:</td><td>{{residenza_street}} {{residenza_number}}</td></tr>
    <tr><td class="label">Città:</td><td>{{residenza_cap}} {{residenza_city}} ({{residenza_province}})</td></tr>
</table>

{{#if domicilio_street}}
<h2>Domicilio</h2>
<table class="info-table">
    <tr><td class="label">Indirizzo:</td><td>{{domicilio_street}} {{domicilio_number}}</td></tr>
    <tr><td class="label">Città:</td><td>{{domicilio_cap}} {{domicilio_city}} ({{domicilio_province}})</td></tr>
</table>
{{/if}}

<h2>Contatti</h2>
<table class="info-table">
    {{#each contacts}}
    <tr><td class="label">{{contact_type}}:</td><td>{{value}}</td></tr>
    {{/each}}
</table>

<h2>Patenti e Licenze</h2>
<table class="data-table">
    <thead>
        <tr><th>Tipo</th><th>Numero</th><th>Scadenza</th></tr>
    </thead>
    <tbody>
        {{#each licenses}}
        <tr><td>{{license_type}}</td><td>{{license_number}}</td><td>{{expiry_date}}</td></tr>
        {{/each}}
    </tbody>
</table>

<h2>Formazione e Corsi</h2>
<table class="data-table">
    <thead>
        <tr><th>Corso</th><th>Data</th><th>Scadenza</th></tr>
    </thead>
    <tbody>
        {{#each courses}}
        <tr><td>{{course_name}}</td><td>{{completion_date}}</td><td>{{expiry_date}}</td></tr>
        {{/each}}
    </tbody>
</table>

<h2>Informazioni Salute e Alimentazione</h2>
<table class="info-table">
    {{#each health}}
    <tr><td class="label">{{health_type}}:</td><td>{{description}}</td></tr>
    {{/each}}
</table>

{{#if notes}}
<h2>Note</h2>
<div class="notes-box">{{notes}}</div>
{{/if}}

</div>
</div>

<div class="footer">
<p>Scheda generata il {{current_date}}</p>
</div>
</div>',
    'body {
    font-family: Arial, sans-serif;
    font-size: 9pt;
    margin: 0;
    padding: 0;
}

@page {
    size: A4 portrait;
    margin: 10mm;
}

.scheda-container {
    padding: 5mm;
}

.header {
    text-align: center;
    margin-bottom: 10px;
    border-bottom: 2px solid #333;
    padding-bottom: 5px;
}

.header h1 {
    margin: 5px 0;
    font-size: 16pt;
    color: #333;
}

.header h2 {
    margin: 0;
    font-size: 12pt;
    color: #333;
}

.header .subtitle {
    margin: 3px 0 0 0;
    font-size: 8pt;
    color: #666;
}

.main-content h2 {
    font-size: 11pt;
    color: #333;
    border-bottom: 1px solid #ccc;
    padding-bottom: 3px;
    margin-top: 12px;
    margin-bottom: 5px;
}

.photo-section {
    float: right;
    margin-left: 15px;
    margin-bottom: 10px;
}

.member-photo {
    width: 3.5cm;
    height: 4.5cm;
    object-fit: cover;
    border: 1px solid #333;
}

.photo-placeholder {
    width: 3.5cm;
    height: 4.5cm;
    border: 1px dashed #999;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f5f5f5;
    color: #999;
    font-size: 10pt;
}

table.info-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 9pt;
    margin-bottom: 5px;
}

table.info-table td {
    padding: 2px 5px;
    vertical-align: top;
}

table.info-table td.label {
    width: 30%;
    font-weight: bold;
    color: #555;
}

.mono {
    font-family: "Courier New", monospace;
    letter-spacing: 1px;
}

table.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 8pt;
    margin-bottom: 5px;
}

table.data-table thead {
    background-color: #333;
    color: white;
}

table.data-table th {
    padding: 4px;
    text-align: left;
    border: 1px solid #333;
}

table.data-table td {
    padding: 3px 4px;
    border: 1px solid #ccc;
}

table.data-table tbody tr:nth-child(even) {
    background-color: #f9f9f9;
}

.notes-box {
    border: 1px solid #ccc;
    padding: 5px;
    background-color: #fafafa;
    min-height: 30px;
    font-size: 8pt;
}

.status-attivo { color: #28a745; font-weight: bold; }
.status-sospeso, .status-in_aspettativa, .status-in_congedo { color: #ffc107; font-weight: bold; }
.status-dimesso, .status-decaduto, .status-escluso { color: #dc3545; font-weight: bold; }

.footer {
    margin-top: 15px;
    text-align: center;
    font-size: 8pt;
    color: #666;
    padding: 5px 0;
    border-top: 1px solid #ccc;
    clear: both;
}

@media print {
    body { margin: 0; }
    .scheda-container { page-break-inside: avoid; }
}',
    'A4', 'portrait', 1, 0
);


-- =============================================
-- ADDITIONAL JUNIOR MEMBERS (CADETTI) TEMPLATES
-- =============================================

-- 15. Fogli Firma Assemblee Cadetti (list - landscape)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `is_active`, `is_default`
) VALUES (
    'Fogli Firma Assemblee Cadetti',
    'Foglio firme per assemblee soci minorenni (solo cadetti attivi)',
    'list', 'filtered', 'junior_members',
    '<div class="header">
<h2>{{association_name}}</h2>
<p class="subtitle">{{association_address}} - {{association_postal_code}} {{association_city}}<br>Codice Fiscale {{association_tax_code}}</p>
<h1>FOGLIO FIRME ASSEMBLEA CADETTI</h1>
<p class="meeting-info">Data Assemblea: __________________ Tipo: __________________</p>
</div>
<table class="firma-table">
    <thead>
        <tr>
            <th class="center" style="width: 6%;">Matr.</th>
            <th class="center" style="width: 12%;">Nome</th>
            <th class="center" style="width: 14%;">Cognome</th>
            <th class="center" style="width: 8%;">Stato</th>
            <th class="center" style="width: 10%;">Quota {{current_year}}</th>
            <th class="center" style="width: 25%;">Firma Tutore</th>
            <th class="center" style="width: 25%;">Delega</th>
        </tr>
    </thead>
    <tbody>
        {{#each records}}
        <tr>
            <td class="center">{{registration_number}}</td>
            <td class="center">{{first_name}}</td>
            <td class="center">{{last_name}}</td>
            <td class="center status-{{member_status}}">{{member_status}}</td>
            <td class="center">{{#if fee_paid}}✓{{else}}✗{{/if}}</td>
            <td class="firma-cell"></td>
            <td class="delega-cell"></td>
        </tr>
        {{/each}}
    </tbody>
</table>
<div class="footer">
<p>Totale Cadetti Presenti: ______ di {{total}} - Generato il {{current_date}}</p>
</div>',
    'body {
    font-family: Arial, sans-serif;
    font-size: 8pt;
    margin: 0;
    padding: 0;
}

@page {
    size: A4 landscape;
    margin: 10mm;
}

.header {
    text-align: center;
    margin-bottom: 5px;
    border-bottom: 2px solid #333;
    padding-bottom: 5px;
}

.header h1 {
    margin: 0;
    font-size: 15pt;
    color: #333;
}

.header h2 {
    margin: 0;
    font-size: 13pt;
    color: #333;
}

.header .subtitle {
    margin: 5px 0 0 0;
    font-size: 9pt;
    color: #666;
}

.meeting-info {
    margin-top: 10px;
    font-size: 10pt;
}

table.firma-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 7pt;
    margin-bottom: 10px;
}

table.firma-table thead {
    background-color: #333;
    color: white;
}

table.firma-table th {
    padding: 6px 4px;
    text-align: left;
    font-weight: bold;
    border: 1px solid #333;
    font-size: 7.5pt;
}

.center {
    text-align: center;
}

table.firma-table td {
    padding: 5px 4px;
    border: 1px solid #ccc;
    vertical-align: middle;
}

table.firma-table tbody tr:nth-child(even) {
    background-color: #f9f9f9;
}

.firma-cell, .delega-cell {
    height: 25px;
    background-color: #fafafa;
}

.status-attivo { color: #28a745; font-weight: bold; }
.status-sospeso, .status-in_aspettativa, .status-in_congedo { color: #ffc107; font-weight: bold; }
.status-dimesso, .status-decaduto, .status-escluso { color: #dc3545; font-weight: bold; }

.footer {
    margin-top: 20px;
    text-align: center;
    font-size: 8pt;
    color: #666;
    padding: 5px 0;
    border-top: 1px solid #ccc;
}

@media print {
    body { margin: 0; }
    .header { page-break-after: avoid; }
    table.firma-table { page-break-inside: auto; }
    table.firma-table tr { page-break-inside: avoid; page-break-after: auto; }
    table.firma-table thead { display: table-header-group; }
}',
    'A4', 'landscape', 1, 0
);

-- 16. Elenco Cadetti con Email e Cellulare (list - portrait)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `is_active`, `is_default`
) VALUES (
    'Elenco Cadetti - Email e Cellulare',
    'Elenco cadetti con contatti email e cellulare (solo cadetti attivi)',
    'list', 'filtered', 'junior_members',
    '<div class="header">
<h2>{{association_name}}</h2>
<p class="subtitle">{{association_address}} - {{association_postal_code}} {{association_city}}<br>Codice Fiscale {{association_tax_code}}</p>
<h1>ELENCO CADETTI - CONTATTI</h1>
</div>
<table class="contact-table">
    <thead>
        <tr>
            <th class="center" style="width: 10%;">Matr.</th>
            <th class="center" style="width: 18%;">Nome</th>
            <th class="center" style="width: 18%;">Cognome</th>
            <th class="center" style="width: 30%;">Email</th>
            <th class="center" style="width: 24%;">Cellulare</th>
        </tr>
    </thead>
    <tbody>
        {{#each records}}
        <tr>
            <td class="center">{{registration_number}}</td>
            <td>{{first_name}}</td>
            <td>{{last_name}}</td>
            <td>{{email}}</td>
            <td class="center">{{cellulare}}</td>
        </tr>
        {{/each}}
    </tbody>
</table>
<div class="footer">
<p>Totale Cadetti: {{total}} - Generato il {{current_date}}</p>
</div>',
    'body {
    font-family: Arial, sans-serif;
    font-size: 9pt;
    margin: 0;
    padding: 0;
}

@page {
    size: A4 portrait;
    margin: 10mm;
}

.header {
    text-align: center;
    margin-bottom: 10px;
    border-bottom: 2px solid #333;
    padding-bottom: 5px;
}

.header h1 { margin: 0; font-size: 14pt; color: #333; }
.header h2 { margin: 0; font-size: 12pt; color: #333; }
.header .subtitle { margin: 5px 0 0 0; font-size: 8pt; color: #666; }

table.contact-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 8pt;
    margin-bottom: 10px;
}

table.contact-table thead { background-color: #333; color: white; }
table.contact-table th { padding: 6px 4px; text-align: left; font-weight: bold; border: 1px solid #333; font-size: 8pt; }
.center { text-align: center; }
table.contact-table td { padding: 4px; border: 1px solid #ccc; vertical-align: top; }
table.contact-table tbody tr:nth-child(even) { background-color: #f9f9f9; }

.footer {
    margin-top: 15px;
    text-align: center;
    font-size: 8pt;
    color: #666;
    padding: 5px 0;
    border-top: 1px solid #ccc;
}

@media print {
    body { margin: 0; }
    .header { page-break-after: avoid; }
    table.contact-table { page-break-inside: auto; }
    table.contact-table tr { page-break-inside: avoid; page-break-after: auto; }
    table.contact-table thead { display: table-header-group; }
}',
    'A4', 'portrait', 1, 0
);

-- 17. Elenco Cadetti con Tutori (list - landscape)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `is_active`, `is_default`
) VALUES (
    'Elenco Cadetti - Tutori',
    'Elenco cadetti con informazioni sui tutori/genitori (solo cadetti attivi)',
    'list', 'filtered', 'junior_members',
    '<div class="header">
<h2>{{association_name}}</h2>
<p class="subtitle">{{association_address}} - {{association_postal_code}} {{association_city}}<br>Codice Fiscale {{association_tax_code}}</p>
<h1>ELENCO CADETTI - TUTORI</h1>
</div>
<table class="tutor-table">
    <thead>
        <tr>
            <th class="center" style="width: 5%;">Matr.</th>
            <th class="center" style="width: 10%;">Nome</th>
            <th class="center" style="width: 10%;">Cognome</th>
            <th class="center" style="width: 18%;">Padre</th>
            <th class="center" style="width: 12%;">Tel. Padre</th>
            <th class="center" style="width: 18%;">Madre</th>
            <th class="center" style="width: 12%;">Tel. Madre</th>
        </tr>
    </thead>
    <tbody>
        {{#each records}}
        <tr>
            <td class="center">{{registration_number}}</td>
            <td>{{first_name}}</td>
            <td>{{last_name}}</td>
            <td>{{padre_first_name}} {{padre_last_name}}</td>
            <td class="center">{{padre_phone}}</td>
            <td>{{madre_first_name}} {{madre_last_name}}</td>
            <td class="center">{{madre_phone}}</td>
        </tr>
        {{/each}}
    </tbody>
</table>
<div class="footer">
<p>Totale Cadetti: {{total}} - Generato il {{current_date}}</p>
</div>',
    'body {
    font-family: Arial, sans-serif;
    font-size: 8pt;
    margin: 0;
    padding: 0;
}

@page {
    size: A4 landscape;
    margin: 10mm;
}

.header {
    text-align: center;
    margin-bottom: 5px;
    border-bottom: 2px solid #333;
    padding-bottom: 5px;
}

.header h1 { margin: 0; font-size: 14pt; color: #333; }
.header h2 { margin: 0; font-size: 12pt; color: #333; }
.header .subtitle { margin: 5px 0 0 0; font-size: 8pt; color: #666; }

table.tutor-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 7pt;
    margin-bottom: 10px;
}

table.tutor-table thead { background-color: #333; color: white; }
table.tutor-table th { padding: 5px 3px; text-align: left; font-weight: bold; border: 1px solid #333; font-size: 7pt; }
.center { text-align: center; }
table.tutor-table td { padding: 4px 3px; border: 1px solid #ccc; vertical-align: top; }
table.tutor-table tbody tr:nth-child(even) { background-color: #f9f9f9; }

.footer {
    margin-top: 15px;
    text-align: center;
    font-size: 8pt;
    color: #666;
    padding: 5px 0;
    border-top: 1px solid #ccc;
}

@media print {
    body { margin: 0; }
    .header { page-break-after: avoid; }
    table.tutor-table { page-break-inside: auto; }
    table.tutor-table tr { page-break-inside: avoid; page-break-after: auto; }
    table.tutor-table thead { display: table-header-group; }
}',
    'A4', 'landscape', 1, 0
);

-- 18. Elenco Cadetti con Residenza e Domicilio (list - landscape)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `is_active`, `is_default`
) VALUES (
    'Elenco Cadetti - Residenza e Domicilio',
    'Elenco cadetti con indirizzi di residenza e domicilio (solo cadetti attivi)',
    'list', 'filtered', 'junior_members',
    '<div class="header">
<h2>{{association_name}}</h2>
<p class="subtitle">{{association_address}} - {{association_postal_code}} {{association_city}}<br>Codice Fiscale {{association_tax_code}}</p>
<h1>ELENCO CADETTI - RESIDENZA E DOMICILIO</h1>
</div>
<table class="address-table">
    <thead>
        <tr>
            <th class="center" style="width: 6%;">Matr.</th>
            <th class="center" style="width: 12%;">Nome</th>
            <th class="center" style="width: 12%;">Cognome</th>
            <th class="center" style="width: 35%;">Residenza</th>
            <th class="center" style="width: 35%;">Domicilio</th>
        </tr>
    </thead>
    <tbody>
        {{#each records}}
        <tr>
            <td class="center">{{registration_number}}</td>
            <td>{{first_name}}</td>
            <td>{{last_name}}</td>
            <td>{{residenza_street}} {{residenza_number}}, {{residenza_cap}} {{residenza_city}} ({{residenza_province}})</td>
            <td>{{domicilio_street}} {{domicilio_number}}, {{domicilio_cap}} {{domicilio_city}} ({{domicilio_province}})</td>
        </tr>
        {{/each}}
    </tbody>
</table>
<div class="footer">
<p>Totale Cadetti: {{total}} - Generato il {{current_date}}</p>
</div>',
    'body {
    font-family: Arial, sans-serif;
    font-size: 8pt;
    margin: 0;
    padding: 0;
}

@page {
    size: A4 landscape;
    margin: 10mm;
}

.header {
    text-align: center;
    margin-bottom: 5px;
    border-bottom: 2px solid #333;
    padding-bottom: 5px;
}

.header h1 { margin: 0; font-size: 14pt; color: #333; }
.header h2 { margin: 0; font-size: 12pt; color: #333; }
.header .subtitle { margin: 5px 0 0 0; font-size: 8pt; color: #666; }

table.address-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 7pt;
    margin-bottom: 10px;
}

table.address-table thead { background-color: #333; color: white; }
table.address-table th { padding: 5px 3px; text-align: left; font-weight: bold; border: 1px solid #333; font-size: 7pt; }
.center { text-align: center; }
table.address-table td { padding: 4px 3px; border: 1px solid #ccc; vertical-align: top; }
table.address-table tbody tr:nth-child(even) { background-color: #f9f9f9; }

.footer {
    margin-top: 15px;
    text-align: center;
    font-size: 8pt;
    color: #666;
    padding: 5px 0;
    border-top: 1px solid #ccc;
}

@media print {
    body { margin: 0; }
    .header { page-break-after: avoid; }
    table.address-table { page-break-inside: auto; }
    table.address-table tr { page-break-inside: avoid; page-break-after: auto; }
    table.address-table thead { display: table-header-group; }
}',
    'A4', 'landscape', 1, 0
);

-- 19. Elenco Cadetti con Intolleranze e Allergie (list - landscape)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `is_active`, `is_default`
) VALUES (
    'Elenco Cadetti - Intolleranze e Allergie',
    'Elenco cadetti con intolleranze, allergie e scelte alimentari (solo cadetti attivi)',
    'list', 'filtered', 'junior_members',
    '<div class="header">
<h2>{{association_name}}</h2>
<p class="subtitle">{{association_address}} - {{association_postal_code}} {{association_city}}<br>Codice Fiscale {{association_tax_code}}</p>
<h1>ELENCO CADETTI - INTOLLERANZE E ALLERGIE ALIMENTARI</h1>
</div>
<table class="health-table">
    <thead>
        <tr>
            <th class="center" style="width: 6%;">Matr.</th>
            <th class="center" style="width: 12%;">Nome</th>
            <th class="center" style="width: 12%;">Cognome</th>
            <th class="center" style="width: 22%;">Intolleranze</th>
            <th class="center" style="width: 22%;">Allergie</th>
            <th class="center" style="width: 8%;">Vegano</th>
            <th class="center" style="width: 8%;">Vegetariano</th>
        </tr>
    </thead>
    <tbody>
        {{#each records}}
        <tr>
            <td class="center">{{registration_number}}</td>
            <td>{{first_name}}</td>
            <td>{{last_name}}</td>
            <td>{{intolleranze}}</td>
            <td>{{allergie}}</td>
            <td class="center">{{#if vegano}}✓{{/if}}</td>
            <td class="center">{{#if vegetariano}}✓{{/if}}</td>
        </tr>
        {{/each}}
    </tbody>
</table>
<div class="footer">
<p>Totale Cadetti: {{total}} - Generato il {{current_date}}</p>
</div>',
    'body {
    font-family: Arial, sans-serif;
    font-size: 8pt;
    margin: 0;
    padding: 0;
}

@page {
    size: A4 landscape;
    margin: 10mm;
}

.header {
    text-align: center;
    margin-bottom: 5px;
    border-bottom: 2px solid #333;
    padding-bottom: 5px;
}

.header h1 { margin: 0; font-size: 14pt; color: #333; }
.header h2 { margin: 0; font-size: 12pt; color: #333; }
.header .subtitle { margin: 5px 0 0 0; font-size: 8pt; color: #666; }

table.health-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 7pt;
    margin-bottom: 10px;
}

table.health-table thead { background-color: #333; color: white; }
table.health-table th { padding: 5px 3px; text-align: left; font-weight: bold; border: 1px solid #333; font-size: 7pt; }
.center { text-align: center; }
table.health-table td { padding: 4px 3px; border: 1px solid #ccc; vertical-align: top; }
table.health-table tbody tr:nth-child(even) { background-color: #f9f9f9; }

.footer {
    margin-top: 15px;
    text-align: center;
    font-size: 8pt;
    color: #666;
    padding: 5px 0;
    border-top: 1px solid #ccc;
}

@media print {
    body { margin: 0; }
    .header { page-break-after: avoid; }
    table.health-table { page-break-inside: auto; }
    table.health-table tr { page-break-inside: avoid; page-break-after: auto; }
    table.health-table thead { display: table-header-group; }
}',
    'A4', 'landscape', 1, 0
);

-- 20. Scheda Cadetto Completa con Foto (single - portrait)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `is_active`, `is_default`
) VALUES (
    'Scheda Cadetto Completa',
    'Scheda completa del cadetto con foto e tutte le informazioni (stampa singola)',
    'single', 'single', 'junior_members',
    '<div class="scheda-container">
<div class="header">
<h2>{{association_name}}</h2>
<p class="subtitle">{{association_address}} - {{association_postal_code}} {{association_city}}<br>Codice Fiscale {{association_tax_code}}</p>
<h1>SCHEDA CADETTO</h1>
</div>

<div class="main-content">
<div class="photo-section">
    {{#if photo_path}}
    <img src="{{photo_path}}" alt="Foto Cadetto" class="member-photo"/>
    {{else}}
    <div class="photo-placeholder">FOTO</div>
    {{/if}}
</div>

<div class="info-section">
<h2>Dati Anagrafici</h2>
<table class="info-table">
    <tr><td class="label">Matricola:</td><td>{{registration_number}}</td></tr>
    <tr><td class="label">Cognome:</td><td>{{last_name}}</td></tr>
    <tr><td class="label">Nome:</td><td>{{first_name}}</td></tr>
    <tr><td class="label">Data di Nascita:</td><td>{{birth_date}}</td></tr>
    <tr><td class="label">Luogo di Nascita:</td><td>{{birth_place}} ({{birth_province}})</td></tr>
    <tr><td class="label">Codice Fiscale:</td><td class="mono">{{tax_code}}</td></tr>
    <tr><td class="label">Sesso:</td><td>{{gender}}</td></tr>
    <tr><td class="label">Nazionalità:</td><td>{{nationality}}</td></tr>
</table>

<h2>Informazioni Associative</h2>
<table class="info-table">
    <tr><td class="label">Tipo Socio:</td><td>{{member_type}}</td></tr>
    <tr><td class="label">Stato:</td><td class="status-{{member_status}}">{{member_status}}</td></tr>
    <tr><td class="label">Data Iscrizione:</td><td>{{registration_date}}</td></tr>
    <tr><td class="label">Data Approvazione:</td><td>{{approval_date}}</td></tr>
    {{#if termination_date}}
    <tr><td class="label">Data Cessazione:</td><td>{{termination_date}}</td></tr>
    {{/if}}
</table>

<h2>Tutori / Genitori</h2>
<table class="data-table">
    <thead>
        <tr><th>Tipo</th><th>Nome e Cognome</th><th>Codice Fiscale</th><th>Telefono</th><th>Email</th></tr>
    </thead>
    <tbody>
        {{#each guardians}}
        <tr>
            <td>{{guardian_type}}</td>
            <td>{{first_name}} {{last_name}}</td>
            <td>{{tax_code}}</td>
            <td>{{phone}}</td>
            <td>{{email}}</td>
        </tr>
        {{/each}}
    </tbody>
</table>

<h2>Residenza</h2>
<table class="info-table">
    <tr><td class="label">Indirizzo:</td><td>{{residenza_street}} {{residenza_number}}</td></tr>
    <tr><td class="label">Città:</td><td>{{residenza_cap}} {{residenza_city}} ({{residenza_province}})</td></tr>
</table>

{{#if domicilio_street}}
<h2>Domicilio</h2>
<table class="info-table">
    <tr><td class="label">Indirizzo:</td><td>{{domicilio_street}} {{domicilio_number}}</td></tr>
    <tr><td class="label">Città:</td><td>{{domicilio_cap}} {{domicilio_city}} ({{domicilio_province}})</td></tr>
</table>
{{/if}}

<h2>Contatti</h2>
<table class="info-table">
    {{#each contacts}}
    <tr><td class="label">{{contact_type}}:</td><td>{{value}}</td></tr>
    {{/each}}
</table>

<h2>Informazioni Salute e Alimentazione</h2>
<table class="info-table">
    {{#each health}}
    <tr><td class="label">{{health_type}}:</td><td>{{description}}</td></tr>
    {{/each}}
</table>

{{#if notes}}
<h2>Note</h2>
<div class="notes-box">{{notes}}</div>
{{/if}}

</div>
</div>

<div class="footer">
<p>Scheda generata il {{current_date}}</p>
</div>
</div>',
    'body {
    font-family: Arial, sans-serif;
    font-size: 9pt;
    margin: 0;
    padding: 0;
}

@page {
    size: A4 portrait;
    margin: 10mm;
}

.scheda-container { padding: 5mm; }

.header {
    text-align: center;
    margin-bottom: 10px;
    border-bottom: 2px solid #333;
    padding-bottom: 5px;
}

.header h1 { margin: 5px 0; font-size: 16pt; color: #333; }
.header h2 { margin: 0; font-size: 12pt; color: #333; }
.header .subtitle { margin: 3px 0 0 0; font-size: 8pt; color: #666; }

.main-content h2 {
    font-size: 11pt;
    color: #333;
    border-bottom: 1px solid #ccc;
    padding-bottom: 3px;
    margin-top: 12px;
    margin-bottom: 5px;
}

.photo-section { float: right; margin-left: 15px; margin-bottom: 10px; }
.member-photo { width: 3.5cm; height: 4.5cm; object-fit: cover; border: 1px solid #333; }
.photo-placeholder {
    width: 3.5cm; height: 4.5cm;
    border: 1px dashed #999;
    display: flex; align-items: center; justify-content: center;
    background-color: #f5f5f5; color: #999; font-size: 10pt;
}

table.info-table { width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 5px; }
table.info-table td { padding: 2px 5px; vertical-align: top; }
table.info-table td.label { width: 30%; font-weight: bold; color: #555; }
.mono { font-family: "Courier New", monospace; letter-spacing: 1px; }

table.data-table { width: 100%; border-collapse: collapse; font-size: 8pt; margin-bottom: 5px; }
table.data-table thead { background-color: #333; color: white; }
table.data-table th { padding: 4px; text-align: left; border: 1px solid #333; }
table.data-table td { padding: 3px 4px; border: 1px solid #ccc; }
table.data-table tbody tr:nth-child(even) { background-color: #f9f9f9; }

.notes-box { border: 1px solid #ccc; padding: 5px; background-color: #fafafa; min-height: 30px; font-size: 8pt; }

.status-attivo { color: #28a745; font-weight: bold; }
.status-sospeso, .status-in_aspettativa, .status-in_congedo { color: #ffc107; font-weight: bold; }
.status-dimesso, .status-decaduto, .status-escluso { color: #dc3545; font-weight: bold; }

.footer {
    margin-top: 15px;
    text-align: center;
    font-size: 8pt;
    color: #666;
    padding: 5px 0;
    border-top: 1px solid #ccc;
    clear: both;
}

@media print { body { margin: 0; } .scheda-container { page-break-inside: avoid; } }',
    'A4', 'portrait', 1, 0
);


-- =============================================
-- ADDITIONAL VEHICLES (MEZZI) TEMPLATES
-- =============================================

-- 21. Elenco Mezzi con Scadenze (list - landscape)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `is_active`, `is_default`
) VALUES (
    'Elenco Mezzi - Scadenze',
    'Elenco mezzi con scadenze assicurazione e revisione',
    'list', 'filtered', 'vehicles',
    '<div class="header">
<h2>{{association_name}}</h2>
<p class="subtitle">{{association_address}} - {{association_postal_code}} {{association_city}}<br>Codice Fiscale {{association_tax_code}}</p>
<h1>ELENCO MEZZI - SCADENZE</h1>
</div>
<table class="vehicle-table">
    <thead>
        <tr>
            <th class="center" style="width: 10%;">Tipo</th>
            <th class="center" style="width: 10%;">Targa</th>
            <th class="center" style="width: 12%;">Marca</th>
            <th class="center" style="width: 15%;">Modello</th>
            <th class="center" style="width: 10%;">Stato</th>
            <th class="center" style="width: 8%;">Anno</th>
            <th class="center" style="width: 15%;">Scad. Assicurazione</th>
            <th class="center" style="width: 15%;">Scad. Revisione</th>
        </tr>
    </thead>
    <tbody>
        {{#each records}}
        <tr>
            <td class="center">{{vehicle_type}}</td>
            <td class="center mono">{{license_plate}}</td>
            <td>{{brand}}</td>
            <td>{{model}}</td>
            <td class="center status-{{status}}">{{status}}</td>
            <td class="center">{{year}}</td>
            <td class="center {{#if insurance_expiring}}expiring{{/if}} {{#if insurance_expired}}expired{{/if}}">{{insurance_expiry}}</td>
            <td class="center {{#if inspection_expiring}}expiring{{/if}} {{#if inspection_expired}}expired{{/if}}">{{inspection_expiry}}</td>
        </tr>
        {{/each}}
    </tbody>
</table>
<div class="footer">
<p>Totale Mezzi: {{total}} - Generato il {{current_date}}</p>
<p class="legend">Legenda: <span class="expiring">In scadenza (30 giorni)</span> | <span class="expired">Scaduto</span></p>
</div>',
    'body {
    font-family: Arial, sans-serif;
    font-size: 8pt;
    margin: 0;
    padding: 0;
}

@page {
    size: A4 landscape;
    margin: 10mm;
}

.header {
    text-align: center;
    margin-bottom: 5px;
    border-bottom: 2px solid #333;
    padding-bottom: 5px;
}

.header h1 { margin: 0; font-size: 14pt; color: #333; }
.header h2 { margin: 0; font-size: 12pt; color: #333; }
.header .subtitle { margin: 5px 0 0 0; font-size: 8pt; color: #666; }

table.vehicle-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 7.5pt;
    margin-bottom: 10px;
}

table.vehicle-table thead { background-color: #333; color: white; }
table.vehicle-table th { padding: 5px 3px; text-align: left; font-weight: bold; border: 1px solid #333; font-size: 7.5pt; }
.center { text-align: center; }
.mono { font-family: "Courier New", monospace; }
table.vehicle-table td { padding: 4px 3px; border: 1px solid #ccc; vertical-align: middle; }
table.vehicle-table tbody tr:nth-child(even) { background-color: #f9f9f9; }

.status-operativo { color: #28a745; font-weight: bold; }
.status-in_manutenzione { color: #ffc107; font-weight: bold; }
.status-fuori_servizio, .status-dismesso { color: #dc3545; font-weight: bold; }

.expiring { background-color: #fff3cd; color: #856404; }
.expired { background-color: #f8d7da; color: #721c24; font-weight: bold; }

.footer {
    margin-top: 15px;
    text-align: center;
    font-size: 8pt;
    color: #666;
    padding: 5px 0;
    border-top: 1px solid #ccc;
}

.legend { font-size: 7pt; margin-top: 5px; }
.legend .expiring { padding: 2px 5px; }
.legend .expired { padding: 2px 5px; }

@media print {
    body { margin: 0; }
    .header { page-break-after: avoid; }
    table.vehicle-table { page-break-inside: auto; }
    table.vehicle-table tr { page-break-inside: avoid; page-break-after: auto; }
    table.vehicle-table thead { display: table-header-group; }
}',
    'A4', 'landscape', 1, 0
);

-- 22. Scheda Mezzo Completa (single - portrait)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `is_active`, `is_default`
) VALUES (
    'Scheda Mezzo Completa',
    'Scheda completa del mezzo con tutti i dati (stampa singola)',
    'single', 'single', 'vehicles',
    '<div class="scheda-container">
<div class="header">
<h2>{{association_name}}</h2>
<p class="subtitle">{{association_address}} - {{association_postal_code}} {{association_city}}<br>Codice Fiscale {{association_tax_code}}</p>
<h1>SCHEDA MEZZO</h1>
</div>

<div class="main-content">
<h2>Dati Identificativi</h2>
<table class="info-table">
    <tr><td class="label">Nome/Identificativo:</td><td>{{name}}</td></tr>
    <tr><td class="label">Tipo Mezzo:</td><td>{{vehicle_type}}</td></tr>
    <tr><td class="label">Targa:</td><td class="mono">{{license_plate}}</td></tr>
    <tr><td class="label">Marca:</td><td>{{brand}}</td></tr>
    <tr><td class="label">Modello:</td><td>{{model}}</td></tr>
    <tr><td class="label">Anno Immatricolazione:</td><td>{{year}}</td></tr>
    <tr><td class="label">Numero Telaio:</td><td class="mono">{{serial_number}}</td></tr>
    <tr><td class="label">Stato:</td><td class="status-{{status}}">{{status}}</td></tr>
    <tr><td class="label">Patente Richiesta:</td><td>{{license_type}}</td></tr>
</table>

<h2>Scadenze Documenti</h2>
<table class="info-table">
    <tr>
        <td class="label">Scadenza Assicurazione:</td>
        <td class="{{#if insurance_expiring}}expiring{{/if}} {{#if insurance_expired}}expired{{/if}}">{{insurance_expiry}}</td>
    </tr>
    <tr>
        <td class="label">Scadenza Revisione:</td>
        <td class="{{#if inspection_expiring}}expiring{{/if}} {{#if inspection_expired}}expired{{/if}}">{{inspection_expiry}}</td>
    </tr>
</table>

<h2>Documenti Allegati</h2>
<table class="data-table">
    <thead>
        <tr><th>Tipo Documento</th><th>Nome File</th><th>Scadenza</th></tr>
    </thead>
    <tbody>
        {{#each documents}}
        <tr>
            <td>{{document_type}}</td>
            <td>{{file_name}}</td>
            <td>{{expiry_date}}</td>
        </tr>
        {{/each}}
    </tbody>
</table>

{{#if notes}}
<h2>Note</h2>
<div class="notes-box">{{notes}}</div>
{{/if}}

</div>

<div class="footer">
<p>Scheda generata il {{current_date}}</p>
</div>
</div>',
    'body {
    font-family: Arial, sans-serif;
    font-size: 10pt;
    margin: 0;
    padding: 0;
}

@page {
    size: A4 portrait;
    margin: 10mm;
}

.scheda-container { padding: 5mm; }

.header {
    text-align: center;
    margin-bottom: 10px;
    border-bottom: 2px solid #333;
    padding-bottom: 5px;
}

.header h1 { margin: 5px 0; font-size: 16pt; color: #333; }
.header h2 { margin: 0; font-size: 12pt; color: #333; }
.header .subtitle { margin: 3px 0 0 0; font-size: 8pt; color: #666; }

.main-content h2 {
    font-size: 12pt;
    color: #333;
    border-bottom: 1px solid #ccc;
    padding-bottom: 3px;
    margin-top: 15px;
    margin-bottom: 8px;
}

table.info-table { width: 100%; border-collapse: collapse; font-size: 10pt; margin-bottom: 5px; }
table.info-table td { padding: 4px 8px; vertical-align: top; }
table.info-table td.label { width: 35%; font-weight: bold; color: #555; background-color: #f9f9f9; }
.mono { font-family: "Courier New", monospace; letter-spacing: 1px; }

table.data-table { width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 5px; }
table.data-table thead { background-color: #333; color: white; }
table.data-table th { padding: 5px; text-align: left; border: 1px solid #333; }
table.data-table td { padding: 4px 5px; border: 1px solid #ccc; }
table.data-table tbody tr:nth-child(even) { background-color: #f9f9f9; }

.notes-box { border: 1px solid #ccc; padding: 8px; background-color: #fafafa; min-height: 40px; font-size: 9pt; }

.status-operativo { color: #28a745; font-weight: bold; }
.status-in_manutenzione { color: #ffc107; font-weight: bold; }
.status-fuori_servizio, .status-dismesso { color: #dc3545; font-weight: bold; }

.expiring { background-color: #fff3cd; color: #856404; padding: 2px 5px; }
.expired { background-color: #f8d7da; color: #721c24; font-weight: bold; padding: 2px 5px; }

.footer {
    margin-top: 20px;
    text-align: center;
    font-size: 8pt;
    color: #666;
    padding: 5px 0;
    border-top: 1px solid #ccc;
}

@media print { body { margin: 0; } .scheda-container { page-break-inside: avoid; } }',
    'A4', 'portrait', 1, 0
);

-- 23. Scheda Mezzo con Manutenzioni (single - portrait)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `is_active`, `is_default`
) VALUES (
    'Scheda Mezzo con Manutenzioni',
    'Scheda completa del mezzo con elenco manutenzioni (stampa singola)',
    'single', 'single', 'vehicles',
    '<div class="scheda-container">
<div class="header">
<h2>{{association_name}}</h2>
<p class="subtitle">{{association_address}} - {{association_postal_code}} {{association_city}}<br>Codice Fiscale {{association_tax_code}}</p>
<h1>SCHEDA MEZZO CON STORICO MANUTENZIONI</h1>
</div>

<div class="main-content">
<h2>Dati Identificativi</h2>
<table class="info-table">
    <tr><td class="label">Nome/Identificativo:</td><td>{{name}}</td></tr>
    <tr><td class="label">Tipo Mezzo:</td><td>{{vehicle_type}}</td></tr>
    <tr><td class="label">Targa:</td><td class="mono">{{license_plate}}</td></tr>
    <tr><td class="label">Marca:</td><td>{{brand}}</td></tr>
    <tr><td class="label">Modello:</td><td>{{model}}</td></tr>
    <tr><td class="label">Anno Immatricolazione:</td><td>{{year}}</td></tr>
    <tr><td class="label">Stato:</td><td class="status-{{status}}">{{status}}</td></tr>
</table>

<h2>Scadenze Documenti</h2>
<table class="info-table">
    <tr>
        <td class="label">Scadenza Assicurazione:</td>
        <td class="{{#if insurance_expiring}}expiring{{/if}} {{#if insurance_expired}}expired{{/if}}">{{insurance_expiry}}</td>
    </tr>
    <tr>
        <td class="label">Scadenza Revisione:</td>
        <td class="{{#if inspection_expiring}}expiring{{/if}} {{#if inspection_expired}}expired{{/if}}">{{inspection_expiry}}</td>
    </tr>
</table>

<h2>Storico Manutenzioni</h2>
<table class="maint-table">
    <thead>
        <tr>
            <th style="width: 12%;">Data</th>
            <th style="width: 15%;">Tipo</th>
            <th style="width: 35%;">Descrizione</th>
            <th style="width: 12%;">Costo</th>
            <th style="width: 15%;">Eseguita da</th>
            <th style="width: 11%;">Stato</th>
        </tr>
    </thead>
    <tbody>
        {{#each maintenance}}
        <tr>
            <td class="center">{{date}}</td>
            <td>{{maintenance_type}}</td>
            <td>{{description}}</td>
            <td class="right">€ {{cost}}</td>
            <td>{{performed_by}}</td>
            <td class="center status-{{status}}">{{status}}</td>
        </tr>
        {{/each}}
    </tbody>
</table>

<div class="summary-box">
    <p><strong>Totale Manutenzioni:</strong> {{maintenance_count}} | <strong>Costo Totale:</strong> € {{maintenance_total_cost}}</p>
</div>

{{#if notes}}
<h2>Note</h2>
<div class="notes-box">{{notes}}</div>
{{/if}}

</div>

<div class="footer">
<p>Scheda generata il {{current_date}}</p>
</div>
</div>',
    'body {
    font-family: Arial, sans-serif;
    font-size: 9pt;
    margin: 0;
    padding: 0;
}

@page {
    size: A4 portrait;
    margin: 10mm;
}

.scheda-container { padding: 5mm; }

.header {
    text-align: center;
    margin-bottom: 10px;
    border-bottom: 2px solid #333;
    padding-bottom: 5px;
}

.header h1 { margin: 5px 0; font-size: 14pt; color: #333; }
.header h2 { margin: 0; font-size: 12pt; color: #333; }
.header .subtitle { margin: 3px 0 0 0; font-size: 8pt; color: #666; }

.main-content h2 {
    font-size: 11pt;
    color: #333;
    border-bottom: 1px solid #ccc;
    padding-bottom: 3px;
    margin-top: 12px;
    margin-bottom: 5px;
}

table.info-table { width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 5px; }
table.info-table td { padding: 3px 6px; vertical-align: top; }
table.info-table td.label { width: 30%; font-weight: bold; color: #555; background-color: #f9f9f9; }
.mono { font-family: "Courier New", monospace; letter-spacing: 1px; }

table.maint-table { width: 100%; border-collapse: collapse; font-size: 8pt; margin-bottom: 10px; }
table.maint-table thead { background-color: #333; color: white; }
table.maint-table th { padding: 4px; text-align: left; border: 1px solid #333; font-size: 8pt; }
table.maint-table td { padding: 3px 4px; border: 1px solid #ccc; vertical-align: top; }
table.maint-table tbody tr:nth-child(even) { background-color: #f9f9f9; }

.center { text-align: center; }
.right { text-align: right; }

.summary-box {
    background-color: #e9ecef;
    padding: 8px;
    border: 1px solid #ccc;
    text-align: center;
    font-size: 9pt;
}

.notes-box { border: 1px solid #ccc; padding: 5px; background-color: #fafafa; min-height: 30px; font-size: 8pt; }

.status-operativo { color: #28a745; font-weight: bold; }
.status-in_manutenzione { color: #ffc107; font-weight: bold; }
.status-fuori_servizio, .status-dismesso { color: #dc3545; font-weight: bold; }

.expiring { background-color: #fff3cd; color: #856404; padding: 2px 5px; }
.expired { background-color: #f8d7da; color: #721c24; font-weight: bold; padding: 2px 5px; }

.footer {
    margin-top: 15px;
    text-align: center;
    font-size: 8pt;
    color: #666;
    padding: 5px 0;
    border-top: 1px solid #ccc;
}

@media print {
    body { margin: 0; }
    table.maint-table { page-break-inside: auto; }
    table.maint-table tr { page-break-inside: avoid; page-break-after: auto; }
    table.maint-table thead { display: table-header-group; }
}',
    'A4', 'portrait', 1, 0
);


-- =============================================
-- ADDITIONAL MEETINGS (RIUNIONI/ASSEMBLEE) TEMPLATES
-- =============================================

-- 24. Verbale di Riunione/Assemblea Completo (single - portrait)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `is_active`, `is_default`
) VALUES (
    'Verbale Riunione/Assemblea',
    'Verbale ufficiale completo di riunione o assemblea (stampa singola)',
    'single', 'single', 'meetings',
    '<div class="verbale-container">
<div class="header">
<h2>{{association_name}}</h2>
<p class="subtitle">{{association_address}} - {{association_postal_code}} {{association_city}}<br>Codice Fiscale {{association_tax_code}}</p>
<h1>VERBALE DI {{meeting_type_upper}}</h1>
</div>

<div class="verbale-intro">
<p>L''anno <strong>{{meeting_year}}</strong>, il giorno <strong>{{meeting_day}}</strong> del mese di <strong>{{meeting_month}}</strong>, 
alle ore <strong>{{start_time}}</strong>, presso <strong>{{location}}</strong>{{#if location_address}} ({{location_address}}){{/if}}, 
si è riunita {{meeting_type_article}} <strong>{{meeting_type}}</strong> {{#if title}}avente ad oggetto: <em>{{title}}</em>{{/if}}.</p>

<p>La riunione è stata convocata da <strong>{{convocator}}</strong>.</p>

{{#if online_details}}
<p>Modalità di partecipazione: <strong>{{location_type}}</strong><br>
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
            <td>{{participant_name}}</td>
            <td>{{role}}</td>
            <td class="center">
                {{#if present}}Presente{{else}}
                {{#eq attendance_status "delegated"}}Delegato{{else}}Assente{{/eq}}
                {{/if}}
            </td>
            <td>{{#if delegated_to_name}}{{delegated_to_name}}{{/if}}</td>
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
    <h3>Punto {{order_number}}: {{subject}}</h3>
    {{#if discussion}}
    <div class="discussion-text">{{discussion}}</div>
    {{else}}
    <div class="discussion-placeholder">[Discussione non registrata]</div>
    {{/if}}
    
    {{#if has_voting}}
    <div class="votazione">
        <p><strong>VOTAZIONE:</strong></p>
        <p>Votanti: {{voting_total}} | Favorevoli: {{voting_in_favor}} | Contrari: {{voting_against}} | Astenuti: {{voting_abstained}}</p>
        <p>Esito: <strong>{{#if voting_approved}}APPROVATO{{else}}NON APPROVATO{{/if}}</strong></p>
    </div>
    {{/if}}
</div>
{{/each}}

{{#if minutes_content}}
<h2>Verbale Dettagliato</h2>
<div class="minutes-content">
{{{minutes_content}}}
</div>
{{/if}}

<div class="chiusura">
<p>Non essendovi altro da discutere, la seduta viene sciolta alle ore <strong>{{end_time}}</strong>.</p>

<p>Del che è verbale.</p>
</div>

<div class="firme">
<table class="firme-table">
    <tr>
        <td class="firma-col">
            <p>Il Segretario</p>
            <div class="firma-line"></div>
            <p class="firma-nome">_______________________</p>
        </td>
        <td class="firma-col">
            <p>Il Presidente</p>
            <div class="firma-line"></div>
            <p class="firma-nome">_______________________</p>
        </td>
    </tr>
</table>
</div>

<div class="footer">
<p>Verbale generato il {{current_date}}</p>
</div>
</div>',
    'body {
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
.firma-nome { font-size: 9pt; color: #666; }

.footer {
    margin-top: 30px;
    text-align: center;
    font-size: 8pt;
    color: #999;
    padding: 5px 0;
    border-top: 1px solid #ddd;
}

@media print {
    body { margin: 0; }
    .verbale-container { page-break-inside: auto; }
    .discussione-item { page-break-inside: avoid; }
    .firme { page-break-inside: avoid; }
}',
    'A4', 'portrait', 1, 0
);

-- 25. Avviso di Convocazione Riunione/Assemblea (single - portrait)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `is_active`, `is_default`
) VALUES (
    'Avviso di Convocazione',
    'Avviso di convocazione per riunione o assemblea',
    'single', 'single', 'meetings',
    '<div class="convocazione-container">
<div class="header">
<h2>{{association_name}}</h2>
<p class="subtitle">{{association_address}} - {{association_postal_code}} {{association_city}}<br>Codice Fiscale {{association_tax_code}}</p>
</div>

<div class="documento-titolo">
<h1>AVVISO DI CONVOCAZIONE</h1>
<h2>{{meeting_type_upper}}</h2>
</div>

<div class="corpo-lettera">
<p class="destinatari">A tutti i Soci aventi diritto di voto</p>

<p class="intro">Con la presente si comunica che è convocata {{meeting_type_article}} <strong>{{meeting_type}}</strong> dell''Associazione, 
secondo le modalità previste dallo Statuto sociale.</p>

<div class="dettagli-riunione">
<table class="dettagli-table">
    <tr>
        <td class="label">Data:</td>
        <td><strong>{{meeting_date_formatted}}</strong></td>
    </tr>
    <tr>
        <td class="label">Orario:</td>
        <td>Prima convocazione: ore <strong>{{start_time}}</strong></td>
    </tr>
    <tr>
        <td class="label">Luogo:</td>
        <td><strong>{{location}}</strong>{{#if location_address}}<br>{{location_address}}{{/if}}</td>
    </tr>
    {{#if online_details}}
    <tr>
        <td class="label">Modalità:</td>
        <td>{{location_type}}<br>{{online_details}}</td>
    </tr>
    {{/if}}
</table>
</div>

{{#if title}}
<p class="oggetto"><strong>Oggetto:</strong> {{title}}</p>
{{/if}}

<h3>Ordine del Giorno</h3>
<ol class="odg-list">
    {{#each agenda}}
    <li>
        <strong>{{subject}}</strong>
        {{#if description}}<br><span class="odg-desc">{{description}}</span>{{/if}}
    </li>
    {{/each}}
</ol>

{{#if description}}
<h3>Note</h3>
<p class="note-text">{{description}}</p>
{{/if}}

<div class="avviso-deleghe">
<p><strong>DELEGHE:</strong> I soci impossibilitati a partecipare possono farsi rappresentare da altro socio mediante delega scritta, 
secondo quanto previsto dallo Statuto. Ogni socio può rappresentare al massimo un altro socio.</p>
</div>

<p class="invito">La partecipazione di tutti i soci è importante per le decisioni che riguardano la vita associativa.</p>

</div>

<div class="firma-convocatore">
<p>Cordiali saluti,</p>
<p class="firma-ruolo">{{convocator}}</p>
<div class="firma-line"></div>
</div>

<div class="footer">
<p>{{association_city}}, lì {{current_date}}</p>
</div>
</div>',
    'body {
    font-family: Arial, sans-serif;
    font-size: 11pt;
    margin: 0;
    padding: 0;
    line-height: 1.4;
}

@page {
    size: A4 portrait;
    margin: 15mm;
}

.convocazione-container { padding: 5mm; }

.header {
    text-align: center;
    margin-bottom: 20px;
    border-bottom: 2px solid #333;
    padding-bottom: 10px;
}

.header h2 { margin: 0; font-size: 14pt; color: #333; }
.header .subtitle { margin: 5px 0 0 0; font-size: 9pt; color: #666; }

.documento-titolo {
    text-align: center;
    margin: 25px 0;
}

.documento-titolo h1 {
    margin: 0;
    font-size: 18pt;
    color: #333;
    letter-spacing: 3px;
}

.documento-titolo h2 {
    margin: 5px 0 0 0;
    font-size: 14pt;
    color: #555;
    font-weight: normal;
}

.corpo-lettera { margin: 20px 0; }

.destinatari {
    font-weight: bold;
    margin-bottom: 20px;
    padding: 10px;
    background-color: #f5f5f5;
    border-left: 4px solid #333;
}

.intro { text-align: justify; margin-bottom: 20px; }

.dettagli-riunione {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    padding: 15px;
    margin: 20px 0;
}

.dettagli-table { width: 100%; border-collapse: collapse; }
.dettagli-table td { padding: 8px 10px; vertical-align: top; }
.dettagli-table td.label { width: 20%; font-weight: bold; color: #555; }

.oggetto { margin: 20px 0; font-size: 11pt; }

h3 {
    font-size: 12pt;
    color: #333;
    border-bottom: 1px solid #ccc;
    padding-bottom: 3px;
    margin-top: 20px;
    margin-bottom: 10px;
}

ol.odg-list { margin: 10px 0; padding-left: 25px; }
ol.odg-list li { margin-bottom: 10px; }
.odg-desc { font-size: 10pt; color: #666; font-style: italic; }

.note-text { text-align: justify; font-size: 10pt; color: #555; }

.avviso-deleghe {
    background-color: #fff3cd;
    border: 1px solid #ffc107;
    padding: 10px;
    margin: 20px 0;
    font-size: 10pt;
}

.invito {
    text-align: center;
    font-style: italic;
    margin: 25px 0;
    color: #555;
}

.firma-convocatore {
    margin-top: 40px;
    text-align: right;
}

.firma-ruolo { font-weight: bold; margin: 10px 0; }
.firma-line {
    border-bottom: 1px solid #333;
    width: 200px;
    margin-left: auto;
    margin-top: 30px;
}

.footer {
    margin-top: 30px;
    text-align: left;
    font-size: 9pt;
    color: #666;
}

@media print {
    body { margin: 0; }
    .convocazione-container { page-break-inside: avoid; }
}',
    'A4', 'portrait', 1, 0
);

COMMIT;
