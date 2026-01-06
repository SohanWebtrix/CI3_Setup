<?php
// application/helpers/date_display_helper.php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Format a date for display, validating & normalizing diverse inputs.
 *
 * @param mixed       $date         String/number/DateTimeInterface. E.g. '2025-09-08', '08-09-2025', 1694140800, '2025-09-08 13:45:00'
 * @param string|null $userFormat   UI format tokens (e.g., 'DD-MM-YYYY', 'Do MMMM YYYY'). If null, uses $defaultFormat.
 * @param string      $defaultFormat Default UI format when $userFormat is empty/invalid.
 * @param string|null $tzId         Optional IANA timezone (e.g., 'Asia/Kolkata'). If null, uses PHP default timezone.
 * @param string      $onError       String to return when parsing fails.
 * @return string
 */
if (!function_exists('format_display_date')) {
    function format_display_date($date, ?string $userFormat = null, string $defaultFormat = 'DD-MM-YYYY', ?string $tzId = null, string $onError = '-'): string
    {
        $dt = _ddh_parse_to_datetime($date, $tzId);
        if (!$dt) return $onError;

        $fmtPhp = _ddh_user_fmt_to_php($userFormat ?: $defaultFormat);
        if (!$fmtPhp) $fmtPhp = _ddh_user_fmt_to_php('DD-MM-YYYY'); // final fallback

        return $dt->format($fmtPhp);
    }
}

/**
 * Try to parse many common date inputs into DateTimeImmutable.
 * Supports: ISO (with/without time), dd-mm-yyyy, dd/mm/yyyy, yyyy/mm/dd,
 * US m/d/Y, UNIX timestamp (10s or 13ms), and generic strtotime fallbacks.
 */
if (!function_exists('_ddh_parse_to_datetime')) {
    function _ddh_parse_to_datetime($date, ?string $tzId = null): ?DateTimeImmutable
    {
        if ($date instanceof DateTimeInterface) {
            $dt = DateTimeImmutable::createFromInterface($date);
            return $tzId ? $dt->setTimezone(new DateTimeZone($tzId)) : $dt;
        }

        $tz = $tzId ? new DateTimeZone($tzId) : new DateTimeZone(date_default_timezone_get());

        // Numeric timestamp (seconds or milliseconds)
        if (is_numeric($date)) {
            $s = (string)$date;
            if (strlen($s) >= 13) { // ms
                $sec = (int) floor(((int)$s) / 1000);
            } else {
                $sec = (int)$s;
            }
            $dt = (new DateTimeImmutable('@' . $sec))->setTimezone($tz);
            return $dt ?: null;
        }

        if (!is_string($date) || trim($date) === '') return null;
        $str = trim($date);

        // Common explicit patterns (reset time with '!')
        $patterns = [
            '!Y-m-d\TH:i:sP', // ISO 8601 with TZ
            '!Y-m-d\TH:i:s',  // ISO without TZ
            '!Y-m-d H:i:s',
            '!Y-m-d',
            '!Y/m/d H:i:s',
            '!Y/m/d',
            '!d-m-Y H:i:s',
            '!d-m-Y',
            '!d/m/Y H:i:s',
            '!d/m/Y',
            '!d.m.Y',
            '!d-m-y',
            '!d/m/y',
            '!m/d/Y H:i:s',   // US
            '!m/d/Y',
            '!j-n-Y', '!j/n/Y', '!n/j/Y', '!n-j-Y', // single-digit flex
        ];

        foreach ($patterns as $p) {
            $dt = DateTimeImmutable::createFromFormat($p, $str, $tz);
            if ($dt instanceof DateTimeImmutable) {
                // guard against partial parse warnings like 32/13/2025
                $errs = DateTimeImmutable::getLastErrors();
                if (!$errs['warning_count'] && !$errs['error_count']) return $dt;
            }
        }

        // Heuristic: if looks like d-m-Y or d/m/Y, force day-first for strtotime
        if (preg_match('/^\d{1,2}[-\/]\d{1,2}[-\/]\d{2,4}(?:\s+\d{1,2}:\d{2}(:\d{2})?)?$/', $str)) {
            [$a, $b, $c] = preg_split('/[-\/]/', preg_split('/\s+/', $str)[0]);
            if ((int)$a > 12) {
                // Very likely day-first, build safely
                $day = (int)$a; $mon = (int)$b; $yr = (int)$c;
                if ($yr < 100) $yr += 2000;
                if (checkdate($mon, $day, $yr)) {
                    return (new DateTimeImmutable(sprintf('%04d-%02d-%02d', $yr, $mon, $day), $tz));
                }
            }
        }

        // Generic fallback
        $ts = strtotime($str);
        if ($ts !== false) return (new DateTimeImmutable('@' . $ts))->setTimezone($tz);

        return null;
    }
}

/**
 * Map your UI date tokens to PHP date() tokens.
 * Supports case-insensitive inputs like 'dd-mm-yyyy'.
 */
if (!function_exists('_ddh_user_fmt_to_php')) {
    function _ddh_user_fmt_to_php(string $uiFmt): ?string
    {
        $key = strtoupper(trim($uiFmt));

        // Normalize some common alternates
        $aliases = [
            'YYYY-MM-DD' => 'Y-m-d',
            'DD/MM/YYYY' => 'd/m/Y',
            'MM/DD/YYYY' => 'm/d/Y',
            'DD-MM-YYYY' => 'd-m-Y',
            'DD:MM:YYYY' => 'd:m:Y',
            'YYYY/MM/DD' => 'Y/m/d',
        ];

        // Your provided formats
        $map = [
            'DD-MM-YYYY'   => 'd-m-Y',
            'YYYY:MM:DD'   => 'Y:m:d',
            'YY:MM:DD'     => 'y:m:d',
            'DO MMMM YYYY' => 'jS F Y', // e.g., 8th April 2020
            'MMMM DO YYYY' => 'F jS Y', // e.g., April 8th 2020
            'DD:MM:YY'     => 'd:m:y',
        ];

        if (isset($map[$key])) return $map[$key];
        if (isset($aliases[$key])) return $aliases[$key];

        // If caller accidentally passed a PHP format already (heuristic)
        if (preg_match('/[djDlmMFYyS]/', $uiFmt)) return $uiFmt;

        return null;
    }
}
