<?php
namespace EasyVol\Utils;

use DOMDocument;
use DOMXPath;
use Exception;

/**
 * Legacy XML Template Processor for GestionaleWeb format
 * 
 * Processes legacy XML templates from GestionaleWeb system
 * Format: <pdf><body><page><paragraph>, <image>, etc.
 * Variables: ${variable_name}
 * Blocks: <!-- $BeginBlock name --> ... <!-- $EndBlock name -->
 */
class LegacyXmlTemplateProcessor {
    
    /**
     * Mapping of legacy variable names to database field names
     */
    private const VARIABLE_MAPPINGS = [
        'matricola' => 'badge_number',
        'nome' => 'first_name',
        'cognome' => 'last_name',
        'data di nascita' => 'birth_date',
        'luogo di nascita' => 'birth_place',
        'provincia di nascita' => 'birth_province',
        'codice fiscale' => 'tax_code',
        'indirizzo di residenza' => 'address_street',
        'cap di residenza' => 'address_cap',
        'comune di residenza' => 'address_city',
        'provincia di residenza' => 'address_province',
        'data iscrizione' => 'member_since',
        'data approvazione' => 'approval_date',
        'data dimissioni/decadenza' => 'resignation_date',
    ];
    
    private $data;
    private $config;
    private $includes = [];
    
    /**
     * Constructor
     * 
     * @param array $config Configuration array
     */
    public function __construct($config = []) {
        $this->config = $config;
    }
    
    /**
     * Process legacy XML template with data
     * 
     * @param string $xmlContent XML template content
     * @param array $data Data for variable substitution
     * @return array Processed template data [html, css, format, orientation]
     */
    public function process($xmlContent, $data = []) {
        $this->data = $data;
        
        // Parse XML
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        
        // Load XML with error handling
        libxml_use_internal_errors(true);
        if (!$dom->loadXML($xmlContent)) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new Exception('Invalid XML: ' . $errors[0]->message);
        }
        
        $xpath = new DOMXPath($dom);
        
        // Extract template metadata
        $result = [
            'html' => '',
            'css' => '',
            'format' => 'A4',
            'orientation' => 'portrait',
            'margins' => [
                'top' => 20,
                'bottom' => 20,
                'left' => 15,
                'right' => 15
            ]
        ];
        
        // Get body settings
        $bodyNode = $xpath->query('//body')->item(0);
        if ($bodyNode) {
            $format = $bodyNode->getAttribute('format');
            if ($format) {
                $result['format'] = strtoupper($format);
            }
            
            $marginLeft = $bodyNode->getAttribute('marginleft');
            if ($marginLeft) {
                $result['margins']['left'] = (float)$marginLeft;
            }
            
            $marginBottom = $bodyNode->getAttribute('marginbottom');
            if ($marginBottom) {
                $result['margins']['bottom'] = (float)$marginBottom;
            }
        }
        
        // Process page nodes
        $pageNodes = $xpath->query('//page');
        $html = '';
        
        foreach ($pageNodes as $pageNode) {
            $orientation = $pageNode->getAttribute('orientation');
            if ($orientation === 'l') {
                $result['orientation'] = 'landscape';
            }
            
            $html .= $this->processPage($pageNode);
        }
        
        $result['html'] = $html;
        
        // Generate CSS for legacy format
        $result['css'] = $this->generateCss();
        
        return $result;
    }
    
    /**
     * Process a page node
     * 
     * @param \DOMElement $pageNode Page node
     * @return string HTML
     */
    private function processPage($pageNode) {
        $html = '<div class="page">';
        
        // Process all child elements
        foreach ($pageNode->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $html .= $this->processElement($child);
            } elseif ($child->nodeType === XML_COMMENT_NODE) {
                // Handle block directives
                $comment = trim($child->nodeValue);
                if (strpos($comment, '$BeginBlock') !== false || 
                    strpos($comment, '$EndBlock') !== false) {
                    // Block markers - keep for reference but don't output
                    continue;
                }
            }
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Process an element
     * 
     * @param \DOMElement $element Element to process
     * @return string HTML
     */
    private function processElement($element) {
        $tagName = $element->tagName;
        
        switch ($tagName) {
            case 'paragraph':
                return $this->processParagraph($element);
            case 'image':
                return $this->processImage($element);
            default:
                // Process children recursively
                $html = '';
                foreach ($element->childNodes as $child) {
                    if ($child->nodeType === XML_ELEMENT_NODE) {
                        $html .= $this->processElement($child);
                    } elseif ($child->nodeType === XML_TEXT_NODE) {
                        $text = trim($child->nodeValue);
                        if ($text) {
                            $html .= $this->replaceVariables($text);
                        }
                    }
                }
                return $html;
        }
    }
    
    /**
     * Process paragraph element
     * 
     * @param \DOMElement $element Paragraph element
     * @return string HTML
     */
    private function processParagraph($element) {
        $position = $element->getAttribute('position');
        $left = $element->getAttribute('left');
        $top = $element->getAttribute('top');
        $width = $element->getAttribute('width');
        $height = $element->getAttribute('height');
        $fontsize = $element->getAttribute('fontsize');
        $fontstyle = $element->getAttribute('fontstyle');
        $fontcolor = $element->getAttribute('fontcolor');
        $textalign = $element->getAttribute('textalign');
        $border = $element->getAttribute('border');
        $lineheight = $element->getAttribute('lineheight');
        
        // Build style
        $styles = [];
        
        if ($position === 'absolute') {
            $styles[] = 'position: absolute';
            if ($left) $styles[] = 'left: ' . $this->evaluateExpression($left) . 'mm';
            if ($top) $styles[] = 'top: ' . $this->evaluateExpression($top) . 'mm';
        } elseif ($position === 'relative') {
            $styles[] = 'display: inline-block';
            if ($left) {
                $leftVal = $this->evaluateExpression($left);
                if ($leftVal != 0) {
                    $styles[] = 'margin-left: ' . $leftVal . 'mm';
                }
            }
            if ($top) {
                $topVal = $this->evaluateExpression($top);
                if ($topVal != 0) {
                    $styles[] = 'margin-top: ' . $topVal . 'mm';
                }
            }
        }
        
        if ($width) $styles[] = 'width: ' . $this->evaluateExpression($width) . 'mm';
        if ($height) $styles[] = 'height: ' . $this->evaluateExpression($height) . 'mm';
        if ($fontsize) $styles[] = 'font-size: ' . $fontsize . 'pt';
        if ($lineheight) $styles[] = 'line-height: ' . $lineheight . 'mm';
        if ($fontcolor) $styles[] = 'color: ' . $fontcolor;
        
        if ($textalign) {
            $align = strtolower($textalign);
            $styles[] = 'text-align: ' . ($align === 'l' ? 'left' : ($align === 'c' ? 'center' : ($align === 'r' ? 'right' : $align)));
        }
        
        if ($border && $border !== '0') {
            $styles[] = 'border: 1px solid #000';
        }
        
        if ($fontstyle) {
            if (strpos($fontstyle, 'B') !== false) {
                $styles[] = 'font-weight: bold';
            }
            if (strpos($fontstyle, 'I') !== false) {
                $styles[] = 'font-style: italic';
            }
            if (strpos($fontstyle, 'U') !== false) {
                $styles[] = 'text-decoration: underline';
            }
        }
        
        $styles[] = 'padding: 1mm';
        
        $styleAttr = implode('; ', $styles);
        
        // Get content
        $content = $this->getNodeContent($element);
        $content = $this->replaceVariables($content);
        
        return '<div style="' . htmlspecialchars($styleAttr) . '">' . nl2br(htmlspecialchars($content)) . '</div>';
    }
    
    /**
     * Process image element
     * 
     * @param \DOMElement $element Image element
     * @return string HTML
     */
    private function processImage($element) {
        $file = $element->getAttribute('file');
        $position = $element->getAttribute('position');
        $left = $element->getAttribute('left');
        $top = $element->getAttribute('top');
        $width = $element->getAttribute('width');
        $height = $element->getAttribute('height');
        
        // Handle includes
        $file = $this->processIncludes($file);
        $file = $this->replaceVariables($file);
        
        // Build style
        $styles = [];
        
        if ($position === 'absolute') {
            $styles[] = 'position: absolute';
            if ($left) $styles[] = 'left: ' . $this->evaluateExpression($left) . 'mm';
            if ($top) $styles[] = 'top: ' . $this->evaluateExpression($top) . 'mm';
        }
        
        if ($width) $styles[] = 'width: ' . $width . 'mm';
        if ($height) $styles[] = 'height: ' . $height . 'mm';
        
        $styleAttr = implode('; ', $styles);
        
        return '<img src="' . htmlspecialchars($file) . '" style="' . htmlspecialchars($styleAttr) . '" />';
    }
    
    /**
     * Get node text content
     * 
     * @param \DOMNode $node Node
     * @return string Content
     */
    private function getNodeContent($node) {
        $content = '';
        
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $content .= $child->nodeValue;
            } elseif ($child->nodeType === XML_COMMENT_NODE) {
                $comment = trim($child->nodeValue);
                if (strpos($comment, '$Include') !== false) {
                    $content .= $this->processIncludes($child->nodeValue);
                }
            }
        }
        
        return $content;
    }
    
    /**
     * Process include directives
     * 
     * @param string $text Text with includes
     * @return string Processed text
     */
    private function processIncludes($text) {
        // Match <!-- $Include path -->
        if (preg_match('/<!--\s*\$Include\s+([^\s]+)\s*-->/', $text, $matches)) {
            $path = $matches[1];
            
            // Check if we have this include in our data
            if (isset($this->data['includes'][$path])) {
                return $this->data['includes'][$path];
            }
            
            // Return placeholder
            return '[Include: ' . $path . ']';
        }
        
        return $text;
    }
    
    /**
     * Replace variables in text
     * 
     * @param string $text Text with ${variable} placeholders
     * @return string Text with replaced variables
     */
    private function replaceVariables($text) {
        return preg_replace_callback('/\$\{([^}]+)\}/', function($matches) {
            $varName = trim($matches[1]);
            return $this->getVariableValue($varName, '');
        }, $text);
    }
    
    /**
     * Get variable value from data
     * 
     * @param string $name Variable name
     * @param mixed $default Default value
     * @return mixed Variable value
     */
    private function getVariableValue($name, $default = '') {
        // Normalize variable name (replace spaces with underscores)
        $normalizedName = str_replace(' ', '_', strtolower($name));
        
        // Try direct match first
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }
        
        // Try normalized match
        if (isset($this->data[$normalizedName])) {
            return $this->data[$normalizedName];
        }
        
        // Try with common field mappings using class constant
        if (isset(self::VARIABLE_MAPPINGS[$normalizedName])) {
            $mappedName = self::VARIABLE_MAPPINGS[$normalizedName];
            if (isset($this->data[$mappedName])) {
                return $this->data[$mappedName];
            }
        }
        
        return $default;
    }
    
    /**
     * Evaluate expression (for position calculations)
     * 
     * @param string $expr Expression like "5+7+27"
     * @return float Result
     */
    private function evaluateExpression($expr) {
        // Remove spaces
        $expr = str_replace(' ', '', $expr);
        
        // If it's just a number, return it
        if (is_numeric($expr)) {
            return (float)$expr;
        }
        
        // Safe evaluation for addition/subtraction only
        // Security: Only allow numbers, dots, and +/- operators
        if (!preg_match('/^[\d\.\+\-]+$/', $expr)) {
            return 0;
        }
        
        // Manual calculation without eval()
        $result = 0;
        $currentNumber = '';
        $operator = '+';
        
        for ($i = 0; $i < strlen($expr); $i++) {
            $char = $expr[$i];
            
            if ($char === '+' || $char === '-') {
                // Process previous number
                if ($currentNumber !== '') {
                    $num = (float)$currentNumber;
                    if ($operator === '+') {
                        $result += $num;
                    } else {
                        $result -= $num;
                    }
                    $currentNumber = '';
                }
                $operator = $char;
            } else {
                $currentNumber .= $char;
            }
        }
        
        // Process last number
        if ($currentNumber !== '') {
            $num = (float)$currentNumber;
            if ($operator === '+') {
                $result += $num;
            } else {
                $result -= $num;
            }
        }
        
        return $result;
    }
    
    /**
     * Generate CSS for legacy format
     * 
     * @return string CSS
     */
    private function generateCss() {
        return '
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10pt;
        }
        
        .page {
            position: relative;
            background: white;
            padding: 10mm;
            min-height: 277mm; /* A4 height minus margins */
        }
        
        .page > div {
            box-sizing: border-box;
        }
        ';
    }
    
    /**
     * Validate legacy XML template structure
     * 
     * @param string $xmlContent XML content
     * @return array [valid => bool, errors => array]
     */
    public function validate($xmlContent) {
        $errors = [];
        
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        
        if (!$dom->loadXML($xmlContent)) {
            foreach (libxml_get_errors() as $error) {
                $errors[] = "Line {$error->line}: {$error->message}";
            }
            libxml_clear_errors();
        }
        
        // Check for required root element
        if ($dom->documentElement && $dom->documentElement->tagName !== 'pdf') {
            $errors[] = 'Root element must be <pdf> for legacy format';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Detect if XML is in legacy format
     * 
     * @param string $xmlContent XML content
     * @return bool True if legacy format
     */
    public static function isLegacyFormat($xmlContent) {
        // Quick check for legacy format indicators
        return (strpos($xmlContent, '<pdf') !== false && 
                strpos($xmlContent, 'creator=') !== false) ||
               (strpos($xmlContent, '<paragraph') !== false &&
                strpos($xmlContent, '${') !== false);
    }
}
