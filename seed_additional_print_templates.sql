-- =============================================
-- ADDITIONAL PRINT TEMPLATES FOR EASYVOL
-- Created: 2026-01-04
-- =============================================
-- 14 new professional print templates
-- Run: mysql -u username -p database_name < seed_additional_print_templates.sql
-- =============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- =============================================
-- MEMBERS TEMPLATES (4 templates)
-- =============================================

-- Template 1: Scheda Socio Completa
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `footer_content`, `is_active`, `is_default`
) VALUES (
    'Scheda Socio Completa',
    'Scheda completa del socio con dati anagrafici, associativi, contatti, indirizzi, patenti, corsi, impiego, disponibilità e note',
    'relational', 'single', 'members',
    '<div style="margin: 1cm;">
        <h1 style="border-bottom: 3px solid #333; padding-bottom: 0.3cm; color: #333; font-size: 18pt;">SCHEDA SOCIO COMPLETA</h1>
        
        <h2 style="margin-top: 0.8cm; font-size: 14pt; color: #555;">Dati Anagrafici</h2>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 1cm; font-size: 10pt;">
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
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Sesso:</td>
                <td style="padding: 0.2cm;">{{gender}}</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Nazionalità:</td>
                <td style="padding: 0.2cm;">{{nationality}}</td>
            </tr>
        </table>

        <h2 style="margin-top: 0.8cm; font-size: 14pt; color: #555;">Informazioni Associative</h2>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 1cm; font-size: 10pt;">
            <tr>
                <td style="width: 30%; padding: 0.2cm; font-weight: bold;">Tipo Socio:</td>
                <td style="padding: 0.2cm;">{{member_type}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Stato:</td>
                <td style="padding: 0.2cm;">{{member_status}}</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Stato Volontario:</td>
                <td style="padding: 0.2cm;">{{volunteer_status}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Data Iscrizione:</td>
                <td style="padding: 0.2cm;">{{registration_date}}</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Data Approvazione:</td>
                <td style="padding: 0.2cm;">{{approval_date}}</td>
            </tr>
        </table>

        <h2 style="margin-top: 0.8cm; font-size: 14pt; color: #555;">Contatti</h2>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-bottom: 1cm; font-size: 10pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; text-align: left;">Tipo</th>
                    <th style="padding: 0.3cm; text-align: left;">Valore</th>
                </tr>
            </thead>
            <tbody>
                {{#each member_contacts}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{contact_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{value}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>

        <h2 style="margin-top: 0.8cm; font-size: 14pt; color: #555;">Indirizzi</h2>
        {{#each member_addresses}}
        <div style="border: 1px solid #ccc; padding: 0.5cm; margin-bottom: 0.5cm; background: #f9f9f9;">
            <p><strong>{{address_type}}:</strong> {{street}} {{number}}, {{cap}} {{city}} ({{province}})</p>
        </div>
        {{/each}}

        <h2 style="margin-top: 0.8cm; font-size: 14pt; color: #555;">Patenti</h2>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-bottom: 1cm; font-size: 10pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; text-align: left;">Tipo</th>
                    <th style="padding: 0.3cm; text-align: left;">Numero</th>
                    <th style="padding: 0.3cm; text-align: left;">Rilascio</th>
                    <th style="padding: 0.3cm; text-align: left;">Scadenza</th>
                </tr>
            </thead>
            <tbody>
                {{#each member_licenses}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{license_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{license_number}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{issue_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{expiry_date}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>

        <h2 style="margin-top: 0.8cm; font-size: 14pt; color: #555;">Corsi e Formazione</h2>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-bottom: 1cm; font-size: 10pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; text-align: left;">Corso</th>
                    <th style="padding: 0.3cm; text-align: left;">Tipo</th>
                    <th style="padding: 0.3cm; text-align: left;">Completamento</th>
                    <th style="padding: 0.3cm; text-align: left;">Scadenza</th>
                </tr>
            </thead>
            <tbody>
                {{#each member_courses}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{course_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{course_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{completion_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{expiry_date}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>

        <h2 style="margin-top: 0.8cm; font-size: 14pt; color: #555;">Impiego</h2>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-bottom: 1cm; font-size: 10pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; text-align: left;">Ruolo</th>
                    <th style="padding: 0.3cm; text-align: left;">Data Inizio</th>
                    <th style="padding: 0.3cm; text-align: left;">Data Fine</th>
                </tr>
            </thead>
            <tbody>
                {{#each member_employment}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{role}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{start_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{end_date}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>

        <h2 style="margin-top: 0.8cm; font-size: 14pt; color: #555;">Disponibilità</h2>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-bottom: 1cm; font-size: 10pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; text-align: left;">Giorno</th>
                    <th style="padding: 0.3cm; text-align: left;">Fascia Oraria</th>
                    <th style="padding: 0.3cm; text-align: left;">Disponibile</th>
                </tr>
            </thead>
            <tbody>
                {{#each member_availability}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{day}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{time_slot}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{available}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>

        <h2 style="margin-top: 0.8cm; font-size: 14pt; color: #555;">Note</h2>
        <div style="border: 1px solid #ccc; padding: 0.5cm; min-height: 2cm; background: #f9f9f9;">
            {{notes}}
        </div>
    </div>',
    'body { font-family: Arial, sans-serif; font-size: 11pt; }
    h1 { font-size: 18pt; color: #333; }
    h2 { font-size: 14pt; color: #555; margin-top: 0.8cm; margin-bottom: 0.3cm; }
    table { font-size: 10pt; }
    @media print { 
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .page-break { page-break-after: always; }
    }',
    '["member_contacts", "member_addresses", "member_licenses", "member_courses", "member_employment", "member_availability"]',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 0.3cm; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}}</h2>
    </div>',
    '<div style="text-align: center; margin-top: 0.5cm; padding-top: 0.3cm; border-top: 1px solid #ccc; font-size: 9pt;">
        Stampato il {{current_date}}
    </div>',
    1, 0
);


-- Template 2: Libro Soci
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `filter_config`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Libro Soci',
    'Elenco completo di tutti i soci con matricola, cognome, nome, data nascita, codice fiscale, tipo socio, stato, data iscrizione',
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
                    <th style="padding: 0.3cm; border: 1px solid #000;">Iscr.</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr style="{{#if @even}}background: #f5f5f5;{{/if}}">
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{registration_number}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{last_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{first_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{birth_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{tax_code}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{member_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{member_status}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{registration_date}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    '{"filters": [{"field": "member_status", "label": "Stato Socio", "type": "select"}]}',
    'A4', 'landscape', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}} - Libro Soci</h2>
        <p style="margin: 0; font-size: 10pt;">Stampato il {{current_date}}</p>
    </div>',
    1, 1
);

-- Template 3: Elenco Soci con Codice Fiscale
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `filter_config`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Elenco Soci con Codice Fiscale',
    'Elenco soci con matricola, cognome, nome, data nascita, luogo nascita, codice fiscale - formato compatto',
    'list', 'filtered', 'members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm; font-size: 16pt;">ELENCO SOCI CON CODICE FISCALE</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 9pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.2cm; border: 1px solid #000;">Matr.</th>
                    <th style="padding: 0.2cm; border: 1px solid #000;">Cognome</th>
                    <th style="padding: 0.2cm; border: 1px solid #000;">Nome</th>
                    <th style="padding: 0.2cm; border: 1px solid #000;">Data Nasc.</th>
                    <th style="padding: 0.2cm; border: 1px solid #000;">Luogo Nascita</th>
                    <th style="padding: 0.2cm; border: 1px solid #000;">Codice Fiscale</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr style="{{#if @even}}background: #f5f5f5;{{/if}}">
                    <td style="padding: 0.15cm; border: 1px solid #ccc;">{{registration_number}}</td>
                    <td style="padding: 0.15cm; border: 1px solid #ccc;">{{last_name}}</td>
                    <td style="padding: 0.15cm; border: 1px solid #ccc;">{{first_name}}</td>
                    <td style="padding: 0.15cm; border: 1px solid #ccc;">{{birth_date}}</td>
                    <td style="padding: 0.15cm; border: 1px solid #ccc;">{{birth_place}} ({{birth_province}})</td>
                    <td style="padding: 0.15cm; border: 1px solid #ccc;">{{tax_code}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    '{"filters": [{"field": "member_status", "label": "Stato", "type": "select", "options": ["attivo", "sospeso"]}]}',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}}</h2>
        <p style="margin: 0; font-size: 10pt;">Stampato il {{current_date}}</p>
    </div>',
    1, 0
);

-- Template 4: Elenco Soci con Recapiti
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `filter_config`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Elenco Soci con Recapiti',
    'Elenco soci con matricola, cognome, nome, telefono fisso, cellulare, email',
    'list', 'filtered', 'members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ELENCO SOCI CON RECAPITI</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 9pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000;">Matr.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Cognome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Nome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Tel. Fisso</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Cellulare</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Email</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr style="{{#if @even}}background: #f5f5f5;{{/if}}">
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{registration_number}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{last_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{first_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{phone}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{mobile}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc; font-size: 8pt;">{{email}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    '{"filters": [{"field": "member_status", "label": "Stato", "type": "select", "options": ["attivo", "sospeso"]}]}',
    'A4', 'landscape', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}} - Contatti Soci</h2>
        <p style="margin: 0; font-size: 10pt;">Stampato il {{current_date}}</p>
    </div>',
    1, 0
);


-- =============================================
-- JUNIOR MEMBERS TEMPLATES (4 templates)
-- =============================================

-- Template 5: Scheda Cadetto Completa
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `footer_content`, `is_active`, `is_default`
) VALUES (
    'Scheda Cadetto Completa',
    'Scheda completa del cadetto con dati anagrafici, contatti, tutori (padre/madre), scuola, allergie e intolleranze',
    'relational', 'single', 'junior_members',
    '<div style="margin: 1cm;">
        <h1 style="border-bottom: 3px solid #333; padding-bottom: 0.3cm; color: #333; font-size: 18pt;">SCHEDA CADETTO COMPLETA</h1>
        
        <h2 style="margin-top: 0.8cm; font-size: 14pt; color: #555;">Dati Anagrafici</h2>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 1cm; font-size: 10pt;">
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
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Sesso:</td>
                <td style="padding: 0.2cm;">{{gender}}</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Stato:</td>
                <td style="padding: 0.2cm;">{{member_status}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Data Iscrizione:</td>
                <td style="padding: 0.2cm;">{{registration_date}}</td>
            </tr>
        </table>

        <h2 style="margin-top: 0.8cm; font-size: 14pt; color: #555;">Contatti</h2>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-bottom: 1cm; font-size: 10pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; text-align: left;">Tipo</th>
                    <th style="padding: 0.3cm; text-align: left;">Valore</th>
                </tr>
            </thead>
            <tbody>
                {{#each junior_member_contacts}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{contact_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{value}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>

        <h2 style="margin-top: 0.8cm; font-size: 14pt; color: #555;">Indirizzi</h2>
        {{#each junior_member_addresses}}
        <div style="border: 1px solid #ccc; padding: 0.5cm; margin-bottom: 0.5cm; background: #f9f9f9;">
            <p><strong>{{address_type}}:</strong> {{street}} {{number}}, {{cap}} {{city}} ({{province}})</p>
        </div>
        {{/each}}

        <h2 style="margin-top: 0.8cm; font-size: 14pt; color: #555;">Tutori/Genitori</h2>
        {{#each junior_member_guardians}}
        <div style="border: 2px solid #333; padding: 0.5cm; margin-bottom: 0.5cm; background: #fffde7;">
            <h3 style="margin: 0 0 0.3cm 0; font-size: 12pt; color: #333;">{{guardian_type}}</h3>
            <table style="width: 100%; border-collapse: collapse; font-size: 10pt;">
                <tr>
                    <td style="width: 30%; padding: 0.2cm; font-weight: bold;">Nome:</td>
                    <td style="padding: 0.2cm;">{{first_name}} {{last_name}}</td>
                </tr>
                <tr style="background: #f5f5f5;">
                    <td style="padding: 0.2cm; font-weight: bold;">Codice Fiscale:</td>
                    <td style="padding: 0.2cm;">{{tax_code}}</td>
                </tr>
                <tr>
                    <td style="padding: 0.2cm; font-weight: bold;">Telefono:</td>
                    <td style="padding: 0.2cm;">{{phone}}</td>
                </tr>
                <tr style="background: #f5f5f5;">
                    <td style="padding: 0.2cm; font-weight: bold;">Cellulare:</td>
                    <td style="padding: 0.2cm;">{{mobile}}</td>
                </tr>
                <tr>
                    <td style="padding: 0.2cm; font-weight: bold;">Email:</td>
                    <td style="padding: 0.2cm;">{{email}}</td>
                </tr>
            </table>
        </div>
        {{/each}}

        <h2 style="margin-top: 0.8cm; font-size: 14pt; color: #555;">Informazioni Scolastiche</h2>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 1cm; font-size: 10pt;">
            <tr>
                <td style="width: 30%; padding: 0.2cm; font-weight: bold;">Scuola:</td>
                <td style="padding: 0.2cm;">{{school_name}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Classe:</td>
                <td style="padding: 0.2cm;">{{school_grade}}</td>
            </tr>
        </table>

        <h2 style="margin-top: 0.8cm; font-size: 14pt; color: #555;">Informazioni Sanitarie</h2>
        {{#each junior_member_health}}
        <div style="border: 1px solid #d9534f; padding: 0.5cm; margin-bottom: 0.5cm; background: #fff5f5;">
            <p style="margin: 0;"><strong>Allergie:</strong> {{allergies}}</p>
            <p style="margin: 0;"><strong>Intolleranze:</strong> {{intolerances}}</p>
            <p style="margin: 0;"><strong>Note Mediche:</strong> {{medical_notes}}</p>
        </div>
        {{/each}}

        <h2 style="margin-top: 0.8cm; font-size: 14pt; color: #555;">Note</h2>
        <div style="border: 1px solid #ccc; padding: 0.5cm; min-height: 2cm; background: #f9f9f9;">
            {{notes}}
        </div>
    </div>',
    'body { font-family: Arial, sans-serif; font-size: 11pt; }
    h1 { font-size: 18pt; color: #333; }
    h2 { font-size: 14pt; color: #555; margin-top: 0.8cm; margin-bottom: 0.3cm; }
    h3 { font-size: 12pt; }
    table { font-size: 10pt; }
    @media print { 
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .page-break { page-break-after: always; }
    }',
    '["junior_member_contacts", "junior_member_addresses", "junior_member_guardians", "junior_member_health"]',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 0.3cm; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}}</h2>
    </div>',
    '<div style="text-align: center; margin-top: 0.5cm; padding-top: 0.3cm; border-top: 1px solid #ccc; font-size: 9pt;">
        Stampato il {{current_date}}
    </div>',
    1, 0
);

-- Template 6: Libro Soci Cadetti
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `filter_config`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Libro Soci Cadetti',
    'Elenco completo di tutti i soci minorenni con tutti i campi principali',
    'list', 'all', 'junior_members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">LIBRO SOCI MINORENNI</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 9pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000;">Matr.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Cognome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Nome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Data Nasc.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">C.F.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Stato</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Iscr.</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr style="{{#if @even}}background: #f5f5f5;{{/if}}">
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{registration_number}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{last_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{first_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{birth_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{tax_code}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{member_status}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{registration_date}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    '{"filters": [{"field": "member_status", "label": "Stato Socio", "type": "select"}]}',
    'A4', 'landscape', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}} - Libro Soci Minorenni</h2>
        <p style="margin: 0; font-size: 10pt;">Stampato il {{current_date}}</p>
    </div>',
    1, 1
);

-- Template 7: Elenco Cadetti con Codice Fiscale
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `filter_config`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Elenco Cadetti con Codice Fiscale',
    'Elenco cadetti con matricola, cognome, nome, data nascita, codice fiscale',
    'list', 'filtered', 'junior_members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm; font-size: 16pt;">ELENCO CADETTI CON CODICE FISCALE</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 9pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.2cm; border: 1px solid #000;">Matr.</th>
                    <th style="padding: 0.2cm; border: 1px solid #000;">Cognome</th>
                    <th style="padding: 0.2cm; border: 1px solid #000;">Nome</th>
                    <th style="padding: 0.2cm; border: 1px solid #000;">Data Nasc.</th>
                    <th style="padding: 0.2cm; border: 1px solid #000;">Codice Fiscale</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr style="{{#if @even}}background: #f5f5f5;{{/if}}">
                    <td style="padding: 0.15cm; border: 1px solid #ccc;">{{registration_number}}</td>
                    <td style="padding: 0.15cm; border: 1px solid #ccc;">{{last_name}}</td>
                    <td style="padding: 0.15cm; border: 1px solid #ccc;">{{first_name}}</td>
                    <td style="padding: 0.15cm; border: 1px solid #ccc;">{{birth_date}}</td>
                    <td style="padding: 0.15cm; border: 1px solid #ccc;">{{tax_code}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    '{"filters": [{"field": "member_status", "label": "Stato", "type": "select", "options": ["attivo", "sospeso"]}]}',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}}</h2>
        <p style="margin: 0; font-size: 10pt;">Stampato il {{current_date}}</p>
    </div>',
    1, 0
);

-- Template 8: Elenco Cadetti con Recapiti
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `filter_config`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Elenco Cadetti con Recapiti',
    'Elenco cadetti con matricola, cognome, nome, cellulare, email, telefono tutore',
    'list', 'filtered', 'junior_members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ELENCO CADETTI CON RECAPITI</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 9pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000;">Matr.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Cognome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Nome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Cellulare</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Email</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Tel. Tutore</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr style="{{#if @even}}background: #f5f5f5;{{/if}}">
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{registration_number}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{last_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{first_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{mobile}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc; font-size: 8pt;">{{email}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{guardian_phone}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    '{"filters": [{"field": "member_status", "label": "Stato", "type": "select", "options": ["attivo", "sospeso"]}]}',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}} - Contatti Cadetti</h2>
        <p style="margin: 0; font-size: 10pt;">Stampato il {{current_date}}</p>
    </div>',
    1, 0
);


-- =============================================
-- MEETINGS TEMPLATES (2 templates)
-- =============================================

-- Template 9: Elenco Riunioni
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `filter_config`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Elenco Riunioni',
    'Elenco riunioni con data, tipo, titolo, luogo, stato, numero partecipanti',
    'list', 'all', 'meetings',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ELENCO RIUNIONI</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 9pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000;">Data</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Tipo Riunione</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Titolo</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Luogo</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Stato</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">N. Part.</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr style="{{#if @even}}background: #f5f5f5;{{/if}}">
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{meeting_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{meeting_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{title}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{location}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{status}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc; text-align: center;">{{participant_count}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    '{"filters": [{"field": "status", "label": "Stato", "type": "select"}, {"field": "meeting_type", "label": "Tipo", "type": "select"}]}',
    'A4', 'landscape', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}} - Elenco Riunioni</h2>
        <p style="margin: 0; font-size: 10pt;">Stampato il {{current_date}}</p>
    </div>',
    1, 0
);

-- Template 10: Verbale Riunione Assemblea
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `footer_content`, `is_active`, `is_default`
) VALUES (
    'Verbale Riunione Assemblea',
    'Verbale formale con intestazione, partecipanti, ordine del giorno con votazioni, resoconto e spazi per firme',
    'relational', 'single', 'meetings',
    '<div style="margin: 1.5cm;">
        <div style="text-align: center; margin-bottom: 1cm;">
            <h1 style="font-size: 20pt; margin: 0;">VERBALE DI ASSEMBLEA</h1>
        </div>

        <div style="margin-bottom: 1cm; border: 2px solid #333; padding: 0.5cm; background: #f9f9f9;">
            <table style="width: 100%; border-collapse: collapse; font-size: 11pt;">
                <tr>
                    <td style="width: 25%; padding: 0.2cm; font-weight: bold;">Data:</td>
                    <td style="padding: 0.2cm;">{{meeting_date}}</td>
                </tr>
                <tr>
                    <td style="padding: 0.2cm; font-weight: bold;">Ora Inizio:</td>
                    <td style="padding: 0.2cm;">{{start_time}}</td>
                </tr>
                <tr>
                    <td style="padding: 0.2cm; font-weight: bold;">Tipo:</td>
                    <td style="padding: 0.2cm;">{{meeting_type}}</td>
                </tr>
                <tr>
                    <td style="padding: 0.2cm; font-weight: bold;">Luogo:</td>
                    <td style="padding: 0.2cm;">{{location}}</td>
                </tr>
            </table>
        </div>

        <h2 style="font-size: 14pt; border-bottom: 2px solid #333; padding-bottom: 0.2cm;">PARTECIPANTI</h2>
        <table style="width: 100%; border-collapse: collapse; margin-top: 0.5cm; margin-bottom: 1cm; font-size: 10pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000; text-align: left;">Nome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; text-align: left;">Ruolo</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; text-align: center;">Presente</th>
                </tr>
            </thead>
            <tbody>
                {{#each meeting_participants}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{participant_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{role}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc; text-align: center;">{{#if present}}SI{{else}}NO{{/if}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>

        <h2 style="font-size: 14pt; border-bottom: 2px solid #333; padding-bottom: 0.2cm;">ORDINE DEL GIORNO</h2>
        {{#each meeting_agenda}}
        <div style="margin-top: 0.8cm; margin-bottom: 0.8cm;">
            <h3 style="font-size: 12pt; margin: 0 0 0.3cm 0;">{{order_number}}. {{subject}}</h3>
            <p style="margin-left: 0.5cm; margin-bottom: 0.3cm;">{{description}}</p>
            {{#if has_voting}}
            <div style="margin-left: 0.5cm; background: #fffde7; border: 1px solid #ffc107; padding: 0.3cm;">
                <strong>VOTAZIONE:</strong> Favorevoli: {{voting_in_favor}}, Contrari: {{voting_against}}, Astenuti: {{voting_abstentions}} - 
                <strong>Esito: {{voting_result}}</strong>
            </div>
            {{/if}}
        </div>
        {{/each}}

        <h2 style="font-size: 14pt; border-bottom: 2px solid #333; padding-bottom: 0.2cm; margin-top: 1.5cm;">RESOCONTO</h2>
        <div style="border: 1px solid #ccc; padding: 0.5cm; min-height: 3cm; background: #f9f9f9; margin-top: 0.5cm;">
            {{description}}
        </div>

        <div style="margin-top: 2cm; page-break-inside: avoid;">
            <table style="width: 100%; font-size: 11pt;">
                <tr>
                    <td style="width: 50%; vertical-align: top;">
                        <p><strong>Il Segretario</strong></p>
                        <p style="margin-top: 2cm; border-bottom: 1px solid #000; width: 80%;">_________________________</p>
                    </td>
                    <td style="width: 50%; text-align: right; vertical-align: top;">
                        <p><strong>Il Presidente</strong></p>
                        <p style="margin-top: 2cm; border-bottom: 1px solid #000; width: 80%; display: inline-block;">_________________________</p>
                    </td>
                </tr>
            </table>
        </div>
    </div>',
    'body { font-family: "Times New Roman", serif; font-size: 12pt; line-height: 1.6; }
    h1 { font-size: 20pt; }
    h2 { font-size: 14pt; color: #333; margin-top: 1cm; }
    h3 { font-size: 12pt; }
    @media print { 
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .page-break { page-break-after: always; }
    }',
    '["meeting_participants", "meeting_agenda"]',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 0.3cm; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}}</h2>
    </div>',
    '<div style="text-align: center; margin-top: 0.5cm; padding-top: 0.3cm; border-top: 1px solid #ccc; font-size: 9pt;">
        Documento generato il {{current_date}}
    </div>',
    1, 1
);

-- =============================================
-- VEHICLES TEMPLATES (2 templates)
-- =============================================

-- Template 11: Scheda Mezzo Completa
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `footer_content`, `is_active`, `is_default`
) VALUES (
    'Scheda Mezzo Completa',
    'Scheda completa del mezzo con dati identificativi, stato, scadenze (assicurazione, revisione, bollo), storico manutenzioni',
    'relational', 'single', 'vehicles',
    '<div style="margin: 1cm;">
        <h1 style="border-bottom: 3px solid #333; padding-bottom: 0.3cm; color: #333; font-size: 18pt;">SCHEDA MEZZO COMPLETA</h1>
        
        <h2 style="margin-top: 0.8cm; font-size: 14pt; color: #555;">Dati Identificativi</h2>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 1cm; font-size: 10pt;">
            <tr>
                <td style="width: 30%; padding: 0.2cm; font-weight: bold;">Targa:</td>
                <td style="padding: 0.2cm;">{{license_plate}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Tipo:</td>
                <td style="padding: 0.2cm;">{{vehicle_type}}</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Marca:</td>
                <td style="padding: 0.2cm;">{{brand}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Modello:</td>
                <td style="padding: 0.2cm;">{{model}}</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Anno:</td>
                <td style="padding: 0.2cm;">{{year}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Numero Telaio:</td>
                <td style="padding: 0.2cm;">{{serial_number}}</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Stato:</td>
                <td style="padding: 0.2cm;"><strong>{{status}}</strong></td>
            </tr>
        </table>

        <h2 style="margin-top: 0.8cm; font-size: 14pt; color: #555;">Scadenze</h2>
        <div style="border: 2px solid #d9534f; padding: 0.5cm; margin-bottom: 1cm; background: #fff5f5;">
            <table style="width: 100%; border-collapse: collapse; font-size: 10pt;">
                <tr>
                    <td style="width: 40%; padding: 0.2cm; font-weight: bold;">Scadenza Assicurazione:</td>
                    <td style="padding: 0.2cm; color: #d9534f; font-weight: bold;">{{insurance_expiry}}</td>
                </tr>
                <tr>
                    <td style="padding: 0.2cm; font-weight: bold;">Scadenza Revisione:</td>
                    <td style="padding: 0.2cm; color: #d9534f; font-weight: bold;">{{inspection_expiry}}</td>
                </tr>
            </table>
        </div>

        <h2 style="margin-top: 0.8cm; font-size: 14pt; color: #555;">Storico Manutenzioni</h2>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-bottom: 1cm; font-size: 10pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; text-align: left;">Data</th>
                    <th style="padding: 0.3cm; text-align: left;">Tipo</th>
                    <th style="padding: 0.3cm; text-align: left;">Descrizione</th>
                    <th style="padding: 0.3cm; text-align: right;">Costo</th>
                </tr>
            </thead>
            <tbody>
                {{#each vehicle_maintenance}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{maintenance_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{description}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc; text-align: right;">€ {{cost}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>

        <h2 style="margin-top: 0.8cm; font-size: 14pt; color: #555;">Note</h2>
        <div style="border: 1px solid #ccc; padding: 0.5cm; min-height: 2cm; background: #f9f9f9;">
            {{notes}}
        </div>
    </div>',
    'body { font-family: Arial, sans-serif; font-size: 11pt; }
    h1 { font-size: 18pt; color: #333; }
    h2 { font-size: 14pt; color: #555; margin-top: 0.8cm; margin-bottom: 0.3cm; }
    table { font-size: 10pt; }
    @media print { 
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .page-break { page-break-after: always; }
    }',
    '["vehicle_maintenance"]',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 0.3cm; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}}</h2>
    </div>',
    '<div style="text-align: center; margin-top: 0.5cm; padding-top: 0.3cm; border-top: 1px solid #ccc; font-size: 9pt;">
        Stampato il {{current_date}}
    </div>',
    1, 0
);

-- Template 12: Elenco Mezzi con Scadenze
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `filter_config`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Elenco Mezzi con Scadenze',
    'Elenco mezzi con targa, tipo, marca/modello, stato, scadenze assicurazione, revisione - evidenzia scadenze entro 30 giorni',
    'list', 'all', 'vehicles',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ELENCO MEZZI CON SCADENZE</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 9pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000;">Targa</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Tipo</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Marca/Modello</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Stato</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Scad. Assic.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Scad. Revisione</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr style="{{#if @even}}background: #f5f5f5;{{/if}}">
                    <td style="padding: 0.2cm; border: 1px solid #ccc;"><strong>{{license_plate}}</strong></td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{vehicle_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{brand}} {{model}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{status}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc; {{#if insurance_expiring}}color: #d9534f; font-weight: bold;{{/if}}">{{insurance_expiry}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc; {{#if inspection_expiring}}color: #d9534f; font-weight: bold;{{/if}}">{{inspection_expiry}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>

        <div style="margin-top: 1cm; padding: 0.5cm; background: #fff5f5; border: 2px solid #d9534f;">
            <p style="margin: 0; font-size: 10pt;"><strong>LEGENDA:</strong> Le date in <span style="color: #d9534f; font-weight: bold;">rosso</span> indicano scadenze entro 30 giorni</p>
        </div>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    '{"filters": [{"field": "status", "label": "Stato", "type": "select"}]}',
    'A4', 'landscape', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}} - Elenco Mezzi con Scadenze</h2>
        <p style="margin: 0; font-size: 10pt;">Stampato il {{current_date}}</p>
    </div>',
    1, 0
);

-- =============================================
-- EVENTS TEMPLATES (2 templates)
-- =============================================

-- Template 13: Scheda Evento Dettagliata
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `footer_content`, `is_active`, `is_default`
) VALUES (
    'Scheda Evento Dettagliata',
    'Scheda completa evento con intestazione, descrizione, interventi, volontari partecipanti, mezzi impiegati',
    'relational', 'single', 'events',
    '<div style="margin: 1cm;">
        <h1 style="border-bottom: 3px solid #333; padding-bottom: 0.3cm; color: #333; font-size: 18pt;">SCHEDA EVENTO DETTAGLIATA</h1>
        
        <h2 style="margin-top: 0.8cm; font-size: 14pt; color: #555;">Informazioni Generali</h2>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 1cm; font-size: 10pt;">
            <tr>
                <td style="width: 25%; padding: 0.2cm; font-weight: bold;">Tipo Evento:</td>
                <td style="padding: 0.2cm;">{{event_type}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Titolo:</td>
                <td style="padding: 0.2cm;"><strong>{{title}}</strong></td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Data Inizio:</td>
                <td style="padding: 0.2cm;">{{start_date}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Data Fine:</td>
                <td style="padding: 0.2cm;">{{end_date}}</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Luogo:</td>
                <td style="padding: 0.2cm;">{{location}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Stato:</td>
                <td style="padding: 0.2cm;">{{status}}</td>
            </tr>
        </table>

        <h2 style="margin-top: 0.8cm; font-size: 14pt; color: #555;">Descrizione</h2>
        <div style="border: 1px solid #ccc; padding: 0.5cm; margin-bottom: 1cm; background: #f9f9f9;">
            {{description}}
        </div>

        <h2 style="margin-top: 0.8cm; font-size: 14pt; color: #555;">Interventi</h2>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-bottom: 1cm; font-size: 10pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; text-align: left;">Data/Ora</th>
                    <th style="padding: 0.3cm; text-align: left;">Descrizione</th>
                    <th style="padding: 0.3cm; text-align: left;">Luogo</th>
                </tr>
            </thead>
            <tbody>
                {{#each interventions}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{start_time}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{description}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{location}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>

        <h2 style="margin-top: 0.8cm; font-size: 14pt; color: #555;">Volontari Partecipanti</h2>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-bottom: 1cm; font-size: 10pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; text-align: left;">Matricola</th>
                    <th style="padding: 0.3cm; text-align: left;">Nome</th>
                    <th style="padding: 0.3cm; text-align: left;">Ruolo</th>
                    <th style="padding: 0.3cm; text-align: right;">Ore</th>
                </tr>
            </thead>
            <tbody>
                {{#each event_participants}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{member_registration_number}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{member_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{role}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc; text-align: right;">{{hours}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>

        <h2 style="margin-top: 0.8cm; font-size: 14pt; color: #555;">Mezzi Impiegati</h2>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-bottom: 1cm; font-size: 10pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; text-align: left;">Targa</th>
                    <th style="padding: 0.3cm; text-align: left;">Tipo</th>
                    <th style="padding: 0.3cm; text-align: right;">KM Percorsi</th>
                    <th style="padding: 0.3cm; text-align: right;">Ore Utilizzo</th>
                </tr>
            </thead>
            <tbody>
                {{#each event_vehicles}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{vehicle_license_plate}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{vehicle_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc; text-align: right;">{{km_traveled}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc; text-align: right;">{{hours}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>',
    'body { font-family: Arial, sans-serif; font-size: 11pt; }
    h1 { font-size: 18pt; color: #333; }
    h2 { font-size: 14pt; color: #555; margin-top: 0.8cm; margin-bottom: 0.3cm; }
    table { font-size: 10pt; }
    @media print { 
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .page-break { page-break-after: always; }
    }',
    '["interventions", "event_participants", "event_vehicles"]',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 0.3cm; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}}</h2>
    </div>',
    '<div style="text-align: center; margin-top: 0.5cm; padding-top: 0.3cm; border-top: 1px solid #ccc; font-size: 9pt;">
        Stampato il {{current_date}}
    </div>',
    1, 0
);

-- Template 14: Elenco Eventi
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `filter_config`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Elenco Eventi',
    'Elenco completo eventi con tipo, titolo, date, luogo, stato',
    'list', 'all', 'events',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ELENCO EVENTI</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 9pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000;">Tipo</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Titolo</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Data Inizio</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Data Fine</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Luogo</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Stato</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr style="{{#if @even}}background: #f5f5f5;{{/if}}">
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{event_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;"><strong>{{title}}</strong></td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{start_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{end_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{location}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{status}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    '{"filters": [{"field": "event_type", "label": "Tipo Evento", "type": "select"}, {"field": "status", "label": "Stato", "type": "select"}]}',
    'A4', 'landscape', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}} - Elenco Eventi</h2>
        <p style="margin: 0; font-size: 10pt;">Stampato il {{current_date}}</p>
    </div>',
    1, 0
);

COMMIT;
