<?php

declare(strict_types=1);

namespace Northrook\Contracts {
    /** Space */
    const ENCODED_SPACE = '&#32;';
    /** Horizontal Tab `\t` */
    const ENCODED_TAB = '&#9;';
    /** Line Feed `\n` */
    const ENCODED_LF = '&#10;';
    /** Carriage Return `\r` */
    const ENCODED_CR = '&#13;';
    /** Double quote `"` */
    const ENCODED_QUOTE = '&#34;';
    /** Single quote `'` */
    const ENCODED_APOSTROPHE = '&#39;';
    /** Backtick `` ` `` */
    const ENCODED_BACKTICK = '&#96;';
    /** Double quote `#` */
    const ENCODED_HASHTAG = '&#35;';
    /** Dollar `$` */
    const ENCODED_DOLLAR = '&#36;';
    /** Bang|Exclamation `!` */
    const ENCODED_BANG = '&#33;';
    /** Ampersand `&` */
    const ENCODED_AMP = '&#38;';
    /** Equals `=` */
    const ENCODED_EQUALS = '&#61;';
    /** Less-than `<` */
    const ENCODED_LT = '&#60;';
    /** Greater-than `>` */
    const ENCODED_GT = '&#62;';
    /** Slash `/` */
    const ENCODED_SLASH = '&#47;';
    /** Backslash `\` */
    const ENCODED_BACKSLASH = '&#92;';

    // @formatter:off
    const TAG_STRUCTURE    = [
        'html',
        'head',
        'body',
        'title',
        'style',
        'script',
        'link',
        'noscript',
        'template',
        'iframe',
    ];
    const TAG_CONTENT      = [
        'header',
        'footer',
        'aside',
        'main',
        'section',
        'article',
        'div',
        'p',
        'address',
        'blockquote',
        'details',
        'dialog',
        'dl',
        'hr',
        // : Media
        'svg',
        'canvas',
        'object',
        'source',
        'video',
        'audio',
        'embed',
        'picture',
        'figcaption',
        'figure',
        'caption',
        'pre',
        // : List
        'ol',
        'ul',
        'li',
        'nav',
        'dropdown',
        'menu',
        'modal',
        'tooltip',
        // : Form
        'form',
        'field',
        'fieldset',
        'optgroup',
        'input',
        'label',
        'legend',
        'textarea',
        'select',
        'option',
        'datalist',
        'button',
        'progress',
        'meter',
        'output',
        // : Table
        'table',
        'thead',
        'tbody',
        'tfoot',
        'td',
        'th',
        'tr',
        'col',
        'colgroup',
    ];
    const TAG_HEADING      = [
        'hgroup',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
    ];
    const TAG_INLINE       = [
        'a',
        'b',
        'i',
        's',
        'em',
        'u',
        'small',
        'strong',
        'span',
        'mark',
        'code',
        'kbd',
        'var',
        'samp',
        'cite',
        'q',
        'abbr',
        'dfn',
        'time',
        'data',
        'sub',
        'sup',
        'bdi',
        'bdo',
        'wbr',
        'br',
    ];
    const TAG_SELF_CLOSING = [
        'meta',
        'link',
        'img',
        'input',
        'wbr',
        'hr',
        'br',
        'col',
        'area',
        'base',
        'source',
        'embed',
        'track',
    ];

    // @formatter:on
}
