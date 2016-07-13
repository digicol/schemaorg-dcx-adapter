<?php
/**
 * Created by PhpStorm.
 * User: tim
 * Date: 13.07.16
 * Time: 10:14
 */

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
        static $add_no_space_for = array
        (
            'a', 'b', 'del', 'em', 'i', 'ins', 'mark', 'span', 'strike', 'strong',
            'sub', 'sup', 'u'
        );

        // <span class="x"> => span

        $parts = explode(' ', trim(strtr($matches[ 1 ], array( '<' => '', '>' => '', '/' => '' ))));

        $tag = $parts[ 0 ];

        $result = $matches[ 0 ];

        if (! in_array($tag, $add_no_space_for))
        {
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
        if (trim($html) === '')
        {
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
}
