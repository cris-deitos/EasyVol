<?php
namespace EasyVol\Utils;

use DOMDocument;
use DOMXPath;
use Exception;

/**
 * XML Template Processor
 * 
 * Processes XML-based print templates and converts them to HTML for PDF generation
 * Supports variable substitution, loops, conditionals, and formatting
 */
class XmlTemplateProcessor {
    
    private $data;
    private $config;
    
    /**
     * Constructor
     * 
     * @param array $config Configuration array
     */
    public function __construct($config = []) {
        $this->config = $config;
    }
    
    /**
     * Process XML template with data
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
        
        // Get page settings
        $pageNode = $xpath->query('//page')->item(0);
        if ($pageNode) {
            $result['format'] = $pageNode->getAttribute('format') ?: 'A4';
            $result['orientation'] = $pageNode->getAttribute('orientation') ?: 'portrait';
            
            // Get margins
            $marginNode = $xpath->query('//page/margins')->item(0);
            if ($marginNode) {
                $result['margins']['top'] = $marginNode->getAttribute('top') ?: 20;
                $result['margins']['bottom'] = $marginNode->getAttribute('bottom') ?: 20;
                $result['margins']['left'] = $marginNode->getAttribute('left') ?: 15;
                $result['margins']['right'] = $marginNode->getAttribute('right') ?: 15;
            }
        }
        
        // Get CSS styles
        $styleNode = $xpath->query('//styles')->item(0);
        if ($styleNode) {
            $result['css'] = $styleNode->nodeValue;
        }
        
        // Process body content
        $bodyNode = $xpath->query('//body')->item(0);
        if ($bodyNode) {
            $result['html'] = $this->processNode($bodyNode);
        }
        
        return $result;
    }
    
    /**
     * Process a DOM node recursively
     * 
     * @param \DOMNode $node Node to process
     * @return string Processed HTML
     */
    private function processNode($node) {
        $html = '';
        
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text = trim($child->nodeValue);
                if ($text) {
                    $html .= $this->replaceVariables($text);
                }
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $html .= $this->processElement($child);
            }
        }
        
        return $html;
    }
    
    /**
     * Process an XML element
     * 
     * @param \DOMElement $element Element to process
     * @return string Processed HTML
     */
    private function processElement($element) {
        $tagName = $element->tagName;
        
        // Handle special template tags
        switch ($tagName) {
            case 'variable':
                return $this->processVariable($element);
            case 'loop':
                return $this->processLoop($element);
            case 'condition':
                return $this->processCondition($element);
            case 'section':
                return $this->processSection($element);
            case 'table':
                return $this->processTable($element);
            case 'text':
                return $this->processText($element);
            case 'image':
                return $this->processImage($element);
            default:
                // Pass through as HTML tag
                return $this->processHtmlTag($element);
        }
    }
    
    /**
     * Process variable element
     * 
     * @param \DOMElement $element Variable element
     * @return string Variable value
     */
    private function processVariable($element) {
        $name = $element->getAttribute('name');
        $format = $element->getAttribute('format');
        $default = $element->getAttribute('default');
        
        $value = $this->getVariableValue($name, $default);
        
        // Apply formatting
        if ($format) {
            $value = $this->formatValue($value, $format);
        }
        
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Process loop element
     * 
     * @param \DOMElement $element Loop element
     * @return string Looped HTML
     */
    private function processLoop($element) {
        $source = $element->getAttribute('source');
        $items = $this->getVariableValue($source, []);
        
        if (!is_array($items)) {
            return '';
        }
        
        $html = '';
        $savedData = $this->data;
        
        foreach ($items as $index => $item) {
            // Set loop context
            $this->data['loop_item'] = $item;
            $this->data['loop_index'] = $index;
            
            // Process loop content
            $html .= $this->processNode($element);
        }
        
        // Restore original data
        $this->data = $savedData;
        
        return $html;
    }
    
    /**
     * Process condition element
     * 
     * @param \DOMElement $element Condition element
     * @return string Conditional HTML
     */
    private function processCondition($element) {
        $test = $element->getAttribute('test');
        
        if ($this->evaluateCondition($test)) {
            return $this->processNode($element);
        }
        
        return '';
    }
    
    /**
     * Process section element
     * 
     * @param \DOMElement $element Section element
     * @return string Section HTML
     */
    private function processSection($element) {
        $class = $element->getAttribute('class');
        $style = $element->getAttribute('style');
        
        $html = '<div';
        if ($class) $html .= ' class="' . htmlspecialchars($class) . '"';
        if ($style) $html .= ' style="' . htmlspecialchars($style) . '"';
        $html .= '>';
        $html .= $this->processNode($element);
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Process table element
     * 
     * @param \DOMElement $element Table element
     * @return string Table HTML
     */
    private function processTable($element) {
        $class = $element->getAttribute('class');
        $style = $element->getAttribute('style');
        
        $html = '<table';
        if ($class) $html .= ' class="' . htmlspecialchars($class) . '"';
        if ($style) $html .= ' style="' . htmlspecialchars($style) . '"';
        $html .= '>';
        
        // Process table children (thead, tbody, tr, etc.)
        foreach ($element->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $html .= $this->processTableChild($child);
            }
        }
        
        $html .= '</table>';
        
        return $html;
    }
    
    /**
     * Process table child elements (thead, tbody, tr, td, th)
     * 
     * @param \DOMElement $element Table child element
     * @return string HTML
     */
    private function processTableChild($element) {
        $tagName = $element->tagName;
        $allowedTags = ['thead', 'tbody', 'tfoot', 'tr', 'td', 'th'];
        
        if (!in_array($tagName, $allowedTags)) {
            return '';
        }
        
        $html = '<' . $tagName;
        
        // Copy attributes
        if ($element->hasAttributes()) {
            foreach ($element->attributes as $attr) {
                $html .= ' ' . $attr->name . '="' . htmlspecialchars($attr->value) . '"';
            }
        }
        
        $html .= '>';
        $html .= $this->processNode($element);
        $html .= '</' . $tagName . '>';
        
        return $html;
    }
    
    /**
     * Process text element
     * 
     * @param \DOMElement $element Text element
     * @return string Text HTML
     */
    private function processText($element) {
        $class = $element->getAttribute('class');
        $style = $element->getAttribute('style');
        
        $html = '<span';
        if ($class) $html .= ' class="' . htmlspecialchars($class) . '"';
        if ($style) $html .= ' style="' . htmlspecialchars($style) . '"';
        $html .= '>';
        $html .= $this->processNode($element);
        $html .= '</span>';
        
        return $html;
    }
    
    /**
     * Process image element
     * 
     * @param \DOMElement $element Image element
     * @return string Image HTML
     */
    private function processImage($element) {
        $src = $element->getAttribute('src');
        $alt = $element->getAttribute('alt');
        $width = $element->getAttribute('width');
        $height = $element->getAttribute('height');
        $style = $element->getAttribute('style');
        
        // Replace variables in src
        $src = $this->replaceVariables($src);
        
        $html = '<img src="' . htmlspecialchars($src) . '"';
        if ($alt) $html .= ' alt="' . htmlspecialchars($alt) . '"';
        if ($width) $html .= ' width="' . htmlspecialchars($width) . '"';
        if ($height) $html .= ' height="' . htmlspecialchars($height) . '"';
        if ($style) $html .= ' style="' . htmlspecialchars($style) . '"';
        $html .= ' />';
        
        return $html;
    }
    
    /**
     * Process standard HTML tag
     * 
     * @param \DOMElement $element HTML element
     * @return string HTML
     */
    private function processHtmlTag($element) {
        $tagName = $element->tagName;
        $allowedTags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span', 
                        'strong', 'em', 'u', 'br', 'hr', 'ul', 'ol', 'li'];
        
        if (!in_array($tagName, $allowedTags)) {
            return $this->processNode($element);
        }
        
        $html = '<' . $tagName;
        
        // Copy attributes
        if ($element->hasAttributes()) {
            foreach ($element->attributes as $attr) {
                $html .= ' ' . $attr->name . '="' . htmlspecialchars($attr->value) . '"';
            }
        }
        
        // Self-closing tags
        if (in_array($tagName, ['br', 'hr'])) {
            $html .= ' />';
        } else {
            $html .= '>';
            $html .= $this->processNode($element);
            $html .= '</' . $tagName . '>';
        }
        
        return $html;
    }
    
    /**
     * Replace variables in text
     * 
     * @param string $text Text with {{variable}} placeholders
     * @return string Text with replaced variables
     */
    private function replaceVariables($text) {
        return preg_replace_callback('/\{\{([^}]+)\}\}/', function($matches) {
            $varName = trim($matches[1]);
            return $this->getVariableValue($varName, '');
        }, $text);
    }
    
    /**
     * Get variable value from data
     * 
     * @param string $name Variable name (supports dot notation)
     * @param mixed $default Default value
     * @return mixed Variable value
     */
    private function getVariableValue($name, $default = '') {
        if (empty($name)) {
            return $default;
        }
        
        // Support dot notation (e.g., "association.name")
        $parts = explode('.', $name);
        $value = $this->data;
        
        foreach ($parts as $part) {
            if (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } elseif (is_object($value) && isset($value->$part)) {
                $value = $value->$part;
            } else {
                return $default;
            }
        }
        
        return $value;
    }
    
    /**
     * Format value based on format type
     * 
     * @param mixed $value Value to format
     * @param string $format Format type
     * @return string Formatted value
     */
    private function formatValue($value, $format) {
        switch ($format) {
            case 'date':
                $timestamp = strtotime($value);
                if ($timestamp === false) {
                    return $value; // Return original if invalid date
                }
                return date('d/m/Y', $timestamp);
            case 'datetime':
                $timestamp = strtotime($value);
                if ($timestamp === false) {
                    return $value; // Return original if invalid datetime
                }
                return date('d/m/Y H:i', $timestamp);
            case 'currency':
                if (!is_numeric($value)) {
                    return $value; // Return original if not numeric
                }
                return number_format((float)$value, 2, ',', '.') . ' €';
            case 'number':
                if (!is_numeric($value)) {
                    return $value; // Return original if not numeric
                }
                return number_format((float)$value, 0, ',', '.');
            case 'uppercase':
                return strtoupper($value);
            case 'lowercase':
                return strtolower($value);
            case 'capitalize':
                return ucwords(strtolower($value));
            default:
                return $value;
        }
    }
    
    /**
     * Evaluate condition expression
     * 
     * @param string $condition Condition expression
     * @return bool Evaluation result
     */
    private function evaluateCondition($condition) {
        // Simple condition evaluation
        // Format: "variable" or "variable == value" or "variable != value"
        
        if (strpos($condition, '==') !== false) {
            list($var, $expected) = array_map('trim', explode('==', $condition, 2));
            $value = $this->getVariableValue($var);
            return $value === trim($expected, '"\'');
        }
        
        if (strpos($condition, '!=') !== false) {
            list($var, $expected) = array_map('trim', explode('!=', $condition, 2));
            $value = $this->getVariableValue($var);
            return $value !== trim($expected, '"\'');
        }
        
        // Check if variable exists and is not empty
        $value = $this->getVariableValue($condition);
        return !empty($value);
    }
    
    /**
     * Validate XML template structure
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
        
        // Check for required elements
        if ($dom->documentElement && $dom->documentElement->tagName !== 'template') {
            $errors[] = 'Root element must be <template>';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
