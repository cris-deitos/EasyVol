-- =============================================
-- SEED FILE: Print Templates for Junior Members (Cadetti)
-- =============================================
-- This file contains print templates for junior members section
-- =============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- =============================================
-- JUNIOR MEMBERS TEMPLATES
-- =============================================

-- 1. Libro Soci Cadetti (list)
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
                    <th style="padding: 0.3cm; border: 1px solid #000;">Tutore</th>
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
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{guardian_last_name}} {{guardian_first_name}}</td>
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
    </div>',
    1, 1
);

-- 2. Elenco Contatti Cadetti (list)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `filter_config`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Elenco Contatti Cadetti',
    'Elenco soci minorenni con contatti del tutore per comunicazioni rapide',
    'list', 'filtered', 'junior_members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ELENCO CONTATTI SOCI MINORENNI</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 10pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000;">Cognome Nome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Data Nasc.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Tutore</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Telefono Tutore</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Email Tutore</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;"><strong>{{last_name}} {{first_name}}</strong></td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{birth_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{guardian_last_name}} {{guardian_first_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{guardian_phone}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc; font-size: 9pt;">{{guardian_email}}</td>
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
        <h2 style="margin: 0;">{{association_name}} - Contatti Soci Minorenni</h2>
        <p style="margin: 0; font-size: 10pt;">Stampato il {{current_date}}</p>
    </div>',
    1, 0
);

-- 3. Foglio Firma Cadetti (list)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `filter_config`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Foglio Firma Cadetti',
    'Foglio firme per presenza cadetti ad attività o eventi',
    'list', 'filtered', 'junior_members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">FOGLIO PRESENZE CADETTI</h1>
        
        <div style="margin: 1cm 0;">
            <p><strong>Attività:</strong> _______________________________________</p>
            <p><strong>Data:</strong> ______________________ <strong>Luogo:</strong> ______________________</p>
            <p><strong>Responsabile:</strong> _______________________________________</p>
        </div>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 11pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000; width: 5%;">N.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; width: 15%;">Matricola</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; width: 30%;">Cognome e Nome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; width: 50%;">Firma</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.4cm; border: 1px solid #ccc; text-align: center;">{{@index_plus_1}}</td>
                    <td style="padding: 0.4cm; border: 1px solid #ccc;">{{registration_number}}</td>
                    <td style="padding: 0.4cm; border: 1px solid #ccc;"><strong>{{last_name}} {{first_name}}</strong></td>
                    <td style="padding: 0.4cm; border: 1px solid #ccc;">&nbsp;</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
        
        <div style="margin-top: 2cm;">
            <p><strong>Firma del Responsabile:</strong> _______________________________________</p>
        </div>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    '{"filters": [{"field": "member_status", "label": "Stato", "type": "select", "options": ["attivo"]}]}',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}}</h2>
    </div>',
    1, 0
);

COMMIT;
