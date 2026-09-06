<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

namespace Tests\Unit\Core\Util\String;

use Generator;
use PHPUnit\Framework\TestCase;
use PrestaShop\PrestaShop\Core\Util\String\StringModifier;

class StringModifierTest extends TestCase
{
    /**
     * @var StringModifier
     */
    private $stringModifier;

    public function setUp(): void
    {
        $this->stringModifier = new StringModifier();
    }

    /**
     * Square brackets survive the character filter and then collapse to a space, so one at the end of a
     * name became a trailing dash in the friendly URL, while round brackets - dropped outright by the
     * filter - did not. Brackets still separate words, they just no longer dangle.
     *
     * @dataProvider getStringsWithBrackets
     */
    public function testItDoesNotLeaveASeparatorDanglingInAUrl(string $string, string $expected): void
    {
        $this->assertSame($expected, $this->stringModifier->str2url($string, false));
    }

    public static function getStringsWithBrackets(): Generator
    {
        yield 'trailing square brackets' => ['Product name [text]', 'product-name-text'];
        yield 'leading square brackets' => ['[text] Product name', 'text-product-name'];
        yield 'brackets inside still separate words' => ['Red car[big]', 'red-car-big'];
        yield 'round brackets are dropped, as before' => ['Product name (text)', 'product-name-text'];
        yield 'trailing dash' => ['Product name -', 'product-name'];
        yield 'trailing colon' => ['Product name:', 'product-name'];
        yield 'no separators at the edges' => ['Product name text', 'product-name-text'];
        yield 'inner punctuation is unaffected' => ['Red car / big', 'red-car-big'];
    }

    public function testItTransformsCamelCaseToSplitWords(): void
    {
        $data = [
            [
                'string' => 'oneTwoThreeFour',
                'expects' => 'one Two Three Four',
            ],
            [
                'string' => 'StartsWithCap',
                'expects' => 'Starts With Cap',
            ],
            [
                'string' => 'hasConsecutiveCAPS',
                'expects' => 'has Consecutive CAPS',
            ],
            [
                'string' => 'NewMODULEDevelopment',
                'expects' => 'New MODULE Development',
            ],
            [
                'string' => 'snake_case_word',
                'expects' => 'snake_case_word',
            ],
        ];

        foreach ($data as $item) {
            $result = $this->stringModifier->splitByCamelCase($item['string']);

            $this->assertEquals($item['expects'], $result);
        }
    }

    /**
     * @dataProvider getTooLongStringsForEndCutting
     *
     * @param string $string
     * @param int $length
     * @param string $expectedOutput
     */
    public function testItCutsStringEndIfItIsTooLong(string $string, int $length, string $expectedOutput): void
    {
        $output = $this->stringModifier->cutEnd($string, $length);
        $this->assertEquals($expectedOutput, $output);
    }

    /**
     * @dataProvider getNotTooLongStringsForEndCutting
     *
     * @param string $string
     * @param int $length
     */
    public function testItDoesNotCutStringEndIfItsNotTooLong(string $string, int $length): void
    {
        $output = $this->stringModifier->cutEnd($string, $length);
        $this->assertEquals($string, $output);
    }

    /**
     * @return Generator
     */
    public function getTooLongStringsForEndCutting(): Generator
    {
        yield ['test', 3, 'tes'];
        yield ['testable', 7, 'testabl'];
        yield ['hello world 899', 13, 'hello world 8'];
    }

    /**
     * @return Generator
     */
    public function getNotTooLongStringsForEndCutting(): Generator
    {
        yield ['test', 4];
        yield ['testable', 20];
        yield ['good bye cruel world 10.99', 128];
    }

    /**
     * @dataProvider htmlToTextProvider
     */
    public function testHtmlToText(string $input, string $expected): void
    {
        self::assertSame($expected, $this->stringModifier->htmlToText($input));
    }

    public function htmlToTextProvider(): Generator
    {
        yield 'plain text' => [
            'Plain text',
            'Plain text',
        ];

        yield 'paragraphs' => [
            '<p>First sentence.</p><p>Second sentence.</p>',
            'First sentence. Second sentence.',
        ];

        yield 'unordered list' => [
            '<ul><li>Stainless steel</li><li>Silent run</li><li>5 year warranty</li></ul>',
            'Stainless steel, Silent run, 5 year warranty.',
        ];

        yield 'ordered list with surrounding content' => [
            '<p>Features:</p><ol><li>Fast</li><li>Reliable</li></ol><p>Available now.</p>',
            'Features: Fast, Reliable. Available now.',
        ];

        yield 'line break and entities' => [
            'One&nbsp;two<br>Three &amp; four',
            'One two Three & four',
        ];

        yield 'empty list items' => [
            '<ul><li>First</li><li></li><li>Second</li></ul>',
            'First, Second.',
        ];

        yield 'list items with punctuation' => [
            '<ul><li>First sentence.</li><li>Second sentence.</li></ul>',
            'First sentence. Second sentence.',
        ];
    }

    /**
     * @dataProvider str2UrlProvider
     */
    public function testStr2url(string $input, string $expected, bool $allow_accented_chars): void
    {
        self::assertSame($expected, $this->stringModifier->str2url($input, $allow_accented_chars));
    }

    public function str2UrlProvider(): Generator
    {
        yield ['!@#$%^&*()_+-={}[]|:;"<>,.?/', '-', false];
        yield ['Some !@#$%^&*()_+-={}[]|:;"<>,.?/ text', 'some-text', false];
        yield ['Some text 123 !@#$%^&*()_+-={}[]|:;"<>,.?/', 'some-text-123', false];
        yield ['Some text 123 with unicode characters: áéíóú', 'some-text-123-with-unicode-characters-aeiou', false];
        yield ['!@#$%^&*()_+-={}[]|:;"<>,.?/', '-', false];
        yield ['Some !@#$%^&*()_+-={}[]|:;"<>,.?/ text', 'some-text', false];
        yield ['Some text 123 !@#$%^&*()_+-={}[]|:;"<>,.?/', 'some-text-123', false];
        yield ['Some text 123 with unicode characters: áéíóú', 'some-text-123-with-unicode-characters-aeiou', false];
        yield ['Some text 123 with unicode characters: áéíóú', 'some-text-123-with-unicode-characters-áéíóú', true];
    }

    /**
     * @dataProvider getTestReplaceAccentedCharactersData
     */
    public function testReplaceAccentedCharacters(string $input, string $expected): void
    {
        self::assertSame($expected, $this->stringModifier->replaceAccentedChars($input));
    }

    public function getTestReplaceAccentedCharactersData(): Generator
    {
        yield 'empty string' => ['', ''];
        yield 'Test a variations' => ['aaâæaa', 'aaaaeaa'];
        yield 'Test e variations' => ['éèê', 'eee'];
    }

    /**
     * The back office builds the friendly URL with the JavaScript twin of this method, str2url() in
     * js/admin.js, while the merchant types. These are the characters where the move to the ICU
     * transliterator in 8.0 silently stopped agreeing with it, so one product name produced two
     * different URLs depending on whether it was saved from the back office or through the webservice,
     * an import or a CQRS command.
     *
     * @dataProvider getStringsThatMustMatchTheJavaScriptTwin
     */
    public function testStr2urlAgreesWithItsJavaScriptTwin(string $input, string $expected): void
    {
        self::assertSame($expected, $this->stringModifier->str2url($input, false));
    }

    public function getStringsThatMustMatchTheJavaScriptTwin(): Generator
    {
        yield 'zhe' => ['жук', 'zhuk'];
        yield 'ha' => ['хлеб', 'khleb'];
        yield 'che' => ['Чай', 'chaj'];
        yield 'sha' => ['шар', 'shar'];
        yield 'shcha' => ['щит', 'sshit'];
        yield 'yu' => ['юг', 'yug'];
        yield 'ya' => ['яма', 'yama'];
        yield 'io' => ['Ёлка', 'yolka'];
        yield 'ukrainian ie' => ['єдність', 'yednist'];
        yield 'yi' => ['їжак', 'yizhak'];

        // A symbol must not survive as a letter: ICU writes the registered sign as "(R)" and the
        // numero sign as "No", and the character filter then kept those letters.
        yield 'registered sign' => ['Copyright ®', 'copyright'];
        yield 'copyright sign' => ['© Acme', 'acme'];
        yield 'numero sign' => ['№ 5', '5'];

        // Characters that already agreed must keep agreeing.
        yield 'ligature is unchanged' => ['Œuvre', 'oeuvre'];
        yield 'accents are unchanged' => ['naïve café', 'naive-cafe'];
        yield 'plain cyrillic is unchanged' => ['Крем', 'krem'];
    }
}
