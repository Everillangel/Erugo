<?php

namespace App\Utils;

/**
 * Validation helpers for the branding/style settings that are exposed as CSS
 * custom properties (background scaling, logo placement and fonts).
 *
 * These settings end up inside a <style> block in the page head, so every value
 * is checked against an explicit allowlist instead of being echoed as-is.
 * Keeping the rules here means the blade template, the API and the tests all
 * share one definition of what is a valid value.
 */
class UiStyleSettings
{
  /**
   * Selectable background-size values.
   */
  public const BACKGROUND_SIZES = [
    'cover',
    'contain',
    '100% auto',
    'auto 100%',
  ];

  /**
   * Selectable background-position values.
   */
  public const BACKGROUND_POSITIONS = [
    'center center',
    'center top',
    'center bottom',
    'top left',
    'top right',
    'bottom left',
    'bottom right',
    'left center',
    'right center',
  ];

  /**
   * Selectable logo alignments, mapped to justify-content.
   */
  public const LOGO_ALIGNMENTS = [
    'left' => 'flex-start',
    'center' => 'center',
    'right' => 'flex-end',
  ];

  /**
   * Valid background-size, e.g. "cover", "contain", "100% auto".
   */
  public static function backgroundSize($value, string $fallback = 'cover'): string
  {
    return in_array(self::normalizeInput($value), self::BACKGROUND_SIZES, true)
      ? self::normalizeInput($value)
      : $fallback;
  }

  /**
   * Valid background-position, e.g. "center center" or "center top".
   */
  public static function backgroundPosition($value, string $fallback = 'center center'): string
  {
    $normalized = self::normalizeInput($value);

    return in_array($normalized, self::BACKGROUND_POSITIONS, true) ? $normalized : $fallback;
  }

  /**
   * Logo alignment as a justify-content value.
   */
  public static function logoAlignment($value, string $fallback = 'flex-start'): string
  {
    $normalized = strtolower(trim((string) $value));

    return self::LOGO_ALIGNMENTS[$normalized] ?? $fallback;
  }

  /**
   * A font stack, normalized so every family is explicitly quoted.
   *
   * Quoting matters: an identifier cannot start with a digit, so a family such
   * as "Source Sans 3" is only valid CSS when quoted. Generic families are kept
   * unquoted so they still resolve to the generic font.
   *
   * @param string|null $value
   * @param string|null $fallback Null returns null, which lets callers emit
   *                              "inherit this from the body font".
   */
  public static function fontFamily($value, ?string $fallback = '"Outfit", sans-serif'): ?string
  {
    $value = trim(preg_replace('/\s+/', ' ', (string) $value));

    if ($value === '') {
      return $fallback;
    }

    $genericFamilies = [
      'serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui',
      'ui-serif', 'ui-sans-serif', 'ui-monospace', 'emoji', 'math', 'fangsong',
    ];

    $normalized = [];

    foreach (explode(',', $value) as $family) {
      $family = trim(trim($family), "\"'");

      if ($family === '') {
        continue;
      }

      // Plain family names only: letters, digits, spaces and hyphens.
      if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9 \-]*$/', $family)) {
        return $fallback;
      }

      $normalized[] = in_array(strtolower($family), $genericFamilies, true)
        ? strtolower($family)
        : '"' . $family . '"';
    }

    return $normalized === [] ? $fallback : implode(', ', $normalized);
  }

  /**
   * A stylesheet URL for loading a webfont: https on a trusted font host, or a
   * same-origin path. Returns an empty string when the value is not usable.
   */
  public static function stylesheetUrl($value): string
  {
    $value = trim((string) $value);

    if ($value === '') {
      return '';
    }

    if (preg_match('/javascript:|data:|expression\s*\(/i', $value)) {
      return '';
    }

    // Same-origin path, e.g. /storage/fonts/myfont.css (query string allowed so
    // uploads can be cache-busted). Protocol-relative URLs are rejected.
    if (str_starts_with($value, '/') && !str_starts_with($value, '//')) {
      return preg_match('@^/[A-Za-z0-9\-._~/%?=&#]*$@', $value) ? $value : '';
    }

    $parts = parse_url($value);

    if (
      $parts === false ||
      !isset($parts['scheme'], $parts['host']) ||
      strtolower($parts['scheme']) !== 'https'
    ) {
      return '';
    }

    if (!in_array(strtolower($parts['host']), ['fonts.googleapis.com', 'fonts.gstatic.com'], true)) {
      return '';
    }

    // Strip only characters that could break out of the attribute/CSS context.
    // Semicolons are kept: Google Fonts uses them for weight ranges (wght@400;700).
    return preg_replace('/["\'<>()\s\\\\]/', '', $value);
  }

  /**
   * A background image reference must be a bare filename of an uploaded
   * background, never a path, because it is joined onto the /backgrounds route.
   */
  public static function backgroundImage($value): string
  {
    $value = trim((string) $value);

    if ($value === '' || basename($value) !== $value || str_contains($value, '..')) {
      return '';
    }

    // Same characters the upload sanitiser allows, with a media extension.
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._-]*\.(jpe?g|png|gif|webp|mp4|webm)$/i', $value)) {
      return '';
    }

    return $value;
  }

  private static function normalizeInput($value): string
  {
    return strtolower(trim(preg_replace('/\s+/', ' ', (string) $value)));
  }
}
