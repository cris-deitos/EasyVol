-- Default Print Templates
-- Description: Inserts default templates for members, vehicles, and meetings
-- Date: 2025-12-07

-- =============================================
-- MEMBERS TEMPLATES
-- =============================================

-- 1. Certificato Iscrizione (single)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `footer_content`,
    `is_active`, `is_default`
) VALUES (
    'Certificato di Iscrizione',
    'Certificato ufficiale di iscrizione all\'associazione',
    'single', 'single', 'members',
    '<div style="text-align: center; margin-top: 3cm;">
        <h1 style="font-size: 24pt; margin-bottom: 2cm;">CERTIFICATO DI ISCRIZIONE</h1>
        <div style="text-align: left; margin: 0 auto; max-width: 15cm; font-size: 14pt;">
            <p style="line-height: 1.8;">Si certifica che</p>
            <p style="text-align: center; font-size: 18pt; font-weight: bold; margin: 1cm 0;">
                {{first_name}} {{last_name}}
            </p>
            <p style="line-height: 1.8;">
                nato/a a {{birth_place}} ({{birth_province}}) il {{birth_date}}<br>
                C.F. {{tax_code}}
            </p>
            <p style="line-height: 1.8; margin-top: 1cm;">
                è iscritto/a a questa Associazione con matricola <strong>{{registration_number}}</strong><br>
                dal {{registration_date}} con la qualifica di <strong>Socio {{member_type}}</strong>
            </p>
            <p style="line-height: 1.8; margin-top: 1cm;">
                Stato attuale: <strong>{{member_status}}</strong>
            </p>
        </div>
        <div style="text-align: right; margin-top: 3cm;">
            <p>Data, _______________</p>
            <p style="margin-top: 2cm;">Il Presidente</p>
            <p>___________________________</p>
        </div>
    </div>',
    'body { font-family: "Times New Roman", serif; }
    h1 { border-bottom: 2px solid #333; padding-bottom: 0.5cm; }
    @media print { 
        .page-break { page-break-after: always; }
    }',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; margin-bottom: 1cm;">
        <h2 style="margin: 0;">{{association_name}}</h2>
        <p style="margin: 0.2cm 0;">{{association_address}} - {{association_city}}</p>
    </div>',
    '<div style="text-align: center; margin-top: 1cm; font-size: 10pt; color: #666;">
        <p>Documento generato il {{current_date}}</p>
    </div>',
    1, 1
);

-- 2. Tessera Socio (single)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `is_active`, `is_default`
) VALUES (
    'Tessera Socio',
    'Tessera identificativa del socio - formato card',
    'single', 'single', 'members',
    '<div style="width: 8.5cm; height: 5.4cm; border: 2px solid #000; border-radius: 0.3cm; padding: 0.3cm; margin: 2cm auto;">
        <div style="text-align: center; border-bottom: 1px solid #ccc; padding-bottom: 0.2cm; margin-bottom: 0.3cm;">
            <strong style="font-size: 12pt;">TESSERA SOCIO</strong>
        </div>
        <table style="width: 100%; font-size: 10pt;">
            <tr>
                <td style="padding: 0.1cm;"><strong>Matricola:</strong></td>
                <td style="padding: 0.1cm;">{{registration_number}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Nome:</strong></td>
                <td style="padding: 0.1cm;">{{first_name}} {{last_name}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Nato il:</strong></td>
                <td style="padding: 0.1cm;">{{birth_date}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Tipo:</strong></td>
                <td style="padding: 0.1cm;">{{member_type}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Iscrizione:</strong></td>
                <td style="padding: 0.1cm;">{{registration_date}}</td>
            </tr>
        </table>
        <div style="text-align: center; margin-top: 0.3cm; font-size: 8pt;">
            Valida per l\'anno in corso
        </div>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print { 
        @page { margin: 0.5cm; }
        body { margin: 0; }
    }',
    'A4', 'portrait', 0, 0, 1, 0
);

-- 3. Scheda Completa Socio (relational)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Scheda Completa Socio',
    'Scheda dettagliata con tutti i dati del socio e tabelle correlate',
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

        <h2>Stato Associativo</h2>
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
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-bottom: 1cm;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; text-align: left;">Tipo</th>
                    <th style="padding: 0.3cm; text-align: left;">Indirizzo</th>
                    <th style="padding: 0.3cm; text-align: left;">Città</th>
                </tr>
            </thead>
            <tbody>
                {{#each member_addresses}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{address_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{street}} {{number}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{city}} ({{province}}) {{cap}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>

        <div style="page-break-after: always;"></div>

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

        <h2>Corsi e Qualifiche</h2>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-bottom: 1cm;">
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
    '{"member_status": ["attivo", "sospeso", "dimesso"], "member_type": ["ordinario", "fondatore"]}',
    'A4', 'landscape', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}}</h2>
    </div>',
    1, 0
);

-- 5. Elenco Telefonico (list)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Elenco Telefonico Soci',
    'Lista soci con matricola, nome e contatti',
    'list', 'filtered', 'members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ELENCO TELEFONICO</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000; text-align: left;">Matricola</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; text-align: left;">Nome e Cognome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; text-align: left;">Telefono</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; text-align: left;">Email</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{registration_number}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{first_name}} {{last_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">-</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">-</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
        
        <div style="margin-top: 1cm; font-size: 9pt; color: #666;">
            <p>Nota: Per i contatti completi consultare la scheda dettagliata di ciascun socio</p>
        </div>
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

-- 6. Tessere Multiple (multi_page)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `is_active`, `is_default`
) VALUES (
    'Tessere Soci Multiple',
    'Genera più tessere in un unico PDF (una per pagina)',
    'multi_page', 'filtered', 'members',
    '<div style="width: 8.5cm; height: 5.4cm; border: 2px solid #000; border-radius: 0.3cm; padding: 0.3cm; margin: 5cm auto;">
        <div style="text-align: center; border-bottom: 1px solid #ccc; padding-bottom: 0.2cm; margin-bottom: 0.3cm;">
            <strong style="font-size: 12pt;">TESSERA SOCIO</strong>
        </div>
        <table style="width: 100%; font-size: 10pt;">
            <tr>
                <td style="padding: 0.1cm;"><strong>Matricola:</strong></td>
                <td style="padding: 0.1cm;">{{registration_number}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Nome:</strong></td>
                <td style="padding: 0.1cm;">{{first_name}} {{last_name}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Nato il:</strong></td>
                <td style="padding: 0.1cm;">{{birth_date}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Tipo:</strong></td>
                <td style="padding: 0.1cm;">{{member_type}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Iscrizione:</strong></td>
                <td style="padding: 0.1cm;">{{registration_date}}</td>
            </tr>
        </table>
        <div style="text-align: center; margin-top: 0.3cm; font-size: 8pt;">
            Valida per l\'anno in corso
        </div>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print { 
        @page { margin: 0; }
        body { margin: 0; }
    }',
    'A4', 'portrait', 0, 0, 1, 0
);

-- =============================================
-- VEHICLES TEMPLATES
-- =============================================

-- 7. Scheda Tecnica Mezzo (relational)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Scheda Tecnica Mezzo',
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
    h2 { font-size: 14pt; color: #555; margin-top: 0.8cm; margin-bottom: 0.3cm; }
    table { font-size: 10pt; }',
    '["vehicle_maintenance"]',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 0.3cm; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}} - Parco Mezzi</h2>
    </div>',
    1, 1
);

-- 8. Elenco Mezzi (list)
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

-- 9. Verbale Riunione (relational)
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
            <p><strong>Ora:</strong> {{meeting_time}}</p>
            <p><strong>Luogo:</strong> {{location}}</p>
            <p><strong>Tipo:</strong> {{meeting_type}}</p>
        </div>

        <h2 style="margin-top: 1cm;">Partecipanti</h2>
        <ul>
            {{#each meeting_participants}}
            <li>{{member_name}} - {{role}}</li>
            {{/each}}
        </ul>

        <h2 style="margin-top: 1cm;">Ordine del Giorno</h2>
        <ol>
            {{#each meeting_agenda}}
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

-- 10. Foglio Presenze Riunione (relational)
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
                    <td style="padding: 0.5cm; border: 1px solid #ccc; height: 1.5cm;">{{index}}</td>
                    <td style="padding: 0.5cm; border: 1px solid #ccc;">{{member_name}}</td>
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

-- Note: The created_by field will be NULL as we're inserting default templates
-- In production, these should be updated to reference the admin user ID
