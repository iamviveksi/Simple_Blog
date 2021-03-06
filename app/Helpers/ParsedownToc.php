<?php

namespace App\Helpers;
use Exception;

class ParsedownToc extends \Parsedown
{
    const VERSION = '1.1';

    public function __construct()
    {
        if (version_compare(parent::version, '0.7.1') < 0) {
            throw new Exception('Parsedown-toc requires a later version of Parsedown');
        }

        $this->BlockTypes['['][] = 'Toc';
    }

    private $fullDocument;

    protected function textElements($text)
    {
        // make sure no definitions are set
        $this->DefinitionData = array();

        // standardize line breaks
        $text = str_replace(array("\r\n", "\r"), "\n", $text);

        // remove surrounding line breaks
        $text = trim($text, "\n");

        // Save a copy of the document
        $this->fullDocument = $text;

        // split text into lines
        $lines = explode("\n", $text);
        // iterate through lines to identify blocks
        return $this->linesElements($lines);
    }

    //
    // Header
    // -------------------------------------------------------------------------

    protected function blockHeader($Line)
    {
        $Block = parent::blockHeader($Line);

        if (preg_match('/[ #]*{('.$this->regexAttribute.'+)}[ ]*$/', $Block['element']['text'], $matches, PREG_OFFSET_CAPTURE)) {
            $attributeString = $matches[1][0];
            $Block['element']['attributes'] = $this->parseAttributeData($attributeString);
            $Block['element']['text'] = substr($Block['element']['text'], 0, $matches[0][1]);
        }

        // createAnchorID
        if (!isset($Block['element']['attributes']['id']) && isset($Block['element']['text'])) {
            $Block['element']['attributes']['id'] = $this->createAnchorID($Block['element']['text'], ['transliterate' => true]);
        }

        $link = "#".$Block['element']['attributes']['id'];

        $Block['element']['text'] = $Block['element']['text']."<a class='heading-link' href='{$link}'> <i class='fas fa-link'></i></a>";

        // ~

        return $Block;
    }

    //
    // Setext
    protected function blockSetextHeader($Line, array $Block = null)
    {
        $Block = parent::blockSetextHeader($Line, $Block);
        if (preg_match('/[ ]*{('.$this->regexAttribute.'+)}[ ]*$/', $Block['element']['text'], $matches, PREG_OFFSET_CAPTURE)) {
            $attributeString = $matches[1][0];
            $Block['element']['attributes'] = $this->parseAttributeData($attributeString);
            $Block['element']['text'] = substr($Block['element']['text'], 0, $matches[0][1]);
        }

        // createAnchorID
        if (!isset($Block['element']['attributes']['id']) && isset($Block['element']['text'])) {
            $Block['element']['attributes']['id'] = $this->createAnchorID($Block['element']['text'], ['transliterate' => true]);
        }

        if ($Block['type'] == 'Paragraph') {
            $link = "#".$Block['element']['attributes']['id'];
            $Block['element']['text'] = $Block['element']['text']."<a class='heading-link' href='{$link}'> <i class='fas fa-link'></i></a>";
        }


        return $Block;
    }


    //
    // Toc
    // -------------------------------------------------------------------------
    private $tocSettings;

    public function toc($input)
    {
        $Line['text'] = '[toc]';
        $Line['toc']['type'] = 'string';

        if (is_array($input)) {
            // selectors
            if (isset($input['selector'])) {
                if(!is_array($input['selector'])) {
                    throw new Exception("Selector must be a array");
                }
                $this->tocSettings['selectors'] = $input['selector'];
            }

            // Inline
            if (isset($input['inline'])) {
                if(!is_bool($input['inline'])) {
                    throw new Exception("Inline must be a boolean");
                }
                $this->tocSettings['inline'] = $input['inline'];
            }

            // Scope
            if (isset($input['scope'])) {
                if(!is_string($input['scope'])) {
                    throw new Exception("Scope must be a string");
                }
                $this->fullDocument = $input['scope'];
            }

        } elseif (is_string($input)) {
            $this->fullDocument = $input;
        } else {
            throw new Exception("Unexpected parameter type");
        }

        return $this->blockToc($Line, null, false);
    }

    // ~

    protected $contentsListString;
    protected $contentsListArray = array();
    protected $firstHeadLevel = 0;

    // ~

    protected function blockToc(array $Line, array $Block = null, $isInline = true)
    {
        if ($Line['text'] == '[toc]') {
            if(isset($this->tocSettings['inline']) && $this->tocSettings['inline'] == false && $isInline == true) {
                return;
            }

            $selectorList = $this->tocSettings['selectors'] ? $this->tocSettings['selectors'] : ['h1','h2','h3','h4','h5','h6'];

            // Check if $Line[toc][type] already is defined
            if (!isset($Line['toc']['type'])) {
                $Line['toc']['type'] = 'array';
            }

            foreach ($selectorList as $selector) {
                $selectors[] = (integer) trim($selector, 'h');
            }

            $cleanDoc = preg_replace('/<!--(.|\s)*?-->/', '', $this->fullDocument);
            $headerLines = array();
            $prevLine = '';

            // split text into lines
            $lines = explode("\n", $cleanDoc);

            foreach ($lines as $headerLine) {
                if (strspn($headerLine, '#') > 0 || strspn($headerLine, '=') >= 3 || strspn($headerLine, '-') >= 3) {
                    $level = strspn($headerLine, '#');

                    // Setext headers
                    if (strspn($headerLine, '=') >= 3 && $prevLine !== '') {
                        $level = 1;
                        $headerLine = $prevLine;
                    } elseif (strspn($headerLine, '-') >= 3 && $prevLine !== '') {
                        $level = 2;
                        $headerLine = $prevLine;
                    }

                    if (in_array($level, $selectors) && $level > 0 && $level <= 6) {
                        $text = preg_replace('/[ #]*{('.$this->regexAttribute.'+)}[ ]*$/', '', $headerLine);
                        $text = trim(trim($text, '#'));

                        // createAnchorID
                        $id = $this->createAnchorID($text, ['transliterate' => true]);

                        if (preg_match('/{('.$this->regexAttribute.'+)}$/', $headerLine, $matches)) {
                            if (strspn($matches[1], '#') > 0) {
                                $id = trim($matches[1], '#');
                            }
                        }

                        // ~

                        if ($this->firstHeadLevel === 0) {
                            $this->firstHeadLevel = $level;
                        }

                        $cutIndent = $this->firstHeadLevel - 1;

                        if ($cutIndent > $level) {
                            $level = 1;
                        } else {
                            $level = $level - $cutIndent;
                        }

                        $indent = str_repeat('  ', $level);

                        // ~

                        if ($Line['toc']['type'] == 'string') {
                            $this->contentsListString .= "$indent- [${text}](#${id})\n";
                        } else {
                            $this->contentsListArray[] = "$indent- [${text}](#${id})\n";
                        }
                    }
                }
                $prevLine = $headerLine;
            }

            if ($Line['toc']['type'] == 'string') {
                return $this->text($this->contentsListString);
            }

            // ~

            $Block = array(

                'element' => array(
                    'name' => 'nav',
                    'attributes' => array(
                        'id'   => 'table-of-contents',
                    ),
                    'elements' => array(
                        '1' => array(
                            "handler" => array(
                                "function" => "li",
                                "argument" => $this->contentsListArray,
                                "destination" => "elements",
                            ),
                        ),
                    ),
                ),
            );

            // ~

            return $Block;
        }
    }


    private function createAnchorID(string $str, $options = array()) : string
    {
        // Make sure string is in UTF-8 and strip invalid UTF-8 characters
        $str = mb_convert_encoding((string)$str, 'UTF-8', mb_list_encodings());

        $defaults = array(
            'delimiter' => '-',
            'limit' => null,
            'lowercase' => true,
            'replacements' => array(),
            'transliterate' => false,
        );

        // Merge options
        $options = array_merge($defaults, $options);

        $char_map = array(
            // Latin
            '??' => 'A', '??' => 'A', '??' => 'A', '??' => 'A', '??' => 'A', '??' => 'Aa', '??' => 'AE', '??' => 'C',
            '??' => 'E', '??' => 'E', '??' => 'E', '??' => 'E', '??' => 'I', '??' => 'I', '??' => 'I', '??' => 'I',
            '??' => 'D', '??' => 'N', '??' => 'O', '??' => 'O', '??' => 'O', '??' => 'O', '??' => 'O', '??' => 'O',
            '??' => 'Oe', '??' => 'U', '??' => 'U', '??' => 'U', '??' => 'U', '??' => 'U', '??' => 'Y', '??' => 'TH',
            '??' => 'ss', '??' => 'OE',
            '??' => 'a', '??' => 'a', '??' => 'a', '??' => 'a', '??' => 'a', '??' => 'aa', '??' => 'ae', '??' => 'c',
            '??' => 'e', '??' => 'e', '??' => 'e', '??' => 'e', '??' => 'i', '??' => 'i', '??' => 'i', '??' => 'i',
            '??' => 'd', '??' => 'n', '??' => 'o', '??' => 'o', '??' => 'o', '??' => 'o', '??' => 'o', '??' => 'o',
            '??' => 'oe', '??' => 'u', '??' => 'u', '??' => 'u', '??' => 'u', '??' => 'u', '??' => 'y', '??' => 'th',
            '??' => 'y', '??' => 'oe',
            // Latin symbols
            '??' => '(c)','??' => '(r)','???' => '(tm)',
            // Greek
            '??' => 'A', '??' => 'B', '??' => 'G', '??' => 'D', '??' => 'E', '??' => 'Z', '??' => 'H', '??' => '8',
            '??' => 'I', '??' => 'K', '??' => 'L', '??' => 'M', '??' => 'N', '??' => '3', '??' => 'O', '??' => 'P',
            '??' => 'R', '??' => 'S', '??' => 'T', '??' => 'Y', '??' => 'F', '??' => 'X', '??' => 'PS', '??' => 'W',
            '??' => 'A', '??' => 'E', '??' => 'I', '??' => 'O', '??' => 'Y', '??' => 'H', '??' => 'W', '??' => 'I',
            '??' => 'Y',
            '??' => 'a', '??' => 'b', '??' => 'g', '??' => 'd', '??' => 'e', '??' => 'z', '??' => 'h', '??' => '8',
            '??' => 'i', '??' => 'k', '??' => 'l', '??' => 'm', '??' => 'n', '??' => '3', '??' => 'o', '??' => 'p',
            '??' => 'r', '??' => 's', '??' => 't', '??' => 'y', '??' => 'f', '??' => 'x', '??' => 'ps', '??' => 'w',
            '??' => 'a', '??' => 'e', '??' => 'i', '??' => 'o', '??' => 'y', '??' => 'h', '??' => 'w', '??' => 's',
            '??' => 'i', '??' => 'y', '??' => 'y', '??' => 'i',
            // Turkish
            '??' => 'S', '??' => 'I', '??' => 'C', '??' => 'U', '??' => 'O', '??' => 'G',
            '??' => 's', '??' => 'i', '??' => 'c', '??' => 'u', '??' => 'o', '??' => 'g',
            // Russian
            '??' => 'A', '??' => 'B', '??' => 'V', '??' => 'G', '??' => 'D', '??' => 'E', '??' => 'Yo', '??' => 'Zh',
            '??' => 'Z', '??' => 'I', '??' => 'J', '??' => 'K', '??' => 'L', '??' => 'M', '??' => 'N', '??' => 'O',
            '??' => 'P', '??' => 'R', '??' => 'S', '??' => 'T', '??' => 'U', '??' => 'F', '??' => 'H', '??' => 'C',
            '??' => 'Ch', '??' => 'Sh', '??' => 'Sh', '??' => '', '??' => 'Y', '??' => '', '??' => 'E', '??' => 'Yu',
            '??' => 'Ya',
            '??' => 'a', '??' => 'b', '??' => 'v', '??' => 'g', '??' => 'd', '??' => 'e', '??' => 'yo', '??' => 'zh',
            '??' => 'z', '??' => 'i', '??' => 'j', '??' => 'k', '??' => 'l', '??' => 'm', '??' => 'n', '??' => 'o',
            '??' => 'p', '??' => 'r', '??' => 's', '??' => 't', '??' => 'u', '??' => 'f', '??' => 'h', '??' => 'c',
            '??' => 'ch', '??' => 'sh', '??' => 'sh', '??' => '', '??' => 'y', '??' => '', '??' => 'e', '??' => 'yu',
            '??' => 'ya',
            // Ukrainian
            '??' => 'Ye', '??' => 'I', '??' => 'Yi', '??' => 'G',
            '??' => 'ye', '??' => 'i', '??' => 'yi', '??' => 'g',
            // Czech
            '??' => 'C', '??' => 'D', '??' => 'E', '??' => 'N', '??' => 'R', '??' => 'S', '??' => 'T', '??' => 'U',
            '??' => 'Z',
            '??' => 'c', '??' => 'd', '??' => 'e', '??' => 'n', '??' => 'r', '??' => 's', '??' => 't', '??' => 'u',
            '??' => 'z',
            // Polish
            '??' => 'A', '??' => 'C', '??' => 'e', '??' => 'L', '??' => 'N', '??' => 'o', '??' => 'S', '??' => 'Z',
            '??' => 'Z',
            '??' => 'a', '??' => 'c', '??' => 'e', '??' => 'l', '??' => 'n', '??' => 'o', '??' => 's', '??' => 'z',
            '??' => 'z',
            // Latvian
            '??' => 'A', '??' => 'C', '??' => 'E', '??' => 'G', '??' => 'i', '??' => 'k', '??' => 'L', '??' => 'N',
            '??' => 'S', '??' => 'u', '??' => 'Z',
            '??' => 'a', '??' => 'c', '??' => 'e', '??' => 'g', '??' => 'i', '??' => 'k', '??' => 'l', '??' => 'n',
            '??' => 's', '??' => 'u', '??' => 'z'
        );

        // Make custom replacements
        $str = preg_replace(array_keys($options['replacements']), $options['replacements'], $str);

        // Transliterate characters to ASCII
        if ($options['transliterate']) {
            $str = str_replace(array_keys($char_map), $char_map, $str);
        }

        // Replace non-alphanumeric characters with our delimiter
        $str = preg_replace('/[^\p{L}\p{Nd}]+/u', $options['delimiter'], $str);

        // Remove duplicate delimiters
        $str = preg_replace('/(' . preg_quote($options['delimiter'], '/') . '){2,}/', '$1', $str);

        // Truncate slug to max. characters
        $str = mb_substr($str, 0, ($options['limit'] ? $options['limit'] : mb_strlen($str, 'UTF-8')), 'UTF-8');

        // Remove delimiter from ends
        $str = trim($str, $options['delimiter']);


        return $options['lowercase'] ? mb_strtolower($str, 'UTF-8') : $str;
    }

    protected function parseAttributeData($attributeString)
    {
        $Data = array();

        $attributes = preg_split('/[ ]+/', $attributeString, - 1, PREG_SPLIT_NO_EMPTY);

        foreach ($attributes as $attribute) {
            if ($attribute[0] === '#') {
                $Data['id'] = substr($attribute, 1);
            } else { // "."
                $classes []= substr($attribute, 1);
            }
        }

        if (isset($classes)) {
            $Data['class'] = implode(' ', $classes);
        }

        return $Data;
    }

    protected $regexAttribute = '(?:[#.][-\w]+[ ]*)';
}
