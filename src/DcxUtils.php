<?php

namespace Digicol\SchemaOrg\Dcx;


class DcxUtils
{
    /**
     * A copy of DCX_HtmlUtils::stripTagsReplaceCallback()
     *
     * @param $matches
     * @return string
     */
    public static function stripTagsReplaceCallback($matches)
    {
        static $addNoSpaceFor = [
            'a',
            'b',
            'del',
            'em',
            'i',
            'ins',
            'mark',
            'span',
            'strike',
            'strong',
            'sub',
            'sup',
            'u'
        ];

        // <span class="x"> => span

        $parts = explode(' ', trim(strtr($matches[1], ['<' => '', '>' => '', '/' => ''])));

        $tag = $parts[0];

        $result = $matches[0];

        if (! in_array($tag, $addNoSpaceFor)) {
            $result .= ' ';
        }

        return $result;
    }


    /**
     * A copy of DCX_HtmlUtils::toStructuredPlainText()
     *
     * @param string $html
     * @return string
     */
    public static function toPlainText($html)
    {
        if (trim($html) === '') {
            return '';
        }

        $html = str_replace('&apos;', "'", $html);

        $regex = '/(<.*?>)/';
        $html = preg_replace_callback($regex, 'DCX_HtmlUtils::stripTagsReplaceCallback', $html);

        $html = trim(preg_replace('/[[:space:]]+/s', ' ', strip_tags($html, '<p><br><div>')));

        // add \n\n after each paragraph
        $html = preg_replace('@</p>@s', "</p>\n\n", $html);

        // add \n after each br
        $html = preg_replace('@<br[/ ]*>@s', "\n", $html);

        // add \n after each div
        $html = preg_replace('@</div>@s', "</div>\n", $html);

        // remove multiple whitespaces
        $html = trim(preg_replace('@([\n ])[ ]+@', "\\1", strip_tags($html)));

        return html_entity_decode($html, ENT_QUOTES, 'UTF-8');
    }


    public static function isValidIso8601($iso8601str)
    {
        // The string should parse into a valid date... 

        try {
            new \DateTime($iso8601str);
        } catch (\Exception $e) {
            return false;
        }

        // ... and match the expected format

        return preg_match
        (
            '/^'
            . '[0-9]{4}-[0-9]{2}-[0-9]{2}' // 2016-09-23
            . 'T[0-9]{2}:[0-9]{2}:[0-9]{2}' // T17:38:02
            . '(\\.[0-9]+)?' // .4312
            . '(Z|(\\+|-)[0-9]{2}:[0-9]{2})' // Z, or +01:00
            . '$/',
            $iso8601str
        );
    }
}
