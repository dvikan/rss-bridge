<?php

final class HttpException extends \Exception
{
}

final class Json
{
    public static function encode($value): string
    {
        $flags = JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        return \json_encode($value, $flags);
    }

    public static function decode(string $json, bool $assoc = true)
    {
        return \json_decode($json, $assoc, 512, JSON_THROW_ON_ERROR);
    }
}

/**
 * Get the home page url of rss-bridge e.g. 'https://example.com/' or 'https://example.com/bridge/'
 */
function get_home_page_url(): string
{
    $https = $_SERVER['HTTPS'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (($pos = strpos($uri, '?')) !== false) {
        $uri = substr($uri, 0, $pos);
    }
    $scheme = $https === 'on' ? 'https' : 'http';
    return "$scheme://$host$uri";
}

/**
 * Get the full current url e.g. 'http://example.com/?action=display&bridge=FooBridge'
 */
function get_current_url(): string
{
    $https = $_SERVER['HTTPS'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $scheme = $https === 'on' ? 'https' : 'http';
    return "$scheme://$host$uri";
}

function create_sane_exception_message(\Throwable $e): string
{
    return sprintf(
        'Exception %s: %s at %s line %s',
        get_class($e),
        $e->getMessage(),
        trim_path_prefix($e->getFile()),
        $e->getLine()
    );
}

function create_sane_stacktrace(\Throwable $e): array
{
    $frames = array_reverse($e->getTrace());
    $frames[] = [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ];
    $trace = [];
    foreach ($frames as $i => $frame) {
        $file = $frame['file'] ?? '(no file)';
        $line = $frame['line'] ?? '(no line)';
        $trace[] = sprintf(
            '#%s %s:%s',
            $i,
            trim_path_prefix($file),
            $line,
        );
    }
    return $trace;
}

/**
 * Trim path prefix for privacy/security reasons
 *
 * Example: "/var/www/rss-bridge/index.php" => "index.php"
 */
function trim_path_prefix(string $filePath): string
{
    return mb_substr($filePath, mb_strlen(dirname(__DIR__)) + 1);
}

/**
 * This is buggy because strip tags removes a lot that isn't html
 */
function is_html(string $text): bool
{
    return strlen(strip_tags($text)) !== strlen($text);
}

/**
 * Determines the MIME type from a URL/Path file extension.
 *
 * _Remarks_:
 *
 * * The built-in functions `mime_content_type` and `fileinfo` require fetching
 * remote contents.
 * * A caller can hint for a MIME type by appending `#.ext` to the URL (i.e. `#.image`).
 *
 * Based on https://stackoverflow.com/a/1147952
 *
 * @param string $url The URL or path to the file.
 * @return string The MIME type of the file.
 */
function parse_mime_type($url)
{
    static $mime = null;

    if (is_null($mime)) {
        // Default values, overriden by /etc/mime.types when present
        $mime = [
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'png' => 'image/png',
            'image' => 'image/*',
            'mp3' => 'audio/mpeg',
        ];
        // '@' is used to mute open_basedir warning, see issue #818
        if (@is_readable('/etc/mime.types')) {
            $file = fopen('/etc/mime.types', 'r');
            while (($line = fgets($file)) !== false) {
                $line = trim(preg_replace('/#.*/', '', $line));
                if (!$line) {
                    continue;
                }
                $parts = preg_split('/\s+/', $line);
                if (count($parts) == 1) {
                    continue;
                }
                $type = array_shift($parts);
                foreach ($parts as $part) {
                    $mime[$part] = $type;
                }
            }
            fclose($file);
        }
    }

    if (strpos($url, '?') !== false) {
        $url_temp = substr($url, 0, strpos($url, '?'));
        if (strpos($url, '#') !== false) {
            $anchor = substr($url, strpos($url, '#'));
            $url_temp .= $anchor;
        }
        $url = $url_temp;
    }

    $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
    if (!empty($mime[$ext])) {
        return $mime[$ext];
    }

    return 'application/octet-stream';
}

/**
 * https://stackoverflow.com/a/2510459
 */
function format_bytes(int $bytes, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);

    return round($bytes, $precision) . ' ' . $units[$pow];
}

function now(): \DateTimeImmutable
{
    return new \DateTimeImmutable();
}

function check_installation_requirements(): array
{
    $errors = [];

    if (version_compare(\PHP_VERSION, '7.4.0') === -1) {
        $errors[] = 'RSS-Bridge requires at least PHP version 7.4.0!';
    }

    // OpenSSL: https://www.php.net/manual/en/book.openssl.php
    if (!extension_loaded('openssl')) {
        $errors[] = 'openssl extension not loaded';
    }

    // libxml: https://www.php.net/manual/en/book.libxml.php
    if (!extension_loaded('libxml')) {
        $errors[] = 'libxml extension not loaded';
    }

    // Multibyte String (mbstring): https://www.php.net/manual/en/book.mbstring.php
    if (!extension_loaded('mbstring')) {
        $errors[] = 'mbstring extension not loaded';
    }

    // SimpleXML: https://www.php.net/manual/en/book.simplexml.php
    if (!extension_loaded('simplexml')) {
        $errors[] = 'simplexml extension not loaded';
    }

    // Client URL Library (curl): https://www.php.net/manual/en/book.curl.php
    // Allow RSS-Bridge to run without curl module in CLI mode without root certificates
    if (!extension_loaded('curl') && !(php_sapi_name() === 'cli' && empty(ini_get('curl.cainfo')))) {
        $errors[] = 'curl extension not loaded';
    }

    // JavaScript Object Notation (json): https://www.php.net/manual/en/book.json.php
    if (!extension_loaded('json')) {
        $errors[] = 'json extension not loaded';
    }

    return $errors;
}
