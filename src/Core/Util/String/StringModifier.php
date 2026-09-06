<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

namespace PrestaShop\PrestaShop\Core\Util\String;

use Transliterator;

/**
 * This class defines reusable methods for strings modifications.
 */
final class StringModifier implements StringModifierInterface
{
    /**
     * Transliterations PrestaShop has always written with two letters, where ICU writes one.
     *
     * WHY: str2url() has a JavaScript twin in js/admin.js that the back office uses to build the
     * friendly URL while the merchant types. Until 8.0 the PHP side used a hand written table that
     * agreed with it on these characters; moving to the ICU transliterator silently reduced them to a
     * single letter, so the same product name yielded a different URL depending on whether it was
     * saved from the back office or through the webservice, an import or a CQRS command.
     */
    private const TRANSLITERATION_OVERRIDES = [
        'ж' => 'zh', 'Ж' => 'Zh',
        'х' => 'kh', 'Х' => 'Kh',
        'ч' => 'ch', 'Ч' => 'Ch',
        'ш' => 'sh', 'Ш' => 'Sh',
        'щ' => 'ssh', 'Щ' => 'Ssh',
        'ю' => 'yu', 'Ю' => 'Yu',
        'я' => 'ya', 'Я' => 'Ya',
        'ё' => 'yo', 'Ё' => 'Yo',
        'є' => 'ye', 'Є' => 'Ye',
        'ї' => 'yi', 'Ї' => 'Yi',
    ];
    /**
     * @var Transliterator|null
     */
    private $transliterator;

    /**
     * {@inheritdoc}
     */
    public function splitByCamelCase($string)
    {
        $regex = '/(?)(?<=[a-z])(?=[A-Z]) | (?<=[A-Z])(?=[A-Z][a-z])/x';

        $splitString = preg_split($regex, $string);

        return implode(' ', $splitString);
    }

    /**
     * {@inheritdoc}
     */
    public function cutEnd(string $string, int $expectedLength): string
    {
        if (mb_strlen($string, 'UTF-8') > $expectedLength) {
            return mb_substr($string, 0, $expectedLength, 'UTF-8');
        }

        return $string;
    }

    /**
     * {@inheritdoc}
     */
    public function htmlToText(string $html): string
    {
        // Replace explicit line breaks with spaces so words from adjacent lines stay separated.
        $html = preg_replace('/<br\s*\/?>/i', ' ', $html);

        // Add commas after list items before stripping tags so list values stay separated.
        $html = preg_replace('/<\/li\s*>/i', ', ', $html);

        // Add a sentence boundary after lists before stripping tags.
        $html = preg_replace('/<\/(?:ul|ol)\s*>/i', '. ', $html);

        // Replace common block endings with spaces so paragraphs and headings stay separated.
        $html = preg_replace('/<\/(?:p|div|section|article|header|footer|aside|nav|blockquote|h[1-6]|tr|td|th)\s*>/i', ' ', $html);

        // Remove remaining HTML tags after separators have been inserted.
        $text = strip_tags($html);

        // Decode HTML entities so the returned text contains readable characters.
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize all whitespace first so punctuation cleanup can use predictable spacing.
        $text = preg_replace('/\s+/u', ' ', $text);

        // Remove spaces before punctuation introduced by stripped tags.
        $text = preg_replace('/\s+([,.!?;:])/', '$1', $text);

        // Remove commas before sentence punctuation, for example after the last list item.
        $text = preg_replace('/,\s*([.!?])/', '$1', $text);

        // Remove list commas after items that already ended with sentence punctuation.
        $text = preg_replace('/([.!?])\s*,/', '$1', $text);

        // Collapse repeated commas created by empty list items.
        $text = preg_replace('/(?:\s*,\s*){2,}/', ', ', $text);

        // Collapse repeated sentence dots created by list endings next to existing punctuation.
        $text = preg_replace('/(?:\.\s*){2,}/', '. ', $text);

        // Normalize whitespace again after punctuation cleanup.
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    /**
     * Return a friendly url made from the provided string
     * If the mbstring library is available, the output is the same as the js function of the same name.
     *
     * @param string $string
     *
     * @return string
     */
    public function str2url(string $string, bool $allow_accented_chars): string
    {
        $return_str = trim($string);
        $return_str = mb_strtolower($return_str, 'UTF-8');

        if ($allow_accented_chars) {
            $return_str = preg_replace('/[^a-zA-Z0-9\s\'\:\/\[\]\-\p{L}]/u', '', $return_str);
        } else {
            // WHY: a symbol must not survive as a letter. ICU renders the registered sign as "(R)" and
            // the numero sign as "No", and the whitelist below then keeps those letters, so
            // "Copyright (R)" ended up as "copyright-r". Removing symbols first matches the accented
            // branch above, which never keeps them, and the JavaScript twin.
            $return_str = preg_replace('/\p{S}/u', '', $return_str);
            $return_str = $this->replaceAccentedChars($return_str);
            $return_str = preg_replace('/[^a-zA-Z0-9\s\'\:\/\[\]\-]/', '', $return_str);
        }

        $return_str = preg_replace('/[\s\'\:\/\[\]\-]+/', ' ', $return_str);

        // A separator at either end collapses to a space, which would otherwise become a leading or
        // trailing dash. Square brackets are the visible case: they survive the filtering above, while
        // round ones are dropped outright, which is why only they left a dangling dash.
        $trimmed = trim($return_str);
        if ($trimmed !== '') {
            // Kept as it was when the name is nothing but separators, since an empty result would not
            // pass Validate::isLinkRewrite() while the single dash does.
            $return_str = $trimmed;
        }

        return str_replace([' ', '/'], '-', $return_str);
    }

    /**
     * Replace all accented chars by their equivalent non-accented chars.
     *
     * @param string $string
     *
     * @return string
     */
    public function replaceAccentedChars(string $string): string
    {
        if (null === $this->transliterator) {
            $this->transliterator = Transliterator::create('Any-Latin; Latin-ASCII');
        }

        return $this->transliterator->transliterate(strtr($string, self::TRANSLITERATION_OVERRIDES));
    }
}
