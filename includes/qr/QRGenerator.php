<?php

/**
 * Minimal pure-PHP QR code generator (Model 2, versions 1-10).
 * Supports only byte encoding. Produces a PNG image.
 * Based on the ISO/IEC 18004 standard (simplified subset).
 */
class QRGenerator {
    // GF(256) tables for Reed-Solomon
    private static array $GF_EXP = [];
    private static array $GF_LOG = [];
    private static bool  $tablesReady = false;

    // Block structure for EC level M: ['ec' => ecCWperBlock, 'groups' => [[blockCount, dataCWperBlock], ...]]
    private const BLOCK_STRUCTURE = [
        1  => ['ec' => 10, 'groups' => [[1, 16]]],
        2  => ['ec' => 16, 'groups' => [[1, 28]]],
        3  => ['ec' => 26, 'groups' => [[1, 44]]],
        4  => ['ec' => 18, 'groups' => [[2, 32]]],
        5  => ['ec' => 24, 'groups' => [[2, 43]]],
        6  => ['ec' => 16, 'groups' => [[4, 27]]],
        7  => ['ec' => 18, 'groups' => [[4, 31]]],
        8  => ['ec' => 22, 'groups' => [[2, 38], [2, 39]]],
        9  => ['ec' => 22, 'groups' => [[3, 36], [2, 37]]],
        10 => ['ec' => 26, 'groups' => [[4, 43], [1, 44]]],
    ];

    // Total data codewords per version, EC level M (derived from BLOCK_STRUCTURE for selectVersion)
    private const DATA_CAPACITY = [
        1 => 16, 2 => 28, 3 => 44, 4 => 64,  5 => 86,
        6 => 108, 7 => 124, 8 => 154, 9 => 182, 10 => 216,
    ];

    // Alignment pattern center row/col positions per version
    private const ALIGN_POSITIONS = [
        1 => [],
        2 => [6, 18],  3 => [6, 22],  4 => [6, 26],
        5 => [6, 30],  6 => [6, 34],  7 => [6, 22, 38],
        8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Generate QR code PNG and save to $path.
     * @param string $data   The text to encode
     * @param string $path   Full filesystem path for the output PNG
     * @param int    $scale  Pixel size per module (default 6)
     * @param int    $margin Quiet zone in modules (default 4)
     */
    public static function generate(string $data, string $path, int $scale = 6, int $margin = 4): void {
        self::buildGFTables();

        $version  = self::selectVersion($data);
        $n        = 21 + ($version - 1) * 4;
        $matrix   = array_fill(0, $n, array_fill(0, $n, -1));
        // Tracks which cells are function pattern modules (must not be masked)
        $reserved = array_fill(0, $n, array_fill(0, $n, false));

        self::placeFinderPatterns($matrix, $reserved, $n);
        self::placeTimingPatterns($matrix, $reserved, $n);
        self::placeAlignmentPatterns($matrix, $reserved, $n, $version);
        self::placeFormatInfo($matrix, $reserved, $n, 0b101010000010010); // EC=M, mask=0
        if ($version >= 7) {
            self::placeVersionInfo($matrix, $reserved, $n, $version);
        }

        $codewords = self::encode($data, $version);
        self::placeData($matrix, $n, $codewords);
        self::applyMask($matrix, $reserved, $n, 0);

        self::renderPng($matrix, $n, $scale, $margin, $path);
    }

    // ── GF(256) ───────────────────────────────────────────────────────────────

    private static function buildGFTables(): void {
        if (self::$tablesReady) return;
        $x = 1;
        for ($i = 0; $i < 256; $i++) {
            self::$GF_EXP[$i] = $x;
            self::$GF_LOG[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) $x ^= 0x11D;
        }
        self::$GF_EXP[255] = self::$GF_EXP[0];
        self::$tablesReady = true;
    }

    private static function gfMul(int $a, int $b): int {
        if ($a === 0 || $b === 0) return 0;
        return self::$GF_EXP[(self::$GF_LOG[$a] + self::$GF_LOG[$b]) % 255];
    }

    private static function rsGeneratorPoly(int $n): array {
        $poly = [1];
        for ($i = 0; $i < $n; $i++) {
            $a   = self::$GF_EXP[$i];
            $new = array_fill(0, count($poly) + 1, 0);
            foreach ($poly as $j => $c) {
                $new[$j]     ^= $c;
                $new[$j + 1] ^= self::gfMul($c, $a);
            }
            $poly = $new;
        }
        return $poly;
    }

    private static function rsEncode(array $data, int $ecCount): array {
        $gen = self::rsGeneratorPoly($ecCount);
        $msg = array_merge($data, array_fill(0, $ecCount, 0));
        for ($i = 0; $i < count($data); $i++) {
            $coef = $msg[$i];
            if ($coef !== 0) {
                for ($j = 0; $j < count($gen); $j++) {
                    $msg[$i + $j] ^= self::gfMul($gen[$j], $coef);
                }
            }
        }
        return array_slice($msg, count($data));
    }

    // ── Version selection ─────────────────────────────────────────────────────

    private static function selectVersion(string $data): int {
        $len = strlen($data);
        foreach (self::DATA_CAPACITY as $v => $cap) {
            // 2 codewords consumed by mode indicator (4 bits) + char count (8 bits)
            if ($len <= $cap - 2) return $v;
        }
        throw new RuntimeException('Data too long for QR code (max ~214 chars).');
    }

    // ── Encoding ──────────────────────────────────────────────────────────────

    private static function encode(string $data, int $version): array {
        $struct      = self::BLOCK_STRUCTURE[$version];
        $ecCount     = $struct['ec'];
        $totalDataCW = array_sum(array_map(fn($g) => $g[0] * $g[1], $struct['groups']));

        $bits  = '0100'; // byte mode indicator
        $bits .= sprintf('%08b', strlen($data));
        foreach (str_split($data) as $ch) {
            $bits .= sprintf('%08b', ord($ch));
        }

        $totalBits = $totalDataCW * 8;
        $bits .= str_repeat('0', min(4, max(0, $totalBits - strlen($bits)))); // terminator
        while (strlen($bits) % 8 !== 0) $bits .= '0'; // pad to byte boundary
        $pad = ['11101100', '00010001'];
        $i   = 0;
        while (strlen($bits) < $totalBits) $bits .= $pad[$i++ % 2]; // pad codewords

        $codewords = [];
        for ($i = 0; $i < strlen($bits); $i += 8) {
            $codewords[] = bindec(substr($bits, $i, 8));
        }

        // Build blocks according to BLOCK_STRUCTURE (handles unequal block sizes)
        $dataBlocks = [];
        $ecBlocks   = [];
        $offset     = 0;
        foreach ($struct['groups'] as [$cnt, $size]) {
            for ($b = 0; $b < $cnt; $b++) {
                $block        = array_slice($codewords, $offset, $size);
                $offset      += $size;
                $dataBlocks[] = $block;
                $ecBlocks[]   = self::rsEncode($block, $ecCount);
            }
        }

        // Interleave data codewords
        $out    = [];
        $maxLen = max(array_map('count', $dataBlocks));
        for ($i = 0; $i < $maxLen; $i++) {
            foreach ($dataBlocks as $block) {
                if (isset($block[$i])) $out[] = $block[$i];
            }
        }
        // Interleave EC codewords
        for ($i = 0; $i < $ecCount; $i++) {
            foreach ($ecBlocks as $block) {
                $out[] = $block[$i];
            }
        }
        return $out;
    }

    // ── Matrix helpers ────────────────────────────────────────────────────────

    private static function placeFinderPattern(array &$m, array &$reserved, int $row, int $col): void {
        $pat = [
            [1,1,1,1,1,1,1],
            [1,0,0,0,0,0,1],
            [1,0,1,1,1,0,1],
            [1,0,1,1,1,0,1],
            [1,0,1,1,1,0,1],
            [1,0,0,0,0,0,1],
            [1,1,1,1,1,1,1],
        ];
        $n = count($m);
        for ($r = 0; $r < 7; $r++) {
            for ($c = 0; $c < 7; $c++) {
                $mr = $row + $r;
                $mc = $col + $c;
                if ($mr >= 0 && $mc >= 0 && $mr < $n && $mc < $n) {
                    $m[$mr][$mc]        = $pat[$r][$c];
                    $reserved[$mr][$mc] = true;
                }
            }
        }
        // Separator (border of 0s around finder)
        for ($i = -1; $i <= 7; $i++) {
            self::setReserved($m, $reserved, $row - 1, $col + $i, 0);
            self::setReserved($m, $reserved, $row + 7, $col + $i, 0);
            self::setReserved($m, $reserved, $row + $i, $col - 1, 0);
            self::setReserved($m, $reserved, $row + $i, $col + 7, 0);
        }
    }

    private static function setReserved(array &$m, array &$reserved, int $r, int $c, int $v): void {
        $n = count($m);
        if ($r >= 0 && $r < $n && $c >= 0 && $c < $n) {
            $m[$r][$c]        = $v;
            $reserved[$r][$c] = true;
        }
    }

    private static function placeFinderPatterns(array &$m, array &$reserved, int $n): void {
        self::placeFinderPattern($m, $reserved, 0, 0);
        self::placeFinderPattern($m, $reserved, 0, $n - 7);
        self::placeFinderPattern($m, $reserved, $n - 7, 0);
    }

    private static function placeTimingPatterns(array &$m, array &$reserved, int $n): void {
        for ($i = 8; $i < $n - 8; $i++) {
            $v = ($i % 2 === 0) ? 1 : 0;
            if (!$reserved[6][$i]) { $m[6][$i] = $v; $reserved[6][$i] = true; }
            if (!$reserved[$i][6]) { $m[$i][6] = $v; $reserved[$i][6] = true; }
        }
    }

    private static function placeAlignmentPatterns(array &$m, array &$reserved, int $n, int $version): void {
        $positions = self::ALIGN_POSITIONS[$version];
        foreach ($positions as $row) {
            foreach ($positions as $col) {
                // Skip positions that overlap finder pattern regions (including separators)
                if ($row - 2 <= 8 && $col - 2 <= 8) continue;           // top-left
                if ($row - 2 <= 8 && $col + 2 >= $n - 8) continue;      // top-right
                if ($row + 2 >= $n - 8 && $col - 2 <= 8) continue;      // bottom-left

                for ($dr = -2; $dr <= 2; $dr++) {
                    for ($dc = -2; $dc <= 2; $dc++) {
                        $isEdge   = abs($dr) === 2 || abs($dc) === 2;
                        $isCenter = $dr === 0 && $dc === 0;
                        $m[$row + $dr][$col + $dc]        = ($isEdge || $isCenter) ? 1 : 0;
                        $reserved[$row + $dr][$col + $dc] = true;
                    }
                }
            }
        }
    }

    private static function placeFormatInfo(array &$m, array &$reserved, int $n, int $fmt): void {
        $bits = [];
        for ($i = 14; $i >= 0; $i--) $bits[] = ($fmt >> $i) & 1;
        // $bits[0] = bit14 (MSB), $bits[14] = bit0 (LSB)

        // First copy: around top-left finder
        $seq = [
            [8,0],[8,1],[8,2],[8,3],[8,4],[8,5],[8,7],[8,8],
            [7,8],[5,8],[4,8],[3,8],[2,8],[1,8],[0,8]
        ];
        foreach ($seq as $k => [$r, $c]) {
            $m[$r][$c]        = $bits[$k];
            $reserved[$r][$c] = true;
        }

        // Second copy (bottom-left): bits 0-6 → rows n-7 to n-1, col 8
        for ($i = 0; $i < 7; $i++) {
            $m[$n - 7 + $i][8]        = $bits[14 - $i];
            $reserved[$n - 7 + $i][8] = true;
        }

        // Second copy (top-right): bits 14-7 → row 8, cols n-8 to n-1
        for ($i = 0; $i < 8; $i++) {
            $m[8][$n - 8 + $i]        = $bits[$i];
            $reserved[8][$n - 8 + $i] = true;
        }

        // Dark module (always 1, not masked)
        $m[$n - 8][8]        = 1;
        $reserved[$n - 8][8] = true;
    }

    private static function placeVersionInfo(array &$m, array &$reserved, int $n, int $version): void {
        // Version info blocks are only required for V7+; stub (V1-6 not needed)
    }

    private static function placeData(array &$m, int $n, array $codewords): void {
        $bits = '';
        foreach ($codewords as $cw) $bits .= sprintf('%08b', $cw);
        $bIdx = 0;
        $up   = true;
        for ($col = $n - 1; $col >= 1; $col -= 2) {
            if ($col === 6) $col = 5; // skip timing column
            for ($i = 0; $i < $n; $i++) {
                $row = $up ? ($n - 1 - $i) : $i;
                foreach ([0, -1] as $delta) {
                    $c = $col + $delta;
                    if ($m[$row][$c] === -1) {
                        $m[$row][$c] = ($bIdx < strlen($bits)) ? (int)$bits[$bIdx++] : 0;
                    }
                }
            }
            $up = !$up;
        }
    }

    private static function applyMask(array &$m, array $reserved, int $n, int $mask): void {
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if ($reserved[$r][$c]) continue; // never mask function pattern modules
                $flip = match($mask) {
                    0 => ($r + $c) % 2 === 0,
                    1 => $r % 2 === 0,
                    2 => $c % 3 === 0,
                    3 => ($r + $c) % 3 === 0,
                    4 => (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0,
                    5 => ($r * $c) % 2 + ($r * $c) % 3 === 0,
                    6 => (($r * $c) % 2 + ($r * $c) % 3) % 2 === 0,
                    7 => (($r + $c) % 2 + ($r * $c) % 3) % 2 === 0,
                    default => false,
                };
                if ($flip) $m[$r][$c] ^= 1;
            }
        }
    }

    // ── PNG rendering ─────────────────────────────────────────────────────────

    private static function renderPng(array $m, int $n, int $scale, int $margin, string $path): void {
        $size  = ($n + 2 * $margin) * $scale;
        $img   = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0,   0,   0);
        imagefill($img, 0, 0, $white);

        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if ($m[$r][$c] === 1) { // only render set dark modules
                    $x1 = ($margin + $c) * $scale;
                    $y1 = ($margin + $r) * $scale;
                    imagefilledrectangle($img, $x1, $y1, $x1 + $scale - 1, $y1 + $scale - 1, $black);
                }
            }
        }

        if (!is_dir(dirname($path))) mkdir(dirname($path), 0755, true);
        imagepng($img, $path);
        imagedestroy($img);
    }
}
