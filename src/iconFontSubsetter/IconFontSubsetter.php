<?php

namespace Farn\EasyIconFonts\iconFontSubsetter;

use FontLib\Font;
use FontLib\BinaryStream;
use Exception;

/**
 * Icon Font Subsetter using php-font-lib
 *
 * Usage:
 *   $subsetter = new IconFontSubsetter('path/to/font.ttf');
 *   $subsetter->subset(['U+E001', 'U+E002'], 'path/to/subset.ttf');
 */
class IconFontSubsetter {
    private string $sourceFontPath;
    private $sourceFont;
    private array $subsetCodepoints = [];
    private string $subsetString = '';

    public function __construct(string $fontPath) {
        if (!file_exists($fontPath)) {
            throw new Exception("Font file not found: $fontPath");
        }
        $this->sourceFontPath = $fontPath;
    }

    public function subset(array $unicodeGlyphs, string $outputPath, ?array $tables = null): bool {
        $chars = [];

        $this->sourceFont = Font::load($this->sourceFontPath);
        $this->sourceFont->parse();

        $chars = $this->matchSubsetUnicodes($unicodeGlyphs);

        if (empty($chars)) {
            throw new Exception("No valid Unicode points provided");
        }

        $this->subsetString = implode('', $chars);

        $this->sourceFont->setSubset($this->subsetString);
        $this->sourceFont->reduce();

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        WP_Filesystem();

        global $wp_filesystem;
 
        if (!$wp_filesystem->exists($outputPath)) {
            $wp_filesystem->put_contents($outputPath, '', FS_CHMOD_FILE);
        } else {
            $wp_filesystem->touch($outputPath);
        }

        $this->sourceFont->open($outputPath, BinaryStream::modeReadWrite);
        $this->sourceFont->encode(array("OS/2"));
        $this->sourceFont->close($outputPath);

        return file_exists($outputPath) && filesize($outputPath) > 1000;
    }

    public function getSubsetInfo(): array {
        return [
            'unicodePoints'    => $this->subsetCodepoints,
            'unicodePointsHex' => array_map(fn($cp) => sprintf("U+%04X", $cp), $this->subsetCodepoints),
            'glyphCount'       => count($this->subsetCodepoints),
            'subsetString'     => $this->subsetString,
            'sourceFontPath'   => $this->sourceFontPath,
        ];
    }

    private function matchSubsetUnicodes($unicodeGlyphs): array {
        // Get available codepoints from cmap
        $cmap = $this->sourceFont->getData("cmap");
        $availableCodepoints = [];
        if ($cmap && isset($cmap['subtables'])) {
            foreach ($cmap['subtables'] as $subtable) {
                if (!empty($subtable['glyphIndexArray'])) {
                    foreach ($subtable['glyphIndexArray'] as $codepoint => $gid) {
                        if ($gid !== 0) {
                            $availableCodepoints[$codepoint] = $gid;
                        }
                    }
                }
            }
        }

        // Match unicodes to subset against available codepoints
        $this->subsetCodepoints = [];
        foreach ($unicodeGlyphs as $unicode) {
            $codepoint = $this->parseUnicodeString($unicode);
            if ($codepoint !== false && isset($availableCodepoints[$codepoint])) {
                if (!in_array($codepoint, $this->subsetCodepoints)) {
                    $this->subsetCodepoints[] = $codepoint;
                    $char = $this->codepointToUtf8($codepoint);
                    if ($char !== '') {
                        $chars[] = $char;
                    }
                }
            }
        }

        return $chars ?? [];
    }

    private function parseUnicodeString($unicode) {
        if (is_int($unicode)) return $unicode;
        $unicode = trim($unicode);
        // Match U+XXXX format
        if (preg_match('/^U\+([0-9A-Fa-f]+)$/i', $unicode, $m)) return hexdec($m[1]);
        // Match hex format like E04F or 0xE04F
        if (preg_match('/^(?:0x)?([0-9A-Fa-f]+)$/i', $unicode, $m)) return hexdec($m[1]);
        // Match literal \uXXXX in the string
        if (preg_match('/^\\\\u([0-9A-Fa-f]{4})$/i', $unicode, $m)) return hexdec($m[1]);
        // Match literal \XXXX (your format like \E194)
        if (preg_match('/^\\\\([0-9A-Fa-f]{4,6})$/i', $unicode, $m)) return hexdec($m[1]);
        if (is_numeric($unicode)) return intval($unicode);
        return false;
    }

    private function codepointToUtf8(int $codepoint): string {
        if ($codepoint <= 0x7F) return chr($codepoint);
        if ($codepoint <= 0x7FF) return chr(0xC0 | ($codepoint >> 6)) . chr(0x80 | ($codepoint & 0x3F));
        if ($codepoint <= 0xFFFF) return chr(0xE0 | ($codepoint >> 12)) . chr(0x80 | (($codepoint >> 6) & 0x3F)) . chr(0x80 | ($codepoint & 0x3F));
        if ($codepoint <= 0x10FFFF) return chr(0xF0 | ($codepoint >> 18)) . chr(0x80 | (($codepoint >> 12) & 0x3F)) . chr(0x80 | (($codepoint >> 6) & 0x3F)) . chr(0x80 | ($codepoint & 0x3F));
        return '';
    }
}
