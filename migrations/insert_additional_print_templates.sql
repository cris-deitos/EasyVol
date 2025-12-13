-- Additional Print Templates for EasyVol
-- Description: Inserts additional standard templates for all entity types as requested
-- Date: 2025-12-13

-- =============================================
-- ADDITIONAL MEMBERS TEMPLATES
-- =============================================

-- Elenco Soci Attivi
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Elenco Soci Attivi',
    'Lista dei soci con stato attivo',
    'list', 'filtered', 'members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ELENCO SOCI ATTIVI</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 10pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000;">Matricola</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Cognome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Nome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Data Nascita</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Codice Fiscale</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Data Iscrizione</th>
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
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">Associazione - Elenco Soci Attivi</h2>
        <p style="margin: 0;">Data: {{current_date}}</p>
    </div>',
    1, 0
);

-- Elenco Soci Sospesi
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Elenco Soci Sospesi',
    'Lista dei soci sospesi, in congedo o in aspettativa',
    'list', 'filtered', 'members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ELENCO SOCI SOSPESI</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 10pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000;">Matricola</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Cognome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Nome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Stato</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Motivo</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Data</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{registration_number}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{last_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{first_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{member_status}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{volunteer_status}}</td>
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
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">Associazione - Elenco Soci Sospesi</h2>
        <p style="margin: 0;">Data: {{current_date}}</p>
    </div>',
    1, 0
);

-- Elenco Soci Attivi con Contatti
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Elenco Soci Attivi con Contatti',
    'Lista soci attivi con numeri di cellulare ed email',
    'list', 'filtered', 'members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ELENCO SOCI ATTIVI CON CONTATTI</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 9pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000;">Matr.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Cognome e Nome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Cellulare</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Email</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{registration_number}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{last_name}} {{first_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{mobile}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc; font-size: 8pt;">{{email}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
        
        <div style="margin-top: 1cm; font-size: 9pt; color: #666;">
            <p><strong>Nota:</strong> I contatti sono riservati e non possono essere divulgati a terzi senza autorizzazione.</p>
        </div>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">Associazione - Contatti Soci Attivi</h2>
        <p style="margin: 0;">Data: {{current_date}} - RISERVATO</p>
    </div>',
    1, 0
);

-- Elenco Soci Attivi con Ruoli
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Elenco Soci Attivi con Ruoli',
    'Lista soci attivi con i rispettivi ruoli ricoperti',
    'relational', 'filtered', 'members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ELENCO SOCI ATTIVI CON RUOLI</h1>
        
        <div style="margin-top: 1cm;">
            {{#each records}}
            <div style="margin-bottom: 1cm; padding: 0.5cm; border: 1px solid #ddd; background: #f9f9f9;">
                <h3 style="margin: 0; color: #333;">{{registration_number}} - {{last_name}} {{first_name}}</h3>
                <p style="margin: 0.3cm 0;"><strong>Tipo Socio:</strong> {{member_type}}</p>
                <p style="margin: 0;"><strong>Ruoli:</strong></p>
                <ul style="margin: 0.3cm 0;">
                    {{#each member_roles}}
                    <li>{{role_name}} (dal {{start_date}}{{#if end_date}} al {{end_date}}{{/if}})</li>
                    {{/each}}
                </ul>
            </div>
            {{/each}}
        </div>
    </div>',
    'body { font-family: Arial, sans-serif; font-size: 11pt; }
    h3 { font-size: 13pt; }
    @media print {
        .page-break { page-break-after: always; }
    }',
    '["member_roles"]',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">Associazione - Soci con Ruoli</h2>
        <p style="margin: 0;">Data: {{current_date}}</p>
    </div>',
    1, 0
);

-- Elenco Soci con Intolleranze o Scelte Alimentari
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Elenco Soci con Intolleranze Alimentari',
    'Lista soci con intolleranze, allergie o scelte alimentari particolari',
    'relational', 'filtered', 'members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ELENCO SOCI CON INTOLLERANZE O SCELTE ALIMENTARI</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 10pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000;">Matricola</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Cognome e Nome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Intolleranze/Allergie</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Note Salute</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{registration_number}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{last_name}} {{first_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">
                        {{#each member_health}}
                        {{allergies}}, {{food_intolerances}}
                        {{/each}}
                    </td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc; font-size: 8pt;">
                        {{#each member_health}}
                        {{health_notes}}
                        {{/each}}
                    </td>
                </tr>
                {{/each}}
            </tbody>
        </table>
        
        <div style="margin-top: 1cm; font-size: 9pt; color: #666;">
            <p><strong>Nota:</strong> Queste informazioni sono sensibili e devono essere trattate con riservatezza.</p>
        </div>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    '["member_health"]',
    'A4', 'landscape', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">Associazione - Info Alimentari RISERVATO</h2>
        <p style="margin: 0;">Data: {{current_date}}</p>
    </div>',
    1, 0
);

-- Foglio Firma Assemblea con Deleghe
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Foglio Firma Assemblea con Deleghe',
    'Foglio presenze per assemblee con colonna per delegato',
    'list', 'filtered', 'members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">FOGLIO FIRMA ASSEMBLEA</h1>
        
        <div style="margin: 1cm 0;">
            <p><strong>Data Assemblea:</strong> __________________</p>
            <p><strong>Ora:</strong> __________________</p>
            <p><strong>Luogo:</strong> __________________________________________________</p>
        </div>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 9pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000; width: 5%;">N.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; width: 10%;">Matricola</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; width: 25%;">Cognome e Nome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; width: 10%;">Quota OK</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; width: 25%;">Firma</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; width: 25%;">Delega a</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.3cm; border: 1px solid #ccc; height: 1.2cm; text-align: center;">{{@index}}</td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;">{{registration_number}}</td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;">{{last_name}} {{first_name}}</td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc; text-align: center;">☐</td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;"></td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;"></td>
                </tr>
                {{/each}}
                <!-- Righe vuote per ospiti -->
                <tr><td colspan="6" style="padding: 0.3cm; background: #f0f0f0; font-weight: bold;">Ospiti/Altri</td></tr>
                <tr>
                    <td style="padding: 0.3cm; border: 1px solid #ccc; height: 1.2cm;"></td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;"></td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;"></td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;"></td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;"></td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;"></td>
                </tr>
                <tr>
                    <td style="padding: 0.3cm; border: 1px solid #ccc; height: 1.2cm;"></td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;"></td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;"></td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;"></td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;"></td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;"></td>
                </tr>
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
        <h2 style="margin: 0;">Associazione</h2>
    </div>',
    1, 0
);

-- =============================================
-- JUNIOR MEMBERS TEMPLATES (Come Soci)
-- =============================================

-- Libro Soci Minorenni
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Libro Soci Minorenni',
    'Elenco completo di tutti i soci minorenni (cadetti)',
    'list', 'all', 'junior_members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">LIBRO SOCI MINORENNI (CADETTI)</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 9pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000;">Matr.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Cognome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Nome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Data Nasc.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Luogo Nasc.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Codice Fiscale</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Data Iscr.</th>
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
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{birth_place}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{tax_code}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{registration_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{member_status}}</td>
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
        <h2 style="margin: 0;">Associazione - Soci Minorenni</h2>
    </div>',
    1, 0
);

-- Scheda Socio Minorenne Completa
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Scheda Socio Minorenne Completa',
    'Scheda dettagliata socio minorenne con genitori/tutori',
    'relational', 'single', 'junior_members',
    '<div style="margin: 1cm;">
        <h1 style="border-bottom: 3px solid #333; padding-bottom: 0.3cm;">SCHEDA SOCIO MINORENNE</h1>
        
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

        <h2>Genitori/Tutori</h2>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-bottom: 1cm;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; text-align: left;">Tipo</th>
                    <th style="padding: 0.3cm; text-align: left;">Nome</th>
                    <th style="padding: 0.3cm; text-align: left;">Cognome</th>
                    <th style="padding: 0.3cm; text-align: left;">Contatto</th>
                </tr>
            </thead>
            <tbody>
                {{#each junior_member_guardians}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{guardian_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{first_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{last_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{phone}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>

        <h2>Salute e Note</h2>
        {{#each junior_member_health}}
        <div style="border: 1px solid #ccc; padding: 0.5cm; background: #f9f9f9;">
            <p><strong>Allergie:</strong> {{allergies}}</p>
            <p><strong>Intolleranze:</strong> {{food_intolerances}}</p>
            <p><strong>Note:</strong> {{health_notes}}</p>
        </div>
        {{/each}}
    </div>',
    'body { font-family: Arial, sans-serif; font-size: 11pt; }
    h1 { font-size: 18pt; color: #333; }
    h2 { font-size: 14pt; color: #555; margin-top: 0.8cm; margin-bottom: 0.3cm; }
    table { font-size: 10pt; }',
    '["junior_member_guardians", "junior_member_health"]',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 0.3cm; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">Associazione</h2>
    </div>',
    1, 1
);

-- =============================================
-- VEHICLES TEMPLATES (Additional)
-- =============================================

-- Elenco Mezzi con Scadenze
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Elenco Mezzi con Scadenze',
    'Lista mezzi con scadenze assicurazione e revisione',
    'list', 'all', 'vehicles',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ELENCO MEZZI CON SCADENZE</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 9pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000;">Targa</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Tipo</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Marca/Modello</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Scad. Assic.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Scad. Revisione</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Stato</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{license_plate}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{vehicle_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{brand}} {{model}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{insurance_expiry}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{inspection_expiry}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{status}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
        
        <div style="margin-top: 1cm; font-size: 9pt;">
            <p><strong>Legenda Stati:</strong></p>
            <ul>
                <li><strong>Operativo:</strong> Mezzo disponibile per utilizzo</li>
                <li><strong>In Manutenzione:</strong> Mezzo temporaneamente non disponibile</li>
                <li><strong>Fuori Servizio:</strong> Mezzo non utilizzabile</li>
            </ul>
        </div>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    'A4', 'landscape', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">Associazione - Parco Mezzi</h2>
        <p style="margin: 0;">Scadenze Assicurazione e Revisione - {{current_date}}</p>
    </div>',
    1, 0
);

-- =============================================
-- EVENTS TEMPLATES
-- =============================================

-- Elenco Eventi
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
                    <th style="padding: 0.3cm; border: 1px solid #000;">Ora Inizio</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Data Fine</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Ora Fine</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Stato</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{event_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{title}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{start_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{start_time}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{end_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{end_time}}</td>
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
        <h2 style="margin: 0;">Associazione - Registro Eventi</h2>
        <p style="margin: 0;">Data: {{current_date}}</p>
    </div>',
    1, 1
);

-- Scheda Evento con Interventi
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Scheda Evento con Interventi',
    'Scheda dettagliata evento con tutti gli interventi registrati',
    'relational', 'single', 'events',
    '<div style="margin: 1cm;">
        <h1 style="border-bottom: 3px solid #333; padding-bottom: 0.3cm;">SCHEDA EVENTO</h1>
        
        <h2 style="margin-top: 1cm;">Informazioni Generali</h2>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 1cm;">
            <tr>
                <td style="width: 30%; padding: 0.2cm; font-weight: bold;">Tipo Evento:</td>
                <td style="padding: 0.2cm;">{{event_type}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Titolo:</td>
                <td style="padding: 0.2cm;">{{title}}</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Descrizione:</td>
                <td style="padding: 0.2cm;">{{description}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Data e Ora Inizio:</td>
                <td style="padding: 0.2cm;">{{start_date}}</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Data e Ora Fine:</td>
                <td style="padding: 0.2cm;">{{end_date}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Luogo:</td>
                <td style="padding: 0.2cm;">{{location}}</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Stato:</td>
                <td style="padding: 0.2cm;">{{status}}</td>
            </tr>
        </table>

        <h2>Interventi Registrati</h2>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; text-align: left; border: 1px solid #000;">Titolo</th>
                    <th style="padding: 0.3cm; text-align: left; border: 1px solid #000;">Descrizione</th>
                    <th style="padding: 0.3cm; text-align: left; border: 1px solid #000;">Inizio</th>
                    <th style="padding: 0.3cm; text-align: left; border: 1px solid #000;">Fine</th>
                    <th style="padding: 0.3cm; text-align: left; border: 1px solid #000;">Stato</th>
                </tr>
            </thead>
            <tbody>
                {{#each interventions}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;"><strong>{{title}}</strong></td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc; font-size: 9pt;">{{description}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{start_time}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{end_time}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{status}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>',
    'body { font-family: Arial, sans-serif; font-size: 11pt; }
    h1 { font-size: 18pt; color: #333; }
    h2 { font-size: 14pt; color: #555; margin-top: 0.8cm; margin-bottom: 0.3cm; }
    table { font-size: 10pt; }',
    '["interventions"]',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 0.3cm; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">Associazione - Scheda Evento</h2>
    </div>',
    1, 0
);

-- =============================================
-- MEETINGS TEMPLATES (Additional)
-- =============================================

-- Avviso di Assemblea
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Avviso di Assemblea',
    'Convocazione ufficiale per assemblea soci',
    'single', 'single', 'meetings',
    '<div style="margin: 2cm;">
        <h1 style="text-align: center; font-size: 20pt; margin-bottom: 2cm;">AVVISO DI CONVOCAZIONE ASSEMBLEA</h1>
        
        <div style="font-size: 13pt; line-height: 1.8; text-align: justify;">
            <p>I Soci sono convocati in Assemblea {{meeting_type}}</p>
            
            <p style="text-align: center; margin: 2cm 0; font-size: 14pt;">
                <strong>{{meeting_date}}</strong><br>
                alle ore <strong>{{meeting_time}}</strong><br>
                presso <strong>{{location}}</strong>
            </p>
            
            <p>L\'Assemblea è convocata in prima convocazione per l\'ora indicata e in seconda convocazione
            alle ore ___:___ del giorno successivo, nello stesso luogo.</p>
            
            <p style="margin-top: 2cm;">Si invitano tutti i Soci in regola con il versamento della quota associativa
            a intervenire personalmente o a farsi rappresentare da altro Socio mediante delega scritta.</p>
            
            <p style="margin-top: 2cm;">Cordiali saluti.</p>
        </div>
        
        <div style="text-align: right; margin-top: 3cm;">
            <p><strong>Il Presidente</strong></p>
            <p style="margin-top: 2cm;">_________________________</p>
        </div>
    </div>',
    'body { font-family: "Times New Roman", serif; }
    h1 { border-bottom: 3px solid #333; padding-bottom: 0.5cm; }',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; margin-bottom: 1cm;">
        <h2 style="margin: 0;">Associazione</h2>
        <p style="margin: 0.2cm 0;">Sede: ________________</p>
    </div>',
    1, 0
);

-- Ordine del Giorno
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Ordine del Giorno Riunione',
    'Ordine del giorno per riunione o assemblea',
    'relational', 'single', 'meetings',
    '<div style="margin: 1.5cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ORDINE DEL GIORNO</h1>
        
        <div style="margin-top: 1cm; margin-bottom: 1.5cm; font-size: 12pt;">
            <p><strong>Riunione:</strong> {{meeting_type}}</p>
            <p><strong>Data:</strong> {{meeting_date}} ore {{meeting_time}}</p>
            <p><strong>Luogo:</strong> {{location}}</p>
        </div>
        
        <div style="margin-top: 1.5cm;">
            <ol style="font-size: 12pt; line-height: 2;">
                {{#each meeting_agenda}}
                <li style="margin-bottom: 1cm;">
                    <strong style="font-size: 13pt;">{{title}}</strong>
                    {{#if description}}
                    <p style="margin-left: 1cm; margin-top: 0.3cm; font-size: 11pt; color: #555;">{{description}}</p>
                    {{/if}}
                </li>
                {{/each}}
            </ol>
        </div>
        
        <div style="margin-top: 2cm; text-align: right;">
            <p><strong>Il Segretario</strong></p>
            <p style="margin-top: 1.5cm;">_________________________</p>
        </div>
    </div>',
    'body { font-family: "Times New Roman", serif; }
    h1 { font-size: 18pt; }
    li { page-break-inside: avoid; }',
    '["meeting_agenda"]',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 0.3cm; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">Associazione</h2>
    </div>',
    1, 0
);

-- Note: These templates will need to be inserted through a migration or manual SQL execution
-- The created_by field will be NULL for default templates
