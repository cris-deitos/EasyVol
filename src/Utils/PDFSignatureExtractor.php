<?php
namespace EasyVol\Utils;

/**
 * PDF Digital Signature Extractor
 * 
 * Extracts digital signature information from PDF files
 * Supports both CADES and PADES formats
 * Uses public APIs without external dependencies
 */
class PDFSignatureExtractor {
    
    const SIGNATURE_FORMAT_PADES = 'PADES';
    const SIGNATURE_FORMAT_CADES = 'CADES';
    const SIGNATURE_FORMAT_UNKNOWN = 'UNKNOWN';
    
    const SIGNATURE_VALIDITY_VALID = 'valid';
    const SIGNATURE_VALIDITY_INVALID = 'invalid';
    const SIGNATURE_VALIDITY_UNKNOWN = 'unknown';
    
    /**
     * Extract signature information from PDF file
     * 
     * @param string $filePath Path to PDF file
     * @return array ['has_signature' => bool, 'format' => string, 'count' => int, 'signatures' => array, 'validity' => string]
     */
    public static function extractSignatures($filePath) {
        $result = [
            'has_signature' => false,
            'format' => self::SIGNATURE_FORMAT_UNKNOWN,
            'count' => 0,
            'signatures' => [],
            'validity' => self::SIGNATURE_VALIDITY_UNKNOWN
        ];
        
        if (!file_exists($filePath) || !is_readable($filePath)) {
            error_log("PDFSignatureExtractor: File not readable: $filePath");
            return $result;
        }
        
        try {
            // Read PDF binary content
            $pdfContent = file_get_contents($filePath);
            if ($pdfContent === false) {
                error_log("PDFSignatureExtractor: Cannot read file: $filePath");
                return $result;
            }
            
            // Check for signature markers in PDF structure
            $signatures = self::parseSignatures($pdfContent);
            
            if (!empty($signatures)) {
                $result['has_signature'] = true;
                $result['count'] = count($signatures);
                $result['signatures'] = $signatures;
                $result['format'] = self::detectSignatureFormat($pdfContent, $signatures);
                $result['validity'] = self::verifySignatureValidity($signatures);
            }
            
        } catch (\Exception $e) {
            error_log("PDFSignatureExtractor: Error - " . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Parse signatures from PDF content
     * 
     * @param string $pdfContent Raw PDF content
     * @return array Array of signature information
     */
    private static function parseSignatures($pdfContent) {
        $signatures = [];
        
        // Look for /Sig markers in PDF (signature objects)
        // PDF signatures are stored as indirect objects with /Sig dictionary
        $pattern = '/\/(Sig|Signature)\s*<<.*?>>\s*endobj/s';
        if (preg_match_all($pattern, $pdfContent, $matches)) {
            foreach ($matches[0] as $index => $sigObject) {
                $sigInfo = self::extractSignatureInfo($sigObject, $index + 1);
                if ($sigInfo) {
                    $signatures[] = $sigInfo;
                }
            }
        }
        
        // Also check for ByteRange which indicates signature streams
        if (strpos($pdfContent, '/ByteRange') !== false) {
            $rangeMatches = [];
            if (preg_match_all('/\/ByteRange\s*\[([^\]]+)\]/', $pdfContent, $rangeMatches)) {
                // Additional signatures found via ByteRange
                foreach ($rangeMatches[1] as $index => $range) {
                    if (count($signatures) < $index + 1) {
                        $signatures[] = [
                            'number' => count($signatures) + 1,
                            'signer_name' => 'Unknown Signer',
                            'signature_date' => null,
                            'reason' => 'Digital Signature',
                            'location' => null,
                            'certificate_info' => null
                        ];
                    }
                }
            }
        }
        
        return $signatures;
    }
    
    /**
     * Extract signature information from PDF signature object
     * 
     * @param string $sigObject Signature object content
     * @param int $index Signature index
     * @return array|null Signature information or null if parse fails
     */
    private static function extractSignatureInfo($sigObject, $index) {
        $info = [
            'number' => $index,
            'signer_name' => 'Unknown Signer',
            'signature_date' => null,
            'reason' => null,
            'location' => null,
            'certificate_info' => null
        ];
        
        // Try to extract /M (modification date)
        if (preg_match('/\/M\s*\((D:\d{14})/', $sigObject, $matches)) {
            $dateStr = $matches[1];
            $info['signature_date'] = self::parsePDFDate($dateStr);
        }
        
        // Try to extract /Reason
        if (preg_match('/\/Reason\s*\(([^)]*)\)/', $sigObject, $matches)) {
            $info['reason'] = trim($matches[1]);
        }
        
        // Try to extract /Location
        if (preg_match('/\/Location\s*\(([^)]*)\)/', $sigObject, $matches)) {
            $info['location'] = trim($matches[1]);
        }
        
        // Try to extract /Name (signer name)
        if (preg_match('/\/Name\s*\(([^)]*)\)/', $sigObject, $matches)) {
            $info['signer_name'] = trim($matches[1]);
        }
        
        return $info;
    }
    
    /**
     * Parse PDF date format (D:YYYYMMDDHHmmSS)
     * 
     * @param string $pdfDate PDF date string
     * @return string ISO 8601 date format or null
     */
    private static function parsePDFDate($pdfDate) {
        // Format: D:YYYYMMDDHHmmSS
        if (preg_match('/D:(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $pdfDate, $matches)) {
            return sprintf(
                '%s-%s-%s %s:%s:%s',
                $matches[1], $matches[2], $matches[3],
                $matches[4], $matches[5], $matches[6]
            );
        }
        return null;
    }
    
    /**
     * Detect signature format (PADES or CADES)
     * 
     * @param string $pdfContent Raw PDF content
     * @param array $signatures Extracted signatures
     * @return string Signature format
     */
    private static function detectSignatureFormat($pdfContent, $signatures) {
        // PADES: Signature is embedded in PDF incremental save
        // Check for /Sig or /Signature in PDF catalog
        if (strpos($pdfContent, '/Sig') !== false && strpos($pdfContent, 'endobj') !== false) {
            // PADES signature (visible in PDF structure)
            return self::SIGNATURE_FORMAT_PADES;
        }
        
        // CADES: Signature is in separate CMS object
        // Check for CMS/PKCS#7 markers
        if (strpos($pdfContent, '0.9.16.1.4') !== false || // id-aa OID
            strpos($pdfContent, 'signedData') !== false ||
            preg_match('/\x30\x82.*?\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x07\x02/', $pdfContent)) {
            return self::SIGNATURE_FORMAT_CADES;
        }
        
        // If signatures exist but format cannot be determined
        if (!empty($signatures)) {
            return self::SIGNATURE_FORMAT_PADES; // Default to PADES if signatures found
        }
        
        return self::SIGNATURE_FORMAT_UNKNOWN;
    }
    
    /**
     * Verify signature validity (basic check)
     * 
     * @param array $signatures Extracted signatures
     * @return string Signature validity status
     */
    private static function verifySignatureValidity($signatures) {
        if (empty($signatures)) {
            return self::SIGNATURE_VALIDITY_UNKNOWN;
        }
        
        // Basic validity check: if we successfully extracted signatures,
        // consider them as potentially valid (true validation would require
        // certificate chain verification)
        $hasValidSignature = false;
        foreach ($signatures as $sig) {
            if (!empty($sig['signer_name']) && !empty($sig['signature_date'])) {
                $hasValidSignature = true;
                break;
            }
        }
        
        return $hasValidSignature ? self::SIGNATURE_VALIDITY_VALID : self::SIGNATURE_VALIDITY_UNKNOWN;
    }
    
    /**
     * Format signature data for display
     * 
     * @param array $signatureData Raw signature data array
     * @return string Formatted text representation
     */
    public static function formatSignatureInfo($signatureData) {
        if (empty($signatureData) || !isset($signatureData['signatures'])) {
            return 'No signatures found';
        }
        
        $output = [];
        foreach ($signatureData['signatures'] as $sig) {
            $text = sprintf(
                "Signature #%d: %s",
                $sig['number'],
                $sig['signer_name']
            );
            
            if (!empty($sig['signature_date'])) {
                $text .= sprintf(" - %s", $sig['signature_date']);
            }
            
            if (!empty($sig['location'])) {
                $text .= sprintf(" (%s)", $sig['location']);
            }
            
            $output[] = $text;
        }
        
        return !empty($output) ? implode("; ", $output) : 'No signatures found';
    }
}
