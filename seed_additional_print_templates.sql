-- =============================================
-- SEED FILE: Additional Print Templates for EasyVol
-- =============================================
-- This file contains additional print templates for ID cards
-- Run this file to add new templates to the database
--
-- Usage: mysql -u username -p database_name < seed_additional_print_templates.sql
-- Or import via phpMyAdmin or other database management tool
-- =============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- =============================================
-- ADDITIONAL TEMPLATES - ID CARDS
-- =============================================

-- Template 15: Tesserino Socio (Member ID Card)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `is_active`, `is_default`
) VALUES (
    'Tesserino Socio',
    'Tessera associativa professionale formato 85x54mm con qualifica dinamica',
    'single', 'single', 'members',
    '<div class="id-card">
        <!-- Header with Italian colors -->
        <div class="header-stripe">
            <div class="stripe green"></div>
            <div class="stripe white"></div>
            <div class="stripe red"></div>
        </div>
        
        <!-- Main Content Area -->
        <div class="card-content">
            <!-- Left side: Photo area -->
            <div class="photo-section">
                {{#if photo_path}}
                <img src="{{photo_path}}" alt="Foto" class="member-photo">
                {{else}}
                <div class="photo-placeholder">
                    <div class="placeholder-icon">👤</div>
                </div>
                {{/if}}
            </div>
            
            <!-- Right side: Member info -->
            <div class="info-section">
                <div class="association-name">{{association_name}}</div>
                <div class="card-title">TESSERA SOCIO</div>
                
                <div class="member-details">
                    <div class="detail-row">
                        <span class="label">Nome:</span>
                        <span class="value">{{first_name}} {{last_name}}</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Matr.:</span>
                        <span class="value">{{registration_number}}</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Qualifica:</span>
                        <span class="value">{{#if member_roles.[0].role_name}}{{member_roles.[0].role_name}}{{else}}Volontario{{/if}}</span>
                    </div>
                </div>
                
                <div class="validity">
                    Valida fino al 31/12/{{current_year}}
                </div>
            </div>
        </div>
        
        <!-- Footer with QR code placeholder -->
        <div class="footer-section">
            <div class="qr-placeholder">
                <svg width="35" height="35" viewBox="0 0 35 35" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="0" y="0" width="12" height="12" fill="#000"/>
                    <rect x="23" y="0" width="12" height="12" fill="#000"/>
                    <rect x="0" y="23" width="12" height="12" fill="#000"/>
                    <rect x="4" y="4" width="4" height="4" fill="#fff"/>
                    <rect x="27" y="4" width="4" height="4" fill="#fff"/>
                    <rect x="4" y="27" width="4" height="4" fill="#fff"/>
                    <rect x="15" y="2" width="2" height="2" fill="#000"/>
                    <rect x="19" y="2" width="2" height="2" fill="#000"/>
                    <rect x="15" y="6" width="2" height="2" fill="#000"/>
                    <rect x="15" y="10" width="2" height="2" fill="#000"/>
                    <rect x="19" y="10" width="2" height="2" fill="#000"/>
                    <rect x="14" y="14" width="7" height="7" fill="#000"/>
                    <rect x="16" y="16" width="3" height="3" fill="#fff"/>
                    <rect x="2" y="15" width="2" height="2" fill="#000"/>
                    <rect x="6" y="15" width="2" height="2" fill="#000"/>
                    <rect x="10" y="15" width="2" height="2" fill="#000"/>
                    <rect x="2" y="19" width="2" height="2" fill="#000"/>
                    <rect x="10" y="19" width="2" height="2" fill="#000"/>
                    <rect x="23" y="15" width="2" height="2" fill="#000"/>
                    <rect x="27" y="15" width="2" height="2" fill="#000"/>
                    <rect x="31" y="15" width="2" height="2" fill="#000"/>
                    <rect x="23" y="19" width="2" height="2" fill="#000"/>
                    <rect x="31" y="19" width="2" height="2" fill="#000"/>
                    <rect x="15" y="23" width="2" height="2" fill="#000"/>
                    <rect x="19" y="23" width="2" height="2" fill="#000"/>
                    <rect x="15" y="27" width="2" height="2" fill="#000"/>
                    <rect x="15" y="31" width="2" height="2" fill="#000"/>
                    <rect x="19" y="31" width="2" height="2" fill="#000"/>
                    <rect x="23" y="25" width="2" height="2" fill="#000"/>
                    <rect x="27" y="25" width="2" height="2" fill="#000"/>
                    <rect x="27" y="29" width="2" height="2" fill="#000"/>
                    <rect x="31" y="29" width="2" height="2" fill="#000"/>
                </svg>
            </div>
            <div class="issue-date">Emessa il {{current_date}}</div>
        </div>
        
        <!-- Footer stripe -->
        <div class="footer-stripe">
            <div class="stripe green"></div>
            <div class="stripe white"></div>
            <div class="stripe red"></div>
        </div>
    </div>',
    'body { 
        font-family: Arial, Helvetica, sans-serif; 
        margin: 0; 
        padding: 0;
    }
    
    @page { 
        size: 85mm 54mm; 
        margin: 0; 
    }
    
    .id-card {
        width: 85mm;
        height: 54mm;
        border: 1px solid #333;
        border-radius: 3mm;
        overflow: hidden;
        background: #fff;
        position: relative;
        box-sizing: border-box;
    }
    
    /* Italian flag stripes */
    .header-stripe, .footer-stripe {
        display: flex;
        height: 4mm;
        width: 100%;
    }
    
    .stripe {
        flex: 1;
    }
    
    .stripe.green {
        background: #009246;
    }
    
    .stripe.white {
        background: #ffffff;
    }
    
    .stripe.red {
        background: #ce2b37;
    }
    
    .card-content {
        display: flex;
        padding: 2mm;
        height: 42mm;
        box-sizing: border-box;
    }
    
    /* Photo section */
    .photo-section {
        width: 26mm;
        height: 38mm;
        margin-right: 2mm;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .member-photo {
        width: 26mm;
        height: 34mm;
        object-fit: cover;
        border: 1px solid #ccc;
        border-radius: 2mm;
    }
    
    .photo-placeholder {
        width: 26mm;
        height: 34mm;
        border: 1px solid #ccc;
        border-radius: 2mm;
        background: #f5f5f5;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .placeholder-icon {
        font-size: 30pt;
        color: #ccc;
    }
    
    /* Info section */
    .info-section {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    
    .association-name {
        font-size: 7pt;
        font-weight: bold;
        text-align: center;
        color: #333;
        margin-bottom: 1mm;
        line-height: 1.2;
    }
    
    .card-title {
        font-size: 9pt;
        font-weight: bold;
        text-align: center;
        color: #009246;
        border-bottom: 1px solid #009246;
        padding-bottom: 1mm;
        margin-bottom: 2mm;
    }
    
    .member-details {
        flex: 1;
    }
    
    .detail-row {
        margin-bottom: 1.5mm;
        font-size: 7pt;
        line-height: 1.3;
    }
    
    .detail-row .label {
        font-weight: bold;
        color: #666;
        display: inline-block;
        width: 15mm;
    }
    
    .detail-row .value {
        color: #000;
        font-weight: bold;
    }
    
    .validity {
        font-size: 6pt;
        text-align: center;
        color: #666;
        font-weight: bold;
        border-top: 1px solid #ddd;
        padding-top: 1mm;
    }
    
    /* Footer section */
    .footer-section {
        height: 4mm;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 2mm;
        background: #f9f9f9;
        box-sizing: border-box;
    }
    
    .qr-placeholder {
        width: 3.5mm;
        height: 3.5mm;
    }
    
    .issue-date {
        font-size: 5pt;
        color: #666;
    }
    
    @media print {
        body { margin: 0; }
        .id-card { 
            page-break-inside: avoid;
            border-radius: 3mm;
        }
    }',
    'member_roles',
    'custom', 'landscape', 0, 0, 1, 0
);

-- Template 16: Tesserino Cadetto (Junior Member ID Card)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `is_active`, `is_default`
) VALUES (
    'Tesserino Cadetto',
    'Tessera per socio minorenne formato 85x54mm con contatti emergenza',
    'single', 'single', 'junior_members',
    '<div class="id-card junior">
        <!-- Header with Italian colors -->
        <div class="header-stripe">
            <div class="stripe green"></div>
            <div class="stripe white"></div>
            <div class="stripe red"></div>
        </div>
        
        <!-- Main Content Area -->
        <div class="card-content">
            <!-- Left side: Photo area -->
            <div class="photo-section">
                {{#if photo_path}}
                <img src="{{photo_path}}" alt="Foto" class="member-photo">
                {{else}}
                <div class="photo-placeholder">
                    <div class="placeholder-icon">👤</div>
                </div>
                {{/if}}
            </div>
            
            <!-- Right side: Member info -->
            <div class="info-section">
                <div class="association-name">{{association_name}}</div>
                <div class="card-title junior-badge">GIOVANE VOLONTARIO</div>
                
                <div class="member-details">
                    <div class="detail-row">
                        <span class="label">Nome:</span>
                        <span class="value">{{first_name}} {{last_name}}</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Matr.:</span>
                        <span class="value">C-{{registration_number}}</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Nato il:</span>
                        <span class="value">{{birth_date}}</span>
                    </div>
                </div>
                
                <div class="validity">
                    Valida fino al 31/12/{{current_year}}
                </div>
            </div>
        </div>
        
        <!-- Emergency contact section -->
        <div class="emergency-section">
            <div class="emergency-title">
                <svg width="10" height="10" viewBox="0 0 10 10" style="vertical-align: middle; margin-right: 1mm;">
                    <circle cx="5" cy="5" r="4" fill="#ce2b37"/>
                    <text x="5" y="7.5" text-anchor="middle" fill="#fff" font-size="7" font-weight="bold">!</text>
                </svg>
                EMERGENZA
            </div>
            <div class="emergency-contact">
                Tel. Tutore: ___________________
            </div>
        </div>
        
        <!-- Footer section -->
        <div class="footer-section">
            <div class="issue-date">Emessa il {{current_date}}</div>
        </div>
        
        <!-- Footer stripe -->
        <div class="footer-stripe">
            <div class="stripe green"></div>
            <div class="stripe white"></div>
            <div class="stripe red"></div>
        </div>
    </div>',
    'body { 
        font-family: Arial, Helvetica, sans-serif; 
        margin: 0; 
        padding: 0;
    }
    
    @page { 
        size: 85mm 54mm; 
        margin: 0; 
    }
    
    .id-card {
        width: 85mm;
        height: 54mm;
        border: 1px solid #333;
        border-radius: 3mm;
        overflow: hidden;
        background: #fff;
        position: relative;
        box-sizing: border-box;
    }
    
    .id-card.junior {
        border-color: #ce2b37;
    }
    
    /* Italian flag stripes */
    .header-stripe, .footer-stripe {
        display: flex;
        height: 4mm;
        width: 100%;
    }
    
    .stripe {
        flex: 1;
    }
    
    .stripe.green {
        background: #009246;
    }
    
    .stripe.white {
        background: #ffffff;
    }
    
    .stripe.red {
        background: #ce2b37;
    }
    
    .card-content {
        display: flex;
        padding: 2mm;
        height: 31mm;
        box-sizing: border-box;
    }
    
    /* Photo section */
    .photo-section {
        width: 26mm;
        height: 27mm;
        margin-right: 2mm;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .member-photo {
        width: 26mm;
        height: 27mm;
        object-fit: cover;
        border: 1px solid #ccc;
        border-radius: 2mm;
    }
    
    .photo-placeholder {
        width: 26mm;
        height: 27mm;
        border: 1px solid #ccc;
        border-radius: 2mm;
        background: #fff3cd;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .placeholder-icon {
        font-size: 30pt;
        color: #ffc107;
    }
    
    /* Info section */
    .info-section {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    
    .association-name {
        font-size: 7pt;
        font-weight: bold;
        text-align: center;
        color: #333;
        margin-bottom: 1mm;
        line-height: 1.2;
    }
    
    .card-title {
        font-size: 8pt;
        font-weight: bold;
        text-align: center;
        color: #009246;
        border-bottom: 1px solid #009246;
        padding-bottom: 1mm;
        margin-bottom: 2mm;
    }
    
    .card-title.junior-badge {
        color: #ce2b37;
        border-bottom-color: #ce2b37;
        background: #fff3cd;
        padding: 1mm;
        border-radius: 2mm;
    }
    
    .member-details {
        flex: 1;
    }
    
    .detail-row {
        margin-bottom: 1.5mm;
        font-size: 7pt;
        line-height: 1.3;
    }
    
    .detail-row .label {
        font-weight: bold;
        color: #666;
        display: inline-block;
        width: 13mm;
    }
    
    .detail-row .value {
        color: #000;
        font-weight: bold;
    }
    
    .validity {
        font-size: 6pt;
        text-align: center;
        color: #666;
        font-weight: bold;
        border-top: 1px solid #ddd;
        padding-top: 1mm;
    }
    
    /* Emergency section */
    .emergency-section {
        height: 7mm;
        background: #fff3cd;
        border-top: 2px solid #ce2b37;
        border-bottom: 2px solid #ce2b37;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 0 2mm;
        box-sizing: border-box;
    }
    
    .emergency-title {
        font-size: 6pt;
        font-weight: bold;
        color: #ce2b37;
        text-transform: uppercase;
        margin-bottom: 0.5mm;
        display: flex;
        align-items: center;
    }
    
    .emergency-contact {
        font-size: 6pt;
        color: #000;
        font-weight: bold;
    }
    
    /* Footer section */
    .footer-section {
        height: 4mm;
        display: flex;
        justify-content: center;
        align-items: center;
        background: #f9f9f9;
        box-sizing: border-box;
    }
    
    .issue-date {
        font-size: 5pt;
        color: #666;
    }
    
    @media print {
        body { margin: 0; }
        .id-card { 
            page-break-inside: avoid;
            border-radius: 3mm;
        }
    }',
    'junior_member_guardians',
    'custom', 'landscape', 0, 0, 1, 0
);

COMMIT;

-- =============================================
-- END OF SEED FILE
-- =============================================
