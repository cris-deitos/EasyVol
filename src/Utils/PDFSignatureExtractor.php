<?php
namespace EasyVol\Utils;

/**
 * PDF Digital Signature Extractor – 100% PHP native implementation
 *
 * Extracts digital signature information from PDF (PAdES) and P7M (CAdES) files
 * using only PHP's built-in openssl_* functions and pure-PHP parsing. No exec(),
 * shell_exec(), proc_open(), popen() or any other OS-level call is used, making
 * this suitable for shared-hosting environments where those functions are disabled.
 *
 * Validity note: "valid" means the signing certificate was not expired at the
 * time of extraction. This is NOT a full cryptographic verification of the
 * document's integrity or the trust chain. A complete signature validation
 * would require a trusted CA store and content digest verification, which is
 * beyond the scope of this utility.
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
     * Extract signatures from a CAdES .p7m file using native PHP openssl functions.
     *
     * @param string $filePath Path to .p7m file
     * @return array Array of signature info
     */
    private static function extractCadesSignatures($filePath) {
        $signatures = [];

        $raw = @file_get_contents($filePath);
        if ($raw === false || strlen($raw) < 16) {
            error_log("PDFSignatureExtractor: Cannot read CAdES file: $filePath");
            return $signatures;
        }

        $pem = self::derToPkcs7Pem($raw);
        if ($pem === null) {
            error_log("PDFSignatureExtractor: Could not convert CAdES to PEM: $filePath");
            return $signatures;
        }

        $certs = [];
        if (!@openssl_pkcs7_read($pem, $certs) || empty($certs)) {
            error_log("PDFSignatureExtractor: openssl_pkcs7_read failed for: $filePath");
            // Try to extract x509 certs directly from the DER blob as last resort
            $signatures = self::extractCertsFromDer($raw, 0);
        } else {
            $signatures = self::certsToSignatures($certs, 0);
        }

        // Inject signingTime from ASN.1 mini-parser
        $signingTimes = self::extractSigningTimesFromDer($raw);
        if (!empty($signingTimes)) {
            $single = (count($signingTimes) === 1 && count($signatures) === 1);
            foreach ($signatures as $idx => &$sig) {
                if (isset($signingTimes[$idx])) {
                    $sig['signature_date'] = $signingTimes[$idx];
                } elseif ($single) {
                    $sig['signature_date'] = reset($signingTimes);
                }
            }
            unset($sig);
        }

        return $signatures;
    }
    
    /**
     * Convert raw bytes (DER or already-PEM or base64 text) to a PKCS#7 PEM block.
     *
     * @param string $raw Raw file bytes
     * @return string|null PEM string or null on failure
     */
    private static function derToPkcs7Pem($raw) {
        // Already a PEM block?
        if (strpos($raw, '-----BEGIN') !== false) {
            // Normalise: if it's a CERTIFICATE block wrap it into PKCS7
            if (strpos($raw, '-----BEGIN PKCS7-----') !== false
                || strpos($raw, '-----BEGIN PKCS #7-----') !== false) {
                return $raw;
            }
            // May already be passable to openssl_pkcs7_read as-is
            return $raw;
        }

        // Is the whole file base64 text (some tools export it this way)?
        $stripped = preg_replace('/\s+/', '', $raw);
        if (preg_match('/^[A-Za-z0-9+\/]+=*$/', $stripped)) {
            $decoded = base64_decode($stripped, true);
            if ($decoded !== false && strlen($decoded) > 16) {
                $raw = $decoded;
            }
        }

        // Wrap DER as PKCS7 PEM
        $b64 = chunk_split(base64_encode($raw), 64, "\n");
        return "-----BEGIN PKCS7-----\n" . $b64 . "-----END PKCS7-----\n";
    }

    /**
     * Convert an array of PEM certificate strings (from openssl_pkcs7_read) into
     * the signature info array format.
     *
     * @param string[] $pemCerts Array of PEM certificate strings
     * @param int $startIndex Starting signature number offset
     * @return array Signature info array
     */
    private static function certsToSignatures(array $pemCerts, $startIndex = 0) {
        $certs = [];
        foreach ($pemCerts as $pem) {
            $parsed = @openssl_x509_parse($pem, true);
            if ($parsed === false || empty($parsed)) {
                continue;
            }
            $certs[] = $parsed;
        }

        // Filter: keep only end-entity (signer) certs, not CA / intermediate
        $signerCerts = self::filterSignerCertificatesParsed($certs);

        $signatures = [];
        $sigNum = $startIndex;
        foreach ($signerCerts as $cert) {
            $sigNum++;

            $subject  = $cert['subject']  ?? [];
            $issuer   = $cert['issuer']   ?? [];

            $cn   = $subject['CN']   ?? $subject['commonName']   ?? null;
            $org  = $subject['O']    ?? $subject['organizationName'] ?? null;
            $serialAttr = $subject['serialNumber'] ?? $subject['SERIALNUMBER'] ?? null;

            $signerName  = $cn ?? $org ?? 'Sconosciuto';
            $fiscalCode  = self::extractFiscalCodeFromArray($subject, $serialAttr);

            $issuerCN  = $issuer['CN'] ?? $issuer['commonName']   ?? null;
            $issuerOrg = $issuer['O']  ?? $issuer['organizationName'] ?? null;

            // Build full issuer DN string for identifyCAProvider
            $issuerDn = self::buildDnString($issuer);

            $caProvider = self::identifyCAProvider($issuerOrg, $issuerCN, $issuerDn);

            $certValidFrom = isset($cert['validFrom_time_t'])
                ? date('Y-m-d H:i:s', (int)$cert['validFrom_time_t']) : null;
            $certValidTo   = isset($cert['validTo_time_t'])
                ? date('Y-m-d H:i:s', (int)$cert['validTo_time_t'])   : null;

            $serialHex = $cert['serialNumberHex'] ?? ($cert['serialNumber'] ?? null);

            $signatures[] = [
                'number'               => $sigNum,
                'signer_name'          => $signerName,
                'common_name'          => $cn,
                'signer_organization'  => $org,
                'organization'         => $org,
                'fiscal_code'          => $fiscalCode,
                'signature_date'       => null, // filled later by signingTime or /M
                'signing_time'         => null,
                'signing_date'         => null,
                'reason'               => null,
                'location'             => null,
                'ca_provider'          => $caProvider,
                'issuer'               => $issuerCN ?? $issuerOrg ?? $issuerDn,
                'issuer_organization'  => $issuerOrg,
                'certificate_valid_from' => $certValidFrom,
                'certificate_valid_to'   => $certValidTo,
                'serial_number'          => $serialHex,
                'certificate_info'       => [
                    'subject' => self::buildDnString($subject),
                    'issuer'  => $issuerDn,
                ],
            ];
        }

        return $signatures;
    }

    /**
     * Try to extract X.509 certificates directly from a raw DER blob by scanning
     * for SEQUENCE markers. Used as last-resort fallback when openssl_pkcs7_read fails.
     *
     * @param string $der Binary DER data
     * @param int $startIndex Starting number offset
     * @return array Signature info array (may be empty)
     */
    private static function extractCertsFromDer($der, $startIndex = 0) {
        $signatures = [];
        $len = strlen($der);
        $i = 0;
        while ($i < $len - 4) {
            // Look for SEQUENCE (0x30) which may be a certificate
            if (ord($der[$i]) !== 0x30) {
                $i++;
                continue;
            }
            // Read DER length
            $lenInfo = self::readDerLength($der, $i + 1);
            if ($lenInfo === null) {
                $i++;
                continue;
            }
            [$contentLen, $headerLen] = $lenInfo;
            $totalLen = $headerLen + $contentLen;
            if ($i + $totalLen > $len || $totalLen < 64) {
                $i++;
                continue;
            }
            $candidate = substr($der, $i, $totalLen);
            $pem = "-----BEGIN CERTIFICATE-----\n"
                 . chunk_split(base64_encode($candidate), 64, "\n")
                 . "-----END CERTIFICATE-----\n";
            $parsed = @openssl_x509_parse($pem, true);
            if ($parsed !== false && !empty($parsed['subject'])) {
                $sigs = self::certsToSignatures([$pem], $startIndex + count($signatures));
                if (!empty($sigs)) {
                    $signatures = array_merge($signatures, $sigs);
                }
                $i += $totalLen;
                continue;
            }
            $i++;
        }
        return $signatures;
    }

    /**
     * Mini ASN.1 DER parser: scan a PKCS#7/CMS DER blob for signingTime attributes.
     *
     * signingTime OID (1.2.840.113549.1.9.5) in DER:
     *   06 09 2A 86 48 86 F7 0D 01 09 05
     *
     * Immediately following the OID we expect a SET containing a UTCTIME (0x17)
     * or GENERALIZEDTIME (0x18) value.
     *
     * @param string $der Binary DER data
     * @return array Array of signing date strings (Y-m-d H:i:s)
     */
    private static function extractSigningTimesFromDer($der) {
        $times = [];
        $signingTimeOid = "\x06\x09\x2A\x86\x48\x86\xF7\x0D\x01\x09\x05";
        $oidLen = strlen($signingTimeOid);
        $len = strlen($der);
        $pos = 0;

        while (($pos = strpos($der, $signingTimeOid, $pos)) !== false) {
            $pos += $oidLen;
            // Skip optional SET/SEQUENCE wrapper(s) to get to the time tag
            $attempts = 0;
            while ($pos < $len - 2 && $attempts < 4) {
                $tag = ord($der[$pos]);
                if ($tag === 0x17 || $tag === 0x18) {
                    break; // found UTCTIME or GENERALIZEDTIME
                }
                // Skip over wrapper tag + length + (maybe zero-length)
                if ($pos + 1 >= $len) {
                    break;
                }
                $lenInfo = self::readDerLength($der, $pos + 1);
                if ($lenInfo === null) {
                    break;
                }
                // If the wrapper length covers a time tag inside, enter it
                $pos += 1 + $lenInfo[1]; // move past tag+header into content
                $attempts++;
            }
            if ($pos >= $len - 2) {
                continue;
            }
            $tag = ord($der[$pos]);
            if ($tag !== 0x17 && $tag !== 0x18) {
                continue;
            }
            $lenInfo = self::readDerLength($der, $pos + 1);
            if ($lenInfo === null) {
                continue;
            }
            [$valueLen, $headerLen] = $lenInfo;
            $valueStart = $pos + 1 + $headerLen;
            if ($valueStart + $valueLen > $len) {
                continue;
            }
            $timeStr = substr($der, $valueStart, $valueLen);
            $ts = self::parseAsn1Time($tag, $timeStr);
            if ($ts !== false) {
                $times[] = date('Y-m-d H:i:s', $ts);
            } else {
                error_log("PDFSignatureExtractor: Failed to parse ASN.1 time tag=0x" . dechex($tag) . " val=" . bin2hex($timeStr));
            }
            $pos = $valueStart + $valueLen;
        }

        return $times;
    }

    /**
     * Read a DER length field starting at $offset in $data.
     *
     * @param string $data Binary data
     * @param int $offset Byte offset of the length field
     * @return array|null [contentLength, headerBytes] or null on error
     */
    private static function readDerLength($data, $offset) {
        $len = strlen($data);
        if ($offset >= $len) {
            return null;
        }
        $first = ord($data[$offset]);
        if ($first < 0x80) {
            return [$first, 1];
        }
        if ($first === 0x80) {
            return null; // indefinite length not supported here
        }
        $numBytes = $first & 0x7F;
        if ($numBytes > 4 || $offset + $numBytes >= $len) {
            return null;
        }
        $value = 0;
        for ($i = 1; $i <= $numBytes; $i++) {
            $value = ($value << 8) | ord($data[$offset + $i]);
        }
        return [$value, 1 + $numBytes];
    }

    /**
     * Parse an ASN.1 UTCTIME (0x17) or GENERALIZEDTIME (0x18) byte string into a Unix timestamp.
     *
     * @param int $tag 0x17 or 0x18
     * @param string $value Raw bytes of the time value
     * @return int|false Unix timestamp or false on failure
     */
    private static function parseAsn1Time($tag, $value) {
        // Strip any trailing Z or timezone suffix for initial matching
        $s = rtrim($value, "Z \x00");
        if ($tag === 0x17) {
            // UTCTIME: YYMMDDHHMMSS[Z]
            if (preg_match('/^(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $s, $m)) {
                $year = (int)$m[1];
                $year += ($year >= 50) ? 1900 : 2000;
                return gmmktime((int)$m[4], (int)$m[5], (int)$m[6], (int)$m[2], (int)$m[3], $year);
            }
        } else {
            // GENERALIZEDTIME: YYYYMMDDHHMMSS[.frac][Z]
            if (preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $s, $m)) {
                return gmmktime((int)$m[4], (int)$m[5], (int)$m[6], (int)$m[2], (int)$m[3], (int)$m[1]);
            }
        }
        // Last resort: let PHP try
        $ts = @strtotime($value . ' UTC');
        return ($ts !== false && $ts > 0) ? $ts : false;
    }
    
    /**
     * Extract PAdES signatures from PDF content using native PHP openssl functions.
     * Locates PKCS#7 signature blobs in the PDF ByteRange structure and parses
     * certificates with openssl_pkcs7_read() / openssl_x509_parse().
     *
     * @param string $pdfContent Raw PDF binary content
     * @param string $filePath Original file path (unused, kept for API compatibility)
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

            // Convert DER blob to PKCS7 PEM
            $pem = self::derToPkcs7Pem($blobBin);
            if ($pem === null) {
                continue;
            }

            $certs = [];
            $parsed = [];
            if (@openssl_pkcs7_read($pem, $certs) && !empty($certs)) {
                $parsed = self::certsToSignatures($certs, $index);
            } else {
                // Fallback: try to find raw X.509 certs inside the DER blob
                $parsed = self::extractCertsFromDer($blobBin, $index);
            }

            if (!empty($parsed)) {
                // Inject signingTime from this blob's DER
                $signingTimes = self::extractSigningTimesFromDer($blobBin);
                if (!empty($signingTimes)) {
                    $single = (count($signingTimes) === 1 && count($parsed) === 1);
                    foreach ($parsed as $idx => &$sig) {
                        if (isset($signingTimes[$idx])) {
                            $sig['signature_date'] = $signingTimes[$idx];
                        } elseif ($single) {
                            $sig['signature_date'] = reset($signingTimes);
                        }
                    }
                    unset($sig);
                }
                $signatures = array_merge($signatures, $parsed);
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
        $result = @preg_match_all('/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/', $pdfContent, $brMatches, PREG_SET_ORDER);
        if ($result === false) {
            error_log("PDFSignatureExtractor: PCRE error searching ByteRange in PDF");
            return $blobs;
        }
        
        if ($result > 0) {
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
                    // Strip trailing zero-padding (PDF /Contents fields are pre-allocated
                    // and padded with zeros; the actual PKCS#7 DER data is shorter)
                    $hexChunk = self::trimHexDerPadding($hexChunk);
                    if (strlen($hexChunk) >= 128) {
                        $blobs[] = $hexChunk;
                    }
                }
            }
        }
        
        return $blobs;
    }
    
    /**
     * Trim trailing zero-padding from a hex-encoded DER structure.
     * 
     * PDF signature /Contents fields are typically pre-allocated to a fixed size
     * (e.g. 8192 or 16384 bytes) and padded with trailing zeros. The actual PKCS#7
     * DER data is shorter. This method parses the DER header to determine the
     * actual data length and strips the padding.
     * 
     * All internal calculations use byte counts; conversion to hex string
     * positions (2 hex chars per byte) happens at the final substr() call.
     * 
     * @param string $hex Hex-encoded DER data (possibly with trailing zero padding)
     * @return string Trimmed hex string containing only the actual DER structure
     */
    private static function trimHexDerPadding($hex) {
        if (strlen($hex) < 4) {
            return $hex;
        }
        
        // DER format: Tag (1 byte) + Length (1..N bytes) + Content
        // All lengths below are in BYTES (not hex chars).
        $lenByte = hexdec(substr($hex, 2, 2)); // 2nd byte (offset 2 in hex)
        
        if ($lenByte < 0x80) {
            // Short form: length is the byte value itself
            $headerBytes = 2; // 1 byte tag + 1 byte length
            $contentBytes = $lenByte;
        } elseif ($lenByte === 0x80) {
            // Indefinite length - can't trim, return as-is
            return $hex;
        } else {
            // Long form: lower 7 bits = number of subsequent bytes encoding the length
            $numLenBytes = $lenByte & 0x7F;
            if ($numLenBytes > 4 || $numLenBytes === 0) {
                // Unreasonable length encoding, return as-is
                return $hex;
            }
            $headerBytes = 1 + 1 + $numLenBytes; // tag + len-of-len + N length bytes
            $contentBytes = 0;
            for ($i = 0; $i < $numLenBytes; $i++) {
                $hexOffset = 4 + ($i * 2); // skip tag(2 hex) + len-of-len(2 hex)
                if ($hexOffset + 2 > strlen($hex)) {
                    return $hex; // hex too short to read length
                }
                $contentBytes = ($contentBytes << 8) | hexdec(substr($hex, $hexOffset, 2));
            }
        }
        
        // Convert total byte count to hex char count (2 hex chars per byte)
        $totalHexChars = ($headerBytes + $contentBytes) * 2;
        
        if ($totalHexChars > 0 && $totalHexChars <= strlen($hex)) {
            return substr($hex, 0, $totalHexChars);
        }
        
        return $hex;
    }
    
    /**
     * Build a flat DN string from an associative array (as returned by openssl_x509_parse).
     *
     * @param array $dn Associative DN array
     * @return string DN string, e.g. "CN=Mario Rossi, O=Aruba PEC S.p.A., C=IT"
     */
    private static function buildDnString(array $dn) {
        $parts = [];
        foreach ($dn as $k => $v) {
            if (is_array($v)) {
                $v = implode('/', $v);
            }
            $parts[] = $k . '=' . $v;
        }
        return implode(', ', $parts);
    }

    /**
     * Filter out CA/intermediate certificates, keeping only end-entity signer certs.
     * Works on the parsed arrays returned by openssl_x509_parse().
     *
     * @param array $certs Array of parsed cert arrays (each has 'subject' and 'issuer' arrays)
     * @return array Filtered array
     */
    private static function filterSignerCertificatesParsed(array $certs) {
        if (empty($certs)) {
            return [];
        }

        // Precompute both subject and issuer DN strings to avoid O(n²) re-computation
        $subjectDns = [];
        $issuerDns  = [];
        foreach ($certs as $idx => $cert) {
            $subjectDns[$idx] = self::buildDnString($cert['subject'] ?? []);
            $issuerDns[$idx]  = self::buildDnString($cert['issuer']  ?? []);
        }

        $signerCerts = [];
        foreach ($certs as $idx => $cert) {
            $subjectDn = $subjectDns[$idx];
            $issuerDn  = $issuerDns[$idx];

            // Skip self-signed certs (CA root)
            if ($subjectDn === $issuerDn) {
                continue;
            }

            // Skip if this cert's subject is the issuer of another cert
            $isIssuer = false;
            foreach ($issuerDns as $otherIdx => $otherIssuerDn) {
                if ($otherIssuerDn === $subjectDn && $subjectDns[$otherIdx] !== $subjectDn) {
                    $isIssuer = true;
                    break;
                }
            }
            if (!$isIssuer) {
                $signerCerts[] = $cert;
            }
        }

        if (empty($signerCerts)) {
            foreach ($certs as $idx => $cert) {
                if ($subjectDns[$idx] !== $issuerDns[$idx]) {
                    $signerCerts[] = $cert;
                }
            }
        }

        return !empty($signerCerts) ? $signerCerts : $certs;
    }

    /**
     * Extract Italian fiscal code from an openssl_x509_parse subject array.
     *
     * @param array $subject Subject array from openssl_x509_parse
     * @param string|null $serialAttr serialNumber attribute value (if any)
     * @return string|null
     */
    private static function extractFiscalCodeFromArray(array $subject, $serialAttr = null) {
        $pattern = '/\b([A-Z]{6}\d{2}[A-EHLMPRST]\d{2}[A-Z]\d{3}[A-Z])\b/i';

        if (!empty($serialAttr) && preg_match($pattern, $serialAttr, $m)) {
            return strtoupper($m[1]);
        }

        // TINIT- prefix (common in Italian qualified certs)
        foreach ($subject as $v) {
            if (is_string($v) && preg_match('/TINIT-([A-Z]{6}\d{2}[A-EHLMPRST]\d{2}[A-Z]\d{3}[A-Z])/i', $v, $m)) {
                return strtoupper($m[1]);
            }
        }

        $flatSubject = implode(' ', array_map(function($v) { return is_string($v) ? $v : ''; }, $subject));
        if (preg_match($pattern, $flatSubject, $m)) {
            return strtoupper($m[1]);
        }

        return null;
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
        // Uses improved detection that handles PDFs without /Type /Sig
        $sigDicts = self::findSignatureDictionaries($pdfContent);
        if (!empty($sigDicts)) {
            foreach ($sigDicts as $idx => $dict) {
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
                    $sig['signing_time']   = $meta['signature_date'];
                    $sig['signing_date']   = $meta['signature_date'];
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
                    $sig['common_name'] = $sig['common_name'] ?? $meta['pdf_name'];
                }
            }

            // Sync alias fields from primary fields if not already set
            if (empty($sig['signing_time']) && !empty($sig['signature_date'])) {
                $sig['signing_time'] = $sig['signature_date'];
                $sig['signing_date'] = $sig['signature_date'];
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
        $hasByteRange = @preg_match_all('/\/ByteRange\s*\[/', $pdfContent, $brMatches);
        if ($hasByteRange === false) {
            $hasByteRange = 0;
        }
        
        // Find signature dictionaries using multiple patterns:
        // 1. /Type /Sig (standard but optional per PDF spec)
        // 2. /Filter /Adobe.PPKLite (common PAdES filter)
        // 3. /SubFilter /adbe.pkcs7 or /ETSI.CAdES (signature sub-filters)
        $sigDicts = self::findSignatureDictionaries($pdfContent);
        $sigCount = 0;
        
        if (!empty($sigDicts)) {
            foreach ($sigDicts as $dict) {
                $sigCount++;
                $info = [
                    'number' => $sigCount,
                    'signer_name' => 'Sconosciuto',
                    'common_name' => null,
                    'signer_organization' => null,
                    'organization' => null,
                    'fiscal_code' => null,
                    'signature_date' => null,
                    'signing_time' => null,
                    'signing_date' => null,
                    'reason' => null,
                    'location' => null,
                    'ca_provider' => null,
                    'issuer' => null,
                    'issuer_organization' => null,
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
        
        // If no signature dictionaries found but ByteRange exists, there are signatures we can't fully parse
        if (empty($signatures) && $hasByteRange) {
            for ($i = 0; $i < $hasByteRange; $i++) {
                $signatures[] = [
                    'number' => $i + 1,
                    'signer_name' => 'Sconosciuto',
                    'common_name' => null,
                    'signer_organization' => null,
                    'organization' => null,
                    'fiscal_code' => null,
                    'signature_date' => null,
                    'signing_time' => null,
                    'signing_date' => null,
                    'reason' => 'Firma digitale',
                    'location' => null,
                    'ca_provider' => null,
                    'issuer' => null,
                    'issuer_organization' => null,
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
     * Find signature dictionaries in PDF content using multiple detection patterns.
     * 
     * The /Type /Sig key is optional per the PDF specification. Many signing tools
     * (especially Italian qualified signature tools) omit it. This method also
     * detects signatures via /Filter and /SubFilter entries.
     * 
     * @param string $pdfContent Raw PDF content
     * @return array Array of matched dictionary text blocks
     */
    private static function findSignatureDictionaries($pdfContent) {
        $dicts = [];
        $seen = []; // Track start offsets to avoid duplicates
        
        // Pattern 1: /Type /Sig (standard)
        $patterns = [
            '/\/Type\s*\/Sig[^a-zA-Z].*?(?=endobj)/s',
            '/\/Filter\s*\/Adobe\.PPKLite.*?(?=endobj)/s',
            '/\/Filter\s*\/Adobe\.PPKMS.*?(?=endobj)/s',
            '/\/SubFilter\s*\/adbe\.pkcs7\.detached.*?(?=endobj)/s',
            '/\/SubFilter\s*\/adbe\.pkcs7\.sha1.*?(?=endobj)/s',
            '/\/SubFilter\s*\/ETSI\.CAdES\.detached.*?(?=endobj)/s',
            '/\/SubFilter\s*\/ETSI\.RFC3161.*?(?=endobj)/s',
        ];
        
        foreach ($patterns as $pattern) {
            $matches = [];
            $result = @preg_match_all($pattern, $pdfContent, $matches, PREG_OFFSET_CAPTURE);
            if ($result === false) {
                error_log("PDFSignatureExtractor: PCRE error with pattern $pattern: " . preg_last_error());
                continue;
            }
            if ($result === 0) {
                continue;
            }
            
            foreach ($matches[0] as $match) {
                $text = $match[0];
                $offset = $match[1];
                
                // Find the object start (scan backwards for "obj" keyword)
                // to get the full dictionary context
                $objStart = strrpos(substr($pdfContent, 0, $offset), ' obj');
                if ($objStart === false) {
                    $objStart = $offset;
                } else {
                    // Extend to include content from after " obj" to endobj
                    $objStart += 4; // skip " obj"
                }
                
                // Deduplicate by object start offset — multiple patterns
                // may match different keywords in the same PDF object
                if (isset($seen[$objStart])) {
                    continue;
                }
                $seen[$objStart] = true;
                
                // Build the full object text from objStart to endobj,
                // not just from the matched pattern keyword. PDF dictionary
                // keys can appear in any order, so /ByteRange and /Contents
                // may precede the matched keyword (/Filter, /SubFilter, /Type).
                $endObjPos = strpos($pdfContent, 'endobj', $offset);
                $fullObjText = ($endObjPos !== false)
                    ? substr($pdfContent, $objStart, $endObjPos - $objStart)
                    : substr($pdfContent, $objStart, strlen($text) + ($offset - $objStart));
                
                // Verify this is actually a signature dictionary (must have /ByteRange or /Contents)
                if (strpos($fullObjText, '/ByteRange') !== false || strpos($fullObjText, '/Contents') !== false) {
                    $dicts[] = $fullObjText;
                }
            }
        }
        
        return $dicts;
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
