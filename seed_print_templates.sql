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

COMMIT;
