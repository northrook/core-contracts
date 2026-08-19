<?php

declare(strict_types=1);

//region Overridable

defined('CHARSET') || define('CHARSET', 'UTF-8');
defined('DIR_SEP') || define('DIR_SEP', '/');
defined('SLASH') || define('SLASH', DIR_SEP);

if (defined('__PHPSTAN_RUNNING__')) {
    return;
}

//endregion

const MAX_PATH_LENGTH        = 4_094;
const ARRAY_FILTER_USE_VALUE = 0;

/** Empty string */
const EMPTY_STRING = '';
/** Space */
const WHITESPACE = ' ';
/** Newline */
const NEWLINE = "\n";
/** Tab */
const TAB = "\t";
/** Line Feed */
const LF = "\n";
/** Carriage Return */
const CR = "\r";

/** Carriage Return and Line Feed */
defined('__PHPSTAN_RUNNING__') && defined('CRLF') || define('CRLF', "\r\n");

const CROCKFORD_BASE32 = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

const CHARSET_ALPHA  = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
const CHARSET_ALNUM  = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
const CHARSET_DIGIT  = '0123456789';
const CHARSET_XDIGIT = '0123456789abcdefABCDEF';
const CHARSET_ASCII  = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F !\"#$%&'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_`abcdefghijklmnopqrstuvwxyz{|}~\x7F";

/** RFC 3986 `scheme` body after the leading ALPHA */
const CHARSET_URI_SCHEME = CHARSET_ALNUM . '+-.';

const CHARSET_NAMESPACE = CHARSET_ALPHA . CHARSET_DIGIT . '_\\';
