-- =============================================
-- SEED FILE: Default Print Templates for EasyVol
-- =============================================
-- This file contains all default print templates for a fresh installation
-- Run this file if print templates are missing from the database
--
-- Usage: mysql -u username -p database_name < seed_print_templates.sql
-- Or import via phpMyAdmin or other database management tool
-- =============================================

-- First, check if templates already exist to avoid duplicates
-- If you want to reset all templates, manually DELETE FROM print_templates; first

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
    `show_header`, `show_footer`, `is_active`, `is_default`
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
    'body { font-family: Arial, sans-serif; }
    @media print {
        @page { size: 8.5cm 5.4cm; margin: 0; }
        body { margin: 0; }
    }',
    'custom', 'landscape', 0, 0, 1, 1
);

-- 2. Scheda Socio (relational)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Scheda Socio',
    'Scheda completa del socio con tutti i dati',
    'relational', 'single', 'members',
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
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Sesso:</td>
                <td style="padding: 0.2cm;">{{gender}}</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Nazionalità:</td>
                <td style="padding: 0.2cm;">{{nationality}}</td>
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

        <h2>Contatti</h2>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-bottom: 1cm;">
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

        <h2>Indirizzi</h2>
        {{#each member_addresses}}
        <div style="border: 1px solid #ccc; padding: 0.5cm; margin-bottom: 0.5cm; background: #f9f9f9;">
            <p><strong>{{address_type}}:</strong> {{street}} {{number}}, {{cap}} {{city}} ({{province}})</p>
        </div>
        {{/each}}

        <h2>Patenti</h2>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-bottom: 1cm;">
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

        <h2>Corsi e Formazione</h2>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc;">
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
    </div>',
    'body { font-family: Arial, sans-serif; font-size: 11pt; }
    h1 { font-size: 18pt; color: #333; }
    h2 { font-size: 14pt; color: #555; margin-top: 0.8cm; margin-bottom: 0.3cm; }
    table { font-size: 10pt; }
    @media print { 
        .page-break { page-break-after: always; }
    }',
    '["member_contacts", "member_addresses", "member_licenses", "member_courses"]',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 0.3cm; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}}</h2>
    </div>',
    1, 0
);

-- 3. Attestato di Partecipazione (single)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `is_active`, `is_default`
) VALUES (
    'Attestato di Partecipazione',
    'Attestato di partecipazione per un socio',
    'single', 'single', 'members',
    '<div style="margin: 2cm; text-align: center;">
        <h1 style="font-size: 28pt; color: #333; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ATTESTATO DI PARTECIPAZIONE</h1>
        
        <div style="margin-top: 3cm; font-size: 16pt; line-height: 2;">
            <p>Si attesta che</p>
            
            <p style="font-size: 22pt; font-weight: bold; margin: 1cm 0;">
                {{first_name}} {{last_name}}
            </p>
            
            <p>Matricola: {{registration_number}}</p>
            
            <p style="margin-top: 2cm;">
                ha partecipato all\'evento/attività
            </p>
            
            <p style="font-size: 18pt; font-weight: bold; margin: 1cm 0;">
                _____________________________________________
            </p>
            
            <p>in data ___ / ___ / ______</p>
        </div>
        
        <div style="margin-top: 3cm; display: flex; justify-content: space-between; font-size: 12pt;">
            <div style="width: 45%;">
                <p><strong>Data:</strong> {{current_date}}</p>
            </div>
            <div style="width: 45%; text-align: right;">
                <p><strong>Il Presidente</strong></p>
                <p style="margin-top: 2cm;">_______________________</p>
            </div>
        </div>
    </div>',
    'body { font-family: "Times New Roman", serif; }
    @page { size: A4 landscape; }',
    'A4', 'landscape', 0, 0, 1, 0
);

-- 4. Libro Soci (list)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `filter_config`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Libro Soci',
    'Elenco completo di tutti i soci con tutti i campi principali',
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
                <tr>
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
    </div>',
    1, 1
);

-- 5. Tessere Multiple (multi_page)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `is_active`, `is_default`
) VALUES (
    'Tessere Multiple',
    'Genera più tessere, una per pagina, per stampare in blocco',
    'multi_page', 'filtered', 'members',
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
    'body { font-family: Arial, sans-serif; }
    @media print {
        @page { size: 8.5cm 5.4cm; margin: 0; }
        body { margin: 0; }
    }',
    'custom', 'landscape', 0, 0, 1, 0
);

-- =============================================
-- VEHICLES TEMPLATES
-- =============================================

-- 6. Scheda Mezzo (relational)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Scheda Mezzo',
    'Scheda completa del mezzo con storico manutenzioni',
    'relational', 'single', 'vehicles',
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
                    <th style="padding: 0.3cm; text-align: left; border: 1px solid #000;">Data</th>
                    <th style="padding: 0.3cm; text-align: left; border: 1px solid #000;">Tipo</th>
                    <th style="padding: 0.3cm; text-align: left; border: 1px solid #000;">Descrizione</th>
                    <th style="padding: 0.3cm; text-align: left; border: 1px solid #000;">Costo</th>
                </tr>
            </thead>
            <tbody>
                {{#each vehicle_maintenance}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{date}}</td>
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
    h2 { font-size: 14pt; color: #555; margin-top: 0.8cm; margin-bottom: 0.3cm; }
    table { font-size: 10pt; }',
    '["vehicle_maintenance"]',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 0.3cm; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}} - Parco Mezzi</h2>
    </div>',
    1, 1
);

-- 7. Elenco Mezzi (list)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Elenco Mezzi',
    'Lista completa dei mezzi dell\'associazione',
    'list', 'all', 'vehicles',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ELENCO MEZZI</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000; text-align: left;">Targa</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; text-align: left;">Tipo</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; text-align: left;">Marca/Modello</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; text-align: left;">Anno</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; text-align: left;">Stato</th>
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
    </div>',
    'body { font-family: Arial, sans-serif; font-size: 11pt; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}}</h2>
    </div>',
    1, 0
);

-- =============================================
-- MEETINGS TEMPLATES
-- =============================================

-- 8. Verbale Riunione (relational)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `footer_content`, `is_active`, `is_default`
) VALUES (
    'Verbale di Riunione',
    'Verbale ufficiale della riunione con partecipanti',
    'relational', 'single', 'meetings',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">VERBALE DI RIUNIONE</h1>
        
        <div style="margin-top: 1cm;">
            <p><strong>Data:</strong> {{meeting_date}}</p>
            <p><strong>Tipo:</strong> {{meeting_type}}</p>
            <p><strong>Luogo:</strong> {{location}}</p>
        </div>

        <h2 style="margin-top: 1cm;">Partecipanti</h2>
        <ul>
            {{#each meeting_participants}}
            <li>{{participant_name}} - {{role}}</li>
            {{/each}}
        </ul>

        <h2 style="margin-top: 1cm;">Ordine del Giorno</h2>
        <ol>
            {{#each meeting_agenda}}
            <li style="margin-bottom: 0.5cm;">
                <strong>{{subject}}</strong>
                <p style="margin-left: 1cm;">{{description}}</p>
            </li>
            {{/each}}
        </ol>

        <h2 style="margin-top: 1cm;">Resoconto</h2>
        <div style="border: 1px solid #ccc; padding: 0.5cm; min-height: 5cm; background: #f9f9f9;">
            {{description}}
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
    h2 { font-size: 14pt; color: #333; }
    @media print { 
        .page-break { page-break-after: always; }
    }',
    '["meeting_participants", "meeting_agenda"]',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 0.3cm; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}}</h2>
    </div>',
    '<div style="text-align: center; margin-top: 0.5cm; padding-top: 0.3cm; border-top: 1px solid #ccc; font-size: 9pt;">
        Pagina {{page}} - Documento generato il {{current_date}}
    </div>',
    1, 1
);

-- 9. Foglio Presenze Riunione (relational)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Foglio Presenze Riunione',
    'Foglio firme per la presenza alla riunione',
    'relational', 'single', 'meetings',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">FOGLIO PRESENZE</h1>
        
        <div style="margin-top: 1cm; margin-bottom: 1cm;">
            <p><strong>Riunione del:</strong> {{meeting_date}}</p>
            <p><strong>Luogo:</strong> {{location}}</p>
            <p><strong>Tipo:</strong> {{meeting_type}}</p>
        </div>

        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.5cm; border: 1px solid #000; text-align: left; width: 5%;">N.</th>
                    <th style="padding: 0.5cm; border: 1px solid #000; text-align: left; width: 35%;">Nome e Cognome</th>
                    <th style="padding: 0.5cm; border: 1px solid #000; text-align: left; width: 20%;">Ruolo</th>
                    <th style="padding: 0.5cm; border: 1px solid #000; text-align: center; width: 40%;">Firma</th>
                </tr>
            </thead>
            <tbody>
                {{#each meeting_participants}}
                <tr>
                    <td style="padding: 0.5cm; border: 1px solid #ccc; height: 1.5cm;">{{@index}}</td>
                    <td style="padding: 0.5cm; border: 1px solid #ccc;">{{participant_name}}</td>
                    <td style="padding: 0.5cm; border: 1px solid #ccc;">{{role}}</td>
                    <td style="padding: 0.5cm; border: 1px solid #ccc;"></td>
                </tr>
                {{/each}}
                <!-- Extra rows for additional attendees -->
                <tr>
                    <td style="padding: 0.5cm; border: 1px solid #ccc; height: 1.5cm;"></td>
                    <td style="padding: 0.5cm; border: 1px solid #ccc;"></td>
                    <td style="padding: 0.5cm; border: 1px solid #ccc;"></td>
                    <td style="padding: 0.5cm; border: 1px solid #ccc;"></td>
                </tr>
                <tr>
                    <td style="padding: 0.5cm; border: 1px solid #ccc; height: 1.5cm;"></td>
                    <td style="padding: 0.5cm; border: 1px solid #ccc;"></td>
                    <td style="padding: 0.5cm; border: 1px solid #ccc;"></td>
                    <td style="padding: 0.5cm; border: 1px solid #ccc;"></td>
                </tr>
            </tbody>
        </table>
    </div>',
    'body { font-family: Arial, sans-serif; font-size: 11pt; }
    h1 { font-size: 18pt; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    '["meeting_participants"]',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 0.3cm; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}}</h2>
    </div>',
    1, 0
);

-- 10. Elenco Eventi (list)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
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
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    'A4', 'landscape', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}} - Registro Eventi</h2>
        <p style="margin: 0;">Data: {{current_date}}</p>
    </div>',
    1, 1
);

COMMIT;

-- =============================================
-- END OF SEED FILE
-- =============================================
