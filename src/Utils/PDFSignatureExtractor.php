<?php
namespace EasyVol\Utils;

/**
 * PDF Digital Signature Extractor
 * 
 * Extracts digital signature information from PDF and P7M files.
 * Supports both PAdES (PDF embedded) and CAdES (.p7m) formats.
 * Uses openssl CLI to parse PKCS#7/CMS certificate data for accurate
 * signer identification (name, organization, CA, signing date).
 */
class PDFSignatureExtractor {
    
    const SIGNATURE_FORMAT_PADES = 'PADES';
    const SIGNATURE_FORMAT_CADES = 'CADES';
    const SIGNATURE_FORMAT_UNKNOWN = 'UNKNOWN';
    
    const SIGNATURE_VALIDITY_VALID = 'valid';
    const SIGNATURE_VALIDITY_INVALID = 'invalid';
    const SIGNATURE_VALIDITY_UNKNOWN = 'unknown';
    
    /**
     * Get default empty result structure for signature extraction.
     * 
     * @return array Default result with no signatures
     */
    public static function getEmptyResult() {
        return [
            'has_signature' => false,
            'format' => self::SIGNATURE_FORMAT_UNKNOWN,
            'count' => 0,
            'signatures' => [],
            'validity' => self::SIGNATURE_VALIDITY_UNKNOWN
        ];
    }
    
    /**
     * Extract signature information from a PDF or P7M file.
     * 
     * @param string $filePath Path to PDF or P7M file
     * @return array ['has_signature' => bool, 'format' => string, 'count' => int, 'signatures' => array, 'validity' => string]
     */
    public static function extractSignatures($filePath) {
        $result = self::getEmptyResult();
        
        if (!file_exists($filePath) || !is_readable($filePath)) {
            error_log("PDFSignatureExtractor: File not readable: $filePath");
            return $result;
        }
        
        try {
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            
            if ($extension === 'p7m') {
                // CAdES: the whole file is a PKCS#7 envelope
                $signatures = self::extractCadesSignatures($filePath);
                if (!empty($signatures)) {
                    $result['has_signature'] = true;
                    $result['format'] = self::SIGNATURE_FORMAT_CADES;
                    $result['count'] = count($signatures);
                    $result['signatures'] = $signatures;
                    $result['validity'] = self::assessValidity($signatures);
                }
            } else {
                // PDF file – check for PAdES embedded signatures
                $pdfContent = file_get_contents($filePath);
                if ($pdfContent === false) {
                    error_log("PDFSignatureExtractor: Cannot read file: $filePath");
                    return $result;
                }
                
                $signatures = self::extractPadesSignatures($pdfContent, $filePath);
                if (!empty($signatures)) {
                    $result['has_signature'] = true;
                    $result['format'] = self::SIGNATURE_FORMAT_PADES;
                    $result['count'] = count($signatures);
                    $result['signatures'] = $signatures;
                    $result['validity'] = self::assessValidity($signatures);
                }
            }
            
        } catch (\Throwable $e) {
            error_log("PDFSignatureExtractor: Error - " . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Extract signatures from a CAdES .p7m file using openssl.
     * 
     * @param string $filePath Path to .p7m file
     * @return array Array of signature info
     */
    private static function extractCadesSignatures($filePath) {
        $signatures = [];
        
        // Try DER format first (most common for Italian .p7m), then PEM
        $pkcs7Output = self::runOpenSslPkcs7($filePath, 'DER');
        if ($pkcs7Output === null) {
            $pkcs7Output = self::runOpenSslPkcs7($filePath, 'PEM');
        }
        // Also try cms command as fallback
        if ($pkcs7Output === null) {
            $pkcs7Output = self::runOpenSslCms($filePath);
        }
        
        if ($pkcs7Output !== null) {
            $signatures = self::parseCertificateOutput($pkcs7Output);
        }
        
        // Extract actual signing times from CMS signerInfo attributes
        // The certificate Not Before date is NOT the signing date; the real
        // signing date lives in the signingTime authenticated attribute.
        $signingTimes = self::extractCmsSigningTimes($filePath);
        if (!empty($signingTimes)) {
            $singleMatch = (count($signingTimes) === 1 && count($signatures) === 1);
            foreach ($signatures as $idx => &$sig) {
                if (isset($signingTimes[$idx])) {
                    $sig['signature_date'] = $signingTimes[$idx];
                } elseif ($singleMatch) {
                    $sig['signature_date'] = reset($signingTimes);
                }
            }
            unset($sig);
        }
        
        return $signatures;
    }
    
    /**
     * Extract signingTime values from CMS signerInfo blocks.
     * 
     * Uses "openssl cms -cmsout -print" to read the CMS structure and
     * parses signingTime (OID 1.2.840.113549.1.9.5) from each signerInfo.
     * 
     * @param string $filePath Path to .p7m / DER CMS file
     * @return array Indexed array of signing date strings (Y-m-d H:i:s)
     */
    private static function extractCmsSigningTimes($filePath) {
        $escapedPath = escapeshellarg($filePath);
        $outputLines = [];
        $returnCode = 0;
        
        exec("openssl cms -inform DER -in {$escapedPath} -cmsout -print 2>/dev/null", $outputLines, $returnCode);
        if ($returnCode !== 0 || empty($outputLines)) {
            return [];
        }
        
        $cmsText = implode("\n", $outputLines);
        $signingTimes = [];
        
        // Match UTCTIME or GENERALIZEDTIME values that follow a signingTime object line.
        // Expected openssl cms -cmsout -print format:
        //   object: signingTime (1.2.840.113549.1.9.5)
        //   set:
        //     UTCTIME:Jul  5 12:30:00 2025 GMT
        // or  GENERALIZEDTIME:20250705123000Z
        if (preg_match_all('/signingTime[^\n]*\n\s*set:\s*\n\s*(UTCTIME|GENERALIZEDTIME)\s*:\s*(.+)/i', $cmsText, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $timeType = strtoupper(trim($m[1]));
                $timeVal = trim($m[2]);
                
                $ts = false;
                if ($timeType === 'GENERALIZEDTIME') {
                    // Format: YYYYMMDDHHmmSSZ or similar
                    $ts = strtotime($timeVal);
                    if ($ts === false && preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $timeVal, $gm)) {
                        $ts = strtotime("{$gm[1]}-{$gm[2]}-{$gm[3]} {$gm[4]}:{$gm[5]}:{$gm[6]} UTC");
                    }
                } else {
                    // UTCTIME: "Jul  5 12:30:00 2025 GMT" or similar
                    $ts = strtotime($timeVal);
                }
                
                if ($ts !== false) {
                    $signingTimes[] = date('Y-m-d H:i:s', $ts);
                } else {
                    error_log("PDFSignatureExtractor: Failed to parse signingTime ({$timeType}): {$timeVal}");
                }
            }
        }
        
        return $signingTimes;
    }
    
    /**
     * Extract PAdES signatures from PDF content.
     * Locates PKCS#7 signature blobs in the PDF ByteRange structure
     * and parses certificates with openssl.
     * 
     * @param string $pdfContent Raw PDF binary content
     * @param string $filePath Original file path (for temp file extraction)
     * @return array Array of signature info
     */
    private static function extractPadesSignatures($pdfContent, $filePath) {
        $signatures = [];
        $pkcs7Blobs = self::extractPkcs7FromPdf($pdfContent);
        
        if (empty($pkcs7Blobs)) {
            // Fallback: check /Name fields in signature dictionaries
            $signatures = self::fallbackPdfParsing($pdfContent);
            return $signatures;
        }
        
        foreach ($pkcs7Blobs as $index => $blobHex) {
            // Validate hex string before conversion
            if (!preg_match('/^[0-9a-fA-F]+$/', $blobHex)) {
                continue;
            }
            $blobBin = hex2bin($blobHex);
            if ($blobBin === false || strlen($blobBin) < 64) {
                continue;
            }
            
            $tmpFile = tempnam(sys_get_temp_dir(), 'easyvol_sig_');
            if ($tmpFile === false) {
                continue;
            }
            // Restrict permissions on temp file containing signature data
            chmod($tmpFile, 0600);
            
            try {
                file_put_contents($tmpFile, $blobBin);
                
                $certOutput = self::runOpenSslPkcs7($tmpFile, 'DER');
                if ($certOutput === null) {
                    $certOutput = self::runOpenSslCms($tmpFile);
                }
                
                if ($certOutput !== null) {
                    $parsed = self::parseCertificateOutput($certOutput, $index);
                    $signatures = array_merge($signatures, $parsed);
                }
            } finally {
                if (!unlink($tmpFile)) {
                    error_log("PDFSignatureExtractor: Failed to remove temp file: $tmpFile");
                }
            }
        }
        
        // If PKCS#7 blobs were found but openssl couldn't process any of them,
        // fall back to PDF dictionary parsing to at least get basic signature info
        if (empty($signatures)) {
            $signatures = self::fallbackPdfParsing($pdfContent);
        }
        
        // Also extract PDF-level metadata (/M date, /Reason, /Location) and merge
        self::mergePdfMetadata($pdfContent, $signatures);
        
        return $signatures;
    }
    
    /**
     * Extract raw PKCS#7 hex blobs from PDF /Contents fields within signature dictionaries.
     * 
     * @param string $pdfContent Raw PDF content
     * @return array Array of hex strings (PKCS#7 DER data)
     */
    private static function extractPkcs7FromPdf($pdfContent) {
        $blobs = [];
        
        // Find ByteRange entries (each indicates a signature)
        // The signature hex blob sits between the two covered ranges
        if (preg_match_all('/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/', $pdfContent, $brMatches, PREG_SET_ORDER)) {
            foreach ($brMatches as $br) {
                $gapStart = (int)$br[1] + (int)$br[2]; // end of first range
                $gapEnd = (int)$br[3];                  // start of second range
                $gapLen = $gapEnd - $gapStart;
                
                if ($gapLen > 2 && $gapLen < strlen($pdfContent)) {
                    $hexChunk = substr($pdfContent, $gapStart, $gapLen);
                    // The hex data is enclosed between < and >
                    $hexChunk = trim($hexChunk, " \r\n\t<>");
                    // Remove any whitespace inside hex
                    $hexChunk = preg_replace('/\s+/', '', $hexChunk);
                    // Ensure even length for hex2bin
                    if (strlen($hexChunk) % 2 !== 0) {
                        $hexChunk .= '0';
                    }
                    if (strlen($hexChunk) >= 128) {
                        $blobs[] = $hexChunk;
                    }
                }
            }
        }
        
        return $blobs;
    }
    
    /**
     * Run openssl pkcs7 command to extract certificate information.
     * 
     * @param string $filePath Path to PKCS#7 file
     * @param string $inform Input format: 'DER' or 'PEM'
     * @return string|null Certificate text output or null on failure
     */
    private static function runOpenSslPkcs7($filePath, $inform = 'DER') {
        // Whitelist validation for inform parameter
        if (!in_array($inform, ['DER', 'PEM'], true)) {
            error_log("PDFSignatureExtractor: Invalid inform format: $inform");
            return null;
        }
        
        $escapedPath = escapeshellarg($filePath);
        
        $cmd = "openssl pkcs7 -inform " . escapeshellarg($inform) . " -in {$escapedPath} -print_certs -text 2>/dev/null";
        $output = null;
        $returnCode = 0;
        exec($cmd, $outputLines, $returnCode);
        
        if ($returnCode === 0 && !empty($outputLines)) {
            $output = implode("\n", $outputLines);
            // Verify we actually got certificate data
            if (strpos($output, 'Subject:') !== false || strpos($output, 'Issuer:') !== false) {
                return $output;
            }
        }
        
        return null;
    }
    
    /**
     * Run openssl cms command as fallback for newer CMS/CAdES formats.
     * 
     * @param string $filePath Path to CMS file
     * @return string|null Certificate text output or null on failure
     */
    private static function runOpenSslCms($filePath) {
        $escapedPath = escapeshellarg($filePath);
        
        // First try: extract certificates using -verify -noverify (standard CMS cert extraction)
        $outputLines2 = [];
        $returnCode2 = 0;
        exec("openssl cms -inform DER -in {$escapedPath} -verify -noverify -print_certs -text 2>/dev/null", $outputLines2, $returnCode2);
        
        if ($returnCode2 === 0 && !empty($outputLines2)) {
            $certOutput = implode("\n", $outputLines2);
            if (strpos($certOutput, 'Subject:') !== false) {
                return $certOutput;
            }
        }
        
        // Second try: use -noout -print_certs for older/different CMS structures
        $outputLines3 = [];
        $returnCode3 = 0;
        exec("openssl cms -inform DER -in {$escapedPath} -noout -print_certs -text 2>/dev/null", $outputLines3, $returnCode3);
        
        if (!empty($outputLines3)) {
            $certOutput = implode("\n", $outputLines3);
            if (strpos($certOutput, 'Subject:') !== false) {
                return $certOutput;
            }
        }
        
        // Fallback: dump CMS structure and parse signer info from it
        $outputLines = [];
        $returnCode = 0;
        exec("openssl cms -inform DER -in {$escapedPath} -cmsout -print 2>/dev/null", $outputLines, $returnCode);
        
        if ($returnCode !== 0 || empty($outputLines)) {
            return null;
        }
        
        $cmsOutput = implode("\n", $outputLines);
        
        // Parse signer info from CMS print output
        return self::extractSignerInfoFromCmsPrint($cmsOutput);
    }
    
    /**
     * Parse signer info from openssl cms -print output when certificate extraction fails.
     * 
     * @param string $cmsOutput Output from openssl cms -cmsout -print
     * @return string|null Pseudo certificate output or null
     */
    private static function extractSignerInfoFromCmsPrint($cmsOutput) {
        // Look for issuer and serial info in signer info blocks
        if (preg_match_all('/issuer:\s*\n(.*?)(?=\n\s*\n|\n\s*[a-z])/si', $cmsOutput, $matches)) {
            $result = '';
            foreach ($matches[1] as $issuerBlock) {
                $result .= "Subject: " . trim($issuerBlock) . "\n";
            }
            if (!empty($result)) {
                return $result;
            }
        }
        return null;
    }
    
    /**
     * Parse openssl certificate text output to extract signer details.
     * 
     * @param string $certOutput Output from openssl pkcs7/cms -print_certs -text
     * @param int $startIndex Starting index for signature numbering
     * @return array Array of signature info arrays
     */
    private static function parseCertificateOutput($certOutput, $startIndex = 0) {
        $signatures = [];
        
        // Split by certificate blocks
        $certBlocks = preg_split('/-----BEGIN CERTIFICATE-----/', $certOutput);
        
        // Collect all subject/issuer pairs first
        $certs = [];
        
        // Also try parsing without PEM markers (when using -text)
        if (preg_match_all('/Subject:\s*(.+)/i', $certOutput, $subjectMatches)) {
            $issuerMatches = [];
            preg_match_all('/Issuer:\s*(.+)/i', $certOutput, $issuerMatches);
            
            $validityNotBefore = [];
            preg_match_all('/Not Before\s*:\s*(.+)/i', $certOutput, $validityNotBefore);
            
            $validityNotAfter = [];
            preg_match_all('/Not After\s*:\s*(.+)/i', $certOutput, $validityNotAfter);
            
            $serialMatches = [];
            preg_match_all('/Serial Number\s*:\s*\n?\s*(.+)/i', $certOutput, $serialMatches);
            
            for ($i = 0; $i < count($subjectMatches[1]); $i++) {
                $subject = trim($subjectMatches[1][$i]);
                $issuer = isset($issuerMatches[1][$i]) ? trim($issuerMatches[1][$i]) : '';
                $notBefore = isset($validityNotBefore[1][$i]) ? trim($validityNotBefore[1][$i]) : '';
                $notAfter = isset($validityNotAfter[1][$i]) ? trim($validityNotAfter[1][$i]) : '';
                $serial = isset($serialMatches[1][$i]) ? trim($serialMatches[1][$i]) : '';
                
                $certs[] = [
                    'subject' => $subject,
                    'issuer' => $issuer,
                    'not_before' => $notBefore,
                    'not_after' => $notAfter,
                    'serial' => $serial,
                ];
            }
        }
        
        // Filter: keep only end-entity (signer) certificates, not CA certificates
        // A CA cert is one whose Subject appears as Issuer of another cert, or is self-signed
        $signerCerts = self::filterSignerCertificates($certs);
        
        $sigNum = $startIndex;
        foreach ($signerCerts as $cert) {
            $sigNum++;
            $parsed = self::parseDN($cert['subject']);
            $issuerParsed = self::parseDN($cert['issuer']);
            
            $signerName = $parsed['CN'] ?? $parsed['O'] ?? 'Sconosciuto';
            $organization = $parsed['O'] ?? null;
            $serialNumber = $parsed['serialNumber'] ?? $parsed['SERIALNUMBER'] ?? null;
            $fiscalCode = self::extractFiscalCode($cert['subject'], $serialNumber);
            
            $issuerOrg = $issuerParsed['O'] ?? null;
            $issuerCN = $issuerParsed['CN'] ?? null;
            
            // Determine CA provider name
            $caProvider = self::identifyCAProvider($issuerOrg, $issuerCN, $cert['issuer']);
            
            // Parse signing date from certificate validity
            $sigDate = null;
            if (!empty($cert['not_before'])) {
                $ts = strtotime($cert['not_before']);
                if ($ts !== false) {
                    $sigDate = date('Y-m-d H:i:s', $ts);
                }
            }
            
            // Certificate validity period
            $certValidFrom = null;
            $certValidTo = null;
            if (!empty($cert['not_before'])) {
                $ts = strtotime($cert['not_before']);
                if ($ts !== false) {
                    $certValidFrom = date('Y-m-d H:i:s', $ts);
                }
            }
            if (!empty($cert['not_after'])) {
                $ts = strtotime($cert['not_after']);
                if ($ts !== false) {
                    $certValidTo = date('Y-m-d H:i:s', $ts);
                }
            }
            
            $signatures[] = [
                'number' => $sigNum,
                'signer_name' => $signerName,
                'signer_organization' => $organization,
                'fiscal_code' => $fiscalCode,
                'signature_date' => $sigDate,
                'reason' => null,
                'location' => null,
                'ca_provider' => $caProvider,
                'issuer' => $issuerCN ?? $issuerOrg ?? $cert['issuer'],
                'certificate_valid_from' => $certValidFrom,
                'certificate_valid_to' => $certValidTo,
                'serial_number' => $cert['serial'] ?: null,
                'certificate_info' => [
                    'subject' => $cert['subject'],
                    'issuer' => $cert['issuer'],
                ]
            ];
        }
        
        return $signatures;
    }
    
    /**
     * Filter out CA/intermediate certificates, keeping only end-entity signer certs.
     * 
     * @param array $certs Array of cert arrays with 'subject' and 'issuer'
     * @return array Filtered array of signer certificates
     */
    private static function filterSignerCertificates($certs) {
        if (empty($certs)) {
            return [];
        }
        
        // Collect all subjects
        $subjects = array_column($certs, 'subject');
        
        $signerCerts = [];
        foreach ($certs as $cert) {
            // Skip self-signed certs (CA root)
            if ($cert['subject'] === $cert['issuer']) {
                continue;
            }
            
            // Skip certs whose subject is the issuer of another cert (intermediate CA)
            $isIssuer = false;
            foreach ($certs as $other) {
                if ($other['issuer'] === $cert['subject'] && $other['subject'] !== $cert['subject']) {
                    $isIssuer = true;
                    break;
                }
            }
            
            if (!$isIssuer) {
                $signerCerts[] = $cert;
            }
        }
        
        // If filtering removed everything, return all non-self-signed
        if (empty($signerCerts)) {
            foreach ($certs as $cert) {
                if ($cert['subject'] !== $cert['issuer']) {
                    $signerCerts[] = $cert;
                }
            }
        }
        
        // If still empty, return all certs
        if (empty($signerCerts)) {
            $signerCerts = $certs;
        }
        
        return $signerCerts;
    }
    
    /**
     * Parse a Distinguished Name (DN) string into key-value pairs.
     * 
     * @param string $dn DN string like "CN = Mario Rossi, O = Aruba PEC S.p.A., ..."
     * @return array Associative array of DN components
     */
    private static function parseDN($dn) {
        $result = [];
        if (empty($dn)) {
            return $result;
        }
        
        // Use openssl_x509_parse-style parsing: split on ", " but not inside quoted strings
        // openssl uses ", " as separator in Subject/Issuer lines
        // Also handle "/" separator used in some openssl output formats
        $parts = [];
        $current = '';
        $inEscape = false;
        
        for ($i = 0; $i < strlen($dn); $i++) {
            $ch = $dn[$i];
            
            if ($inEscape) {
                $current .= $ch;
                $inEscape = false;
                continue;
            }
            
            if ($ch === '\\') {
                $inEscape = true;
                $current .= $ch;
                continue;
            }
            
            // Split on ", " or "/" separators
            if ($ch === '/' && ($i === 0 || $dn[$i-1] !== '\\')) {
                if (!empty(trim($current))) {
                    $parts[] = trim($current);
                }
                $current = '';
                continue;
            }
            
            if ($ch === ',' && $i + 1 < strlen($dn) && $dn[$i + 1] === ' ') {
                if (!empty(trim($current))) {
                    $parts[] = trim($current);
                }
                $current = '';
                $i++; // skip the space after comma
                continue;
            }
            
            $current .= $ch;
        }
        if (!empty(trim($current))) {
            $parts[] = trim($current);
        }
        
        foreach ($parts as $part) {
            $part = trim($part);
            if (strpos($part, '=') !== false) {
                list($key, $value) = explode('=', $part, 2);
                $key = trim($key);
                $value = trim($value);
                // Handle OID-based keys (e.g., 2.5.4.5 = serialNumber)
                $key = self::resolveOID($key);
                $result[$key] = $value;
            }
        }
        
        return $result;
    }
    
    /**
     * Resolve common OIDs to human-readable names.
     * 
     * @param string $key OID or key name
     * @return string Resolved key name
     */
    private static function resolveOID($key) {
        $oidMap = [
            '2.5.4.3' => 'CN',
            '2.5.4.4' => 'SN', // surname
            '2.5.4.5' => 'serialNumber',
            '2.5.4.6' => 'C',
            '2.5.4.7' => 'L',
            '2.5.4.8' => 'ST',
            '2.5.4.10' => 'O',
            '2.5.4.11' => 'OU',
            '2.5.4.42' => 'GN', // givenName
            '2.5.4.46' => 'dnQualifier',
            '1.2.840.113549.1.9.1' => 'emailAddress',
        ];
        
        return $oidMap[$key] ?? $key;
    }
    
    /**
     * Extract Italian fiscal code (codice fiscale) from certificate data.
     * 
     * @param string $subject Full subject DN string
     * @param string|null $serialNumber Serial number field
     * @return string|null Fiscal code or null
     */
    private static function extractFiscalCode($subject, $serialNumber = null) {
        // Italian fiscal code pattern: 16 chars alphanumeric
        $pattern = '/\b([A-Z]{6}\d{2}[A-EHLMPRST]\d{2}[A-Z]\d{3}[A-Z])\b/i';
        
        // Check in serialNumber first (common location in Italian qualified certs)
        if (!empty($serialNumber) && preg_match($pattern, $serialNumber, $m)) {
            return strtoupper($m[1]);
        }
        
        // Also check the subject line which may contain TINIT- prefix
        if (preg_match('/TINIT-([A-Z]{6}\d{2}[A-EHLMPRST]\d{2}[A-Z]\d{3}[A-Z])/i', $subject, $m)) {
            return strtoupper($m[1]);
        }
        
        if (preg_match($pattern, $subject, $m)) {
            return strtoupper($m[1]);
        }
        
        return null;
    }
    
    /**
     * Identify the Certificate Authority provider from issuer information.
     * Recognizes major Italian and European digital signature CAs.
     * 
     * @param string|null $issuerOrg Issuer organization
     * @param string|null $issuerCN Issuer common name
     * @param string $fullIssuer Full issuer DN
     * @return string Human-readable CA provider name
     */
    private static function identifyCAProvider($issuerOrg, $issuerCN, $fullIssuer) {
        $searchText = strtolower(($issuerOrg ?? '') . ' ' . ($issuerCN ?? '') . ' ' . $fullIssuer);
        
        $providers = [
            'aruba' => 'Aruba PEC S.p.A.',
            'actalis' => 'Actalis S.p.A. (Aruba Group)',
            'infocert' => 'InfoCert S.p.A.',
            'dike' => 'InfoCert S.p.A. (DiKe)',
            'poste italiane' => 'Poste Italiane S.p.A.',
            'postecom' => 'Postecom S.p.A. (Poste Italiane)',
            'namirial' => 'Namirial S.p.A.',
            'telecom italia' => 'Telecom Italia Trust Technologies',
            'trust technologies' => 'Telecom Italia Trust Technologies',
            'intesi group' => 'Intesi Group S.p.A.',
            'in.te.s.a' => 'In.Te.S.A. S.p.A.',
            'cedacri' => 'Cedacri S.p.A.',
            'zucchetti' => 'Zucchetti S.p.A.',
            'banca d\'italia' => 'Banca d\'Italia',
            'agenzia delle entrate' => 'Agenzia delle Entrate',
            'consiglio nazionale del notariato' => 'CNN (Notariato)',
            'globaltrust' => 'GlobalTrust',
            'comodo' => 'Sectigo (Comodo)',
            'sectigo' => 'Sectigo',
            'digicert' => 'DigiCert',
            'globalsign' => 'GlobalSign',
            'entrust' => 'Entrust',
            'verisign' => 'VeriSign',
            'thawte' => 'Thawte',
            'geotrust' => 'GeoTrust',
            'let\'s encrypt' => 'Let\'s Encrypt',
            'certipost' => 'Certipost (Belgium)',
            'a-trust' => 'A-Trust (Austria)',
            'swisscom' => 'Swisscom (Switzerland)',
        ];
        
        foreach ($providers as $keyword => $providerName) {
            if (strpos($searchText, $keyword) !== false) {
                return $providerName;
            }
        }
        
        // Return issuer org or CN if no known provider matched
        return $issuerOrg ?? $issuerCN ?? 'Sconosciuto';
    }
    
    /**
     * Merge PDF-level metadata (/M date, /Reason, /Location, /Name) into signatures.
     * 
     * @param string $pdfContent Raw PDF content
     * @param array &$signatures Signatures array (modified in-place)
     */
    private static function mergePdfMetadata($pdfContent, &$signatures) {
        // Extract PDF signature dictionaries for metadata
        $pdfMeta = [];
        
        // Find signature value objects that contain /M, /Reason, /Location, /Name
        // These are in the /Sig or /Value dictionaries
        $sigDictPattern = '/\/Type\s*\/Sig.*?(?=endobj)/s';
        if (preg_match_all($sigDictPattern, $pdfContent, $dictMatches)) {
            foreach ($dictMatches[0] as $idx => $dict) {
                $meta = [];
                
                // /M date
                if (preg_match('/\/M\s*\((D:[\d\+\-Z\']+)\)/', $dict, $m)) {
                    $meta['signature_date'] = self::parsePDFDate($m[1]);
                }
                
                // /Reason
                if (preg_match('/\/Reason\s*\(([^)]*)\)/s', $dict, $m)) {
                    $meta['reason'] = self::decodePdfString(trim($m[1]));
                }
                
                // /Location
                if (preg_match('/\/Location\s*\(([^)]*)\)/s', $dict, $m)) {
                    $meta['location'] = self::decodePdfString(trim($m[1]));
                }
                
                // /Name (PDF-level signer name, lower priority than cert CN)
                if (preg_match('/\/Name\s*\(([^)]*)\)/s', $dict, $m)) {
                    $meta['pdf_name'] = self::decodePdfString(trim($m[1]));
                }
                
                // /ContactInfo
                if (preg_match('/\/ContactInfo\s*\(([^)]*)\)/s', $dict, $m)) {
                    $meta['contact_info'] = self::decodePdfString(trim($m[1]));
                }
                
                $pdfMeta[] = $meta;
            }
        }
        
        // Merge metadata into signatures
        foreach ($signatures as $idx => &$sig) {
            if (isset($pdfMeta[$idx])) {
                $meta = $pdfMeta[$idx];
                
                // Use PDF /M date if we don't have a signing date from the cert
                if (empty($sig['signature_date']) && !empty($meta['signature_date'])) {
                    $sig['signature_date'] = $meta['signature_date'];
                }
                
                if (empty($sig['reason']) && !empty($meta['reason'])) {
                    $sig['reason'] = $meta['reason'];
                }
                
                if (empty($sig['location']) && !empty($meta['location'])) {
                    $sig['location'] = $meta['location'];
                }
                
                // Use PDF /Name only if signer_name is still unknown
                if (($sig['signer_name'] === 'Sconosciuto' || empty($sig['signer_name']))
                    && !empty($meta['pdf_name'])) {
                    $sig['signer_name'] = $meta['pdf_name'];
                }
            }
        }
        unset($sig);
    }
    
    /**
     * Fallback: Parse PDF signature dictionaries directly when PKCS#7 extraction fails.
     * 
     * @param string $pdfContent Raw PDF content
     * @return array Array of signature info
     */
    private static function fallbackPdfParsing($pdfContent) {
        $signatures = [];
        
        // Look for ByteRange entries which confirm signatures exist
        $hasByteRange = preg_match_all('/\/ByteRange\s*\[/', $pdfContent, $brMatches);
        
        // Find signature dictionaries  
        $sigDictPattern = '/\/Type\s*\/Sig.*?(?=endobj)/s';
        $sigCount = 0;
        
        if (preg_match_all($sigDictPattern, $pdfContent, $dictMatches)) {
            foreach ($dictMatches[0] as $idx => $dict) {
                $sigCount++;
                $info = [
                    'number' => $sigCount,
                    'signer_name' => 'Sconosciuto',
                    'signer_organization' => null,
                    'fiscal_code' => null,
                    'signature_date' => null,
                    'reason' => null,
                    'location' => null,
                    'ca_provider' => null,
                    'issuer' => null,
                    'certificate_valid_from' => null,
                    'certificate_valid_to' => null,
                    'serial_number' => null,
                    'certificate_info' => null
                ];
                
                if (preg_match('/\/Name\s*\(([^)]*)\)/s', $dict, $m)) {
                    $name = self::decodePdfString(trim($m[1]));
                    if (!empty($name)) {
                        $info['signer_name'] = $name;
                    }
                }
                
                if (preg_match('/\/M\s*\((D:[\d\+\-Z\']+)\)/', $dict, $m)) {
                    $info['signature_date'] = self::parsePDFDate($m[1]);
                }
                
                if (preg_match('/\/Reason\s*\(([^)]*)\)/s', $dict, $m)) {
                    $info['reason'] = self::decodePdfString(trim($m[1]));
                }
                
                if (preg_match('/\/Location\s*\(([^)]*)\)/s', $dict, $m)) {
                    $info['location'] = self::decodePdfString(trim($m[1]));
                }
                
                $signatures[] = $info;
            }
        }
        
        // If no /Type /Sig found but ByteRange exists, there are signatures we can't fully parse
        if (empty($signatures) && $hasByteRange) {
            for ($i = 0; $i < $hasByteRange; $i++) {
                $signatures[] = [
                    'number' => $i + 1,
                    'signer_name' => 'Sconosciuto',
                    'signer_organization' => null,
                    'fiscal_code' => null,
                    'signature_date' => null,
                    'reason' => 'Firma digitale',
                    'location' => null,
                    'ca_provider' => null,
                    'issuer' => null,
                    'certificate_valid_from' => null,
                    'certificate_valid_to' => null,
                    'serial_number' => null,
                    'certificate_info' => null
                ];
            }
        }
        
        return $signatures;
    }
    
    /**
     * Parse PDF date format (D:YYYYMMDDHHmmSS+HH'mm')
     * 
     * @param string $pdfDate PDF date string
     * @return string|null ISO 8601 date format or null
     */
    private static function parsePDFDate($pdfDate) {
        // Format: D:YYYYMMDDHHmmSS[+/-HH'mm']
        if (preg_match('/D:(\d{4})(\d{2})(\d{2})(\d{2})?(\d{2})?(\d{2})?/', $pdfDate, $matches)) {
            $year = $matches[1];
            $month = $matches[2];
            $day = $matches[3];
            $hour = $matches[4] ?? '00';
            $min = $matches[5] ?? '00';
            $sec = $matches[6] ?? '00';
            
            return sprintf('%s-%s-%s %s:%s:%s', $year, $month, $day, $hour, $min, $sec);
        }
        return null;
    }
    
    /**
     * Decode PDF string escapes.
     * 
     * @param string $str PDF string
     * @return string Decoded string
     */
    private static function decodePdfString($str) {
        // Handle PDF hex strings
        if (preg_match('/^<([0-9A-Fa-f]+)>$/', $str, $m)) {
            $decoded = hex2bin($m[1]);
            return $decoded !== false ? $decoded : $str;
        }
        
        // Handle octal escapes
        $str = preg_replace_callback('/\\\\(\d{3})/', function($m) {
            return chr(octdec($m[1]));
        }, $str);
        
        // Handle common escapes
        $str = str_replace(['\\n', '\\r', '\\t', '\\(', '\\)', '\\\\'], ["\n", "\r", "\t", '(', ')', '\\'], $str);
        
        return $str;
    }
    
    /**
     * Assess overall validity from extracted signatures.
     * 
     * @param array $signatures Extracted signatures
     * @return string Validity status
     */
    private static function assessValidity($signatures) {
        if (empty($signatures)) {
            return self::SIGNATURE_VALIDITY_UNKNOWN;
        }
        
        $hasIdentified = false;
        foreach ($signatures as $sig) {
            if (!empty($sig['signer_name']) && $sig['signer_name'] !== 'Sconosciuto') {
                $hasIdentified = true;
                
                // Check if certificate is still valid
                if (!empty($sig['certificate_valid_to'])) {
                    $expiry = strtotime($sig['certificate_valid_to']);
                    if ($expiry !== false && $expiry < time()) {
                        return self::SIGNATURE_VALIDITY_INVALID;
                    }
                }
            }
        }
        
        return $hasIdentified ? self::SIGNATURE_VALIDITY_VALID : self::SIGNATURE_VALIDITY_UNKNOWN;
    }
    
    /**
     * Format signature data for display.
     * 
     * @param array $signatureData Raw signature data array
     * @return string Formatted text representation
     */
    public static function formatSignatureInfo($signatureData) {
        if (empty($signatureData) || !isset($signatureData['signatures'])) {
            return 'Nessuna firma trovata';
        }
        
        $output = [];
        foreach ($signatureData['signatures'] as $sig) {
            $text = sprintf(
                "Firma #%d: %s",
                $sig['number'],
                $sig['signer_name'] ?? 'Sconosciuto'
            );
            
            if (!empty($sig['signer_organization'])) {
                $text .= sprintf(" (%s)", $sig['signer_organization']);
            }
            
            if (!empty($sig['signature_date'])) {
                $text .= sprintf(" - %s", $sig['signature_date']);
            }
            
            if (!empty($sig['ca_provider'])) {
                $text .= sprintf(" [CA: %s]", $sig['ca_provider']);
            }
            
            $output[] = $text;
        }
        
        return !empty($output) ? implode("; ", $output) : 'Nessuna firma trovata';
    }
    
    /**
     * Check if a file is a CAdES (.p7m) signed file.
     * 
     * @param string $filePath Path to file
     * @return bool True if file is CAdES format
     */
    public static function isCadesFile($filePath) {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($ext === 'p7m') {
            return true;
        }
        
        // Check binary content for PKCS#7 signedData OID
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return false;
        }
        $content = file_get_contents($filePath, false, null, 0, 256);
        if ($content !== false) {
            // OID 1.2.840.113549.1.7.2 (signedData) in DER encoding
            if (strpos($content, "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x07\x02") !== false) {
                return true;
            }
        }
        
        return false;
    }
}
