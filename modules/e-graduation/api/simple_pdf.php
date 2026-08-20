<?php
/**
 * Tiny PDF writer for E-Graduation SKL output.
 * Uses built-in PDF Type1 fonts and JPEG image XObjects.
 */
class GraduationSimplePdf
{
    // F4: 210 x 330 mm (1 mm = 2.83464567 pt)
    private $width = 595.2756;   // 210mm
    private $height = 935.4331;  // 330mm
    private $objects = [];
    private $pages = [];
    private $commands = '';
    private $images = [];
    private $imageSeq = 0;

    public function width()
    {
        return $this->width;
    }

    public function height()
    {
        return $this->height;
    }

    public function addPage()
    {
        if ($this->commands !== '') {
            $this->pages[] = ['content' => $this->commands, 'images' => $this->images];
        }
        $this->commands = '';
        $this->images = [];
    }

    public function finishPage()
    {
        if ($this->commands !== '') {
            $this->pages[] = ['content' => $this->commands, 'images' => $this->images];
            $this->commands = '';
            $this->images = [];
        }
    }

    public function text($x, $y, $text, $size = 12, $font = 'F1', $align = 'left', $boxWidth = 0)
    {
        $encoded = $this->encodeText($text);
        if ($align !== 'left' && $boxWidth > 0) {
            $tw = $this->textWidth($text, $size);
            if ($align === 'center') {
                $x += max(0, ($boxWidth - $tw) / 2);
            } elseif ($align === 'right') {
                $x += max(0, $boxWidth - $tw);
            }
        }
        $yPos = $this->height - $y;
        $this->commands .= sprintf("BT /%s %s Tf %s %s Td (%s) Tj ET\n", 
            $font, 
            $this->f($size), 
            $this->f($x), 
            $this->f($yPos), 
            $encoded
        );
    }

    public function wrapText($x, $y, $width, $text, $size = 12, $lineHeight = 15, $font = 'F1', $align = 'left')
    {
        $paragraphs = preg_split('/\R/u', (string) $text);
        $printed = false;
        foreach ($paragraphs as $paragraph) {
            $lines = $this->wrapParagraphLines($paragraph, $width, $size);
            $lastIndex = count($lines) - 1;
            foreach ($lines as $idx => $line) {
                $shouldJustify = $align === 'justify' && $idx < $lastIndex && strpos($line, ' ') !== false;
                if ($shouldJustify) {
                    $this->justifiedText($x, $y, $line, $width, $size, $font);
                } else {
                    $lineAlign = $align === 'justify' ? 'left' : $align;
                    $this->text($x, $y, $line, $size, $font, $lineAlign, $width);
                }
                $y += $lineHeight;
                $printed = true;
            }
        }
        if (!$printed) {
            $this->text($x, $y, '', $size, $font, 'left', $width);
            $y += $lineHeight;
        }
        return $y;
    }

    public function justifyLine($x, $y, $text, $boxWidth, $size = 12, $font = 'F1')
    {
        $this->justifiedText($x, $y, $text, $boxWidth, $size, $font);
    }

    public function line($x1, $y1, $x2, $y2, $width = 1)
    {
        $this->commands .= sprintf("%s w %s %s m %s %s l S\n", 
            $this->f($width), 
            $this->f($x1), 
            $this->f($this->height - $y1), 
            $this->f($x2), 
            $this->f($this->height - $y2)
        );
    }

    public function rect($x, $y, $w, $h, $stroke = true, $fill = false)
    {
        $op = $fill && $stroke ? 'B' : ($fill ? 'f' : 'S');
        $this->commands .= sprintf("%s %s %s %s re %s\n", 
            $this->f($x), 
            $this->f($this->height - $y - $h), 
            $this->f($w), 
            $this->f($h), 
            $op
        );
    }

    public function image($path, $x, $y, $w, $h = 0)
    {
        $jpeg = $this->imageToJpeg($path);
        if (!$jpeg) return false;
        if ($w <= 0 && $h <= 0) {
            return false;
        }
        if ($h <= 0) {
            $h = $w * $jpeg['height'] / max(1, $jpeg['width']);
        } elseif ($w <= 0) {
            $w = $h * $jpeg['width'] / max(1, $jpeg['height']);
        }
        $name = 'Im' . (++$this->imageSeq);
        $this->images[$name] = $jpeg;
        $yPos = $this->height - $y - $h;
        $this->commands .= sprintf("q %s 0 0 %s %s %s cm /%s Do Q\n", 
            $this->f($w), 
            $this->f($h), 
            $this->f($x), 
            $this->f($yPos), 
            $name
        );
        return true;
    }

    public function imageDimensions($path)
    {
        $jpeg = $this->imageToJpeg($path);
        if (!$jpeg) {
            return null;
        }
        return ['width' => (float) $jpeg['width'], 'height' => (float) $jpeg['height']];
    }

    public function output()
    {
        $this->finishPage();
        $this->objects = [null];

        $fontRegular = $this->addObject("<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman /Encoding /WinAnsiEncoding >>");
        $fontBold = $this->addObject("<< /Type /Font /Subtype /Type1 /BaseFont /Times-Bold /Encoding /WinAnsiEncoding >>");
        $fontItalic = $this->addObject("<< /Type /Font /Subtype /Type1 /BaseFont /Times-Italic /Encoding /WinAnsiEncoding >>");

        $pageObjectIds = [];
        foreach ($this->pages as $page) {
            $imageIds = [];
            foreach ($page['images'] as $name => $img) {
                $stream = "<< /Type /XObject /Subtype /Image /Width {$img['width']} /Height {$img['height']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($img['data']) . " >>\nstream\n" . $img['data'] . "\nendstream";
                $imageIds[$name] = $this->addObject($stream);
            }

            $contentId = $this->addObject("<< /Length " . strlen($page['content']) . " >>\nstream\n" . $page['content'] . "endstream");
            $resources = "<< /Font << /F1 {$fontRegular} 0 R /F2 {$fontBold} 0 R /F3 {$fontItalic} 0 R >>";
            if ($imageIds) {
                $resources .= " /XObject <<";
                foreach ($imageIds as $name => $id) {
                    $resources .= " /{$name} {$id} 0 R";
                }
                $resources .= " >>";
            }
            $resources .= " >>";
            $widthF = $this->f($this->width);
            $heightF = $this->f($this->height);
            $pageObjectIds[] = $this->addObject("<< /Type /Page /Parent __PAGES__ 0 R /MediaBox [0 0 {$widthF} {$heightF}] /Resources {$resources} /Contents {$contentId} 0 R >>");
        }

        $kids = implode(' ', array_map(function ($id) { return "{$id} 0 R"; }, $pageObjectIds));
        $pagesId = $this->addObject("<< /Type /Pages /Kids [{$kids}] /Count " . count($pageObjectIds) . " >>");
        foreach ($pageObjectIds as $id) {
            $this->objects[$id] = str_replace('__PAGES__', (string) $pagesId, $this->objects[$id]);
        }
        $catalogId = $this->addObject("<< /Type /Catalog /Pages {$pagesId} 0 R >>");

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        for ($i = 1; $i < count($this->objects); $i++) {
            $offsets[$i] = strlen($pdf);
            $pdf .= "{$i} 0 obj\n{$this->objects[$i]}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . count($this->objects) . "\n0000000000 65535 f \n";
        for ($i = 1; $i < count($this->objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . count($this->objects) . " /Root {$catalogId} 0 R >>\nstartxref\n{$xref}\n%%EOF";
        return $pdf;
    }

    // Adobe Times-Roman glyph widths (per 1000 units)
    private static $timesWidths = [
        ' ' => 250, '!' => 333, '"' => 408, '#' => 500, '$' => 500, '%' => 833, '&' => 778, "'" => 180,
        '(' => 333, ')' => 333, '*' => 500, '+' => 564, ',' => 250, '-' => 333, '.' => 250, '/' => 278,
        '0' => 500, '1' => 500, '2' => 500, '3' => 500, '4' => 500, '5' => 500, '6' => 500, '7' => 500, '8' => 500, '9' => 500,
        ':' => 278, ';' => 278, '<' => 564, '=' => 564, '>' => 564, '?' => 444,  '@' => 921,
        'A' => 722, 'B' => 667, 'C' => 667, 'D' => 722, 'E' => 611, 'F' => 556, 'G' => 722, 'H' => 722,
        'I' => 333, 'J' => 389, 'K' => 722, 'L' => 611, 'M' => 889, 'N' => 722, 'O' => 722, 'P' => 556,
        'Q' => 722, 'R' => 667, 'S' => 556, 'T' => 611, 'U' => 722, 'V' => 722, 'W' => 944, 'X' => 722,
        'Y' => 722, 'Z' => 611,
        '[' => 333, '\\' => 278, ']' => 333, '^' => 469, '_' => 500, '`' => 333,
        'a' => 444, 'b' => 500, 'c' => 444, 'd' => 500, 'e' => 444, 'f' => 333, 'g' => 500, 'h' => 500,
        'i' => 278, 'j' => 278, 'k' => 500, 'l' => 278, 'm' => 778, 'n' => 500, 'o' => 500, 'p' => 500,
        'q' => 500, 'r' => 333, 's' => 389, 't' => 278, 'u' => 500, 'v' => 500, 'w' => 722, 'x' => 500,
        'y' => 500, 'z' => 444,
        '{' => 480, '|' => 200, '}' => 480, '~' => 541,
    ];

    public function textWidth($text, $size)
    {
        $text = (string) ($text ?? '');
        if ($text === '') return 0;

        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $width = 0.0;
        foreach ($chars as $ch) {
            $w = self::$timesWidths[$ch] ?? 500;
            $width += $size * $w / 1000.0;
        }
        return $width;
    }

    private function f($num)
    {
        return number_format((float) $num, 3, '.', '');
    }

    private function wrapLines($text, $width, $size)
    {
        $paragraphs = preg_split('/\R/u', (string) $text);
        $lines = [];
        foreach ($paragraphs as $paragraph) {
            $lines = array_merge($lines, $this->wrapParagraphLines($paragraph, $width, $size));
        }
        return $lines ?: [''];
    }

    private function wrapParagraphLines($paragraph, $width, $size)
    {
        $words = preg_split('/\s+/u', trim((string) $paragraph));
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            if ($word === '') continue;
            $try = $line === '' ? $word : $line . ' ' . $word;
            if ($this->textWidth($try, $size) <= $width || $line === '') {
                $line = $try;
            } else {
                $lines[] = $line;
                $line = $word;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        return $lines;
    }

    private function justifiedText($x, $y, $text, $boxWidth, $size = 12, $font = 'F1')
    {
        $spaces = substr_count($text, ' ');
        if ($spaces <= 0) {
            $this->text($x, $y, $text, $size, $font, 'left', $boxWidth);
            return;
        }

        $extra = $boxWidth - $this->textWidth($text, $size);
        if ($extra <= 0) {
            $this->text($x, $y, $text, $size, $font, 'left', $boxWidth);
            return;
        }

        $encoded = $this->encodeText($text);
        $wordSpacing = $extra / $spaces;
        $this->commands .= sprintf("BT /%s %s Tf %s %s Td %s Tw (%s) Tj 0 Tw ET\n", 
            $font, 
            $this->f($size), 
            $this->f($x), 
            $this->f($this->height - $y), 
            $this->f($wordSpacing), 
            $encoded
        );
    }

    private function addObject($content)
    {
        $this->objects[] = $content;
        return count($this->objects) - 1;
    }

    private function encodeText($text)
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $this->toWinAnsi($text));
    }

    private function toWinAnsi($text)
    {
        $text = (string) ($text ?? '');
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
            if ($converted !== false) return $converted;
        }
        return preg_replace('/[^\x20-\x7E]/', '', $text);
    }

    private function imageToJpeg($path)
    {
        if (!is_file($path)) return null;
        $info = @getimagesize($path);
        if (!$info) return null;

        switch ($info[2]) {
            case IMAGETYPE_JPEG:
                $data = file_get_contents($path);
                return ['data' => $data, 'width' => $info[0], 'height' => $info[1]];
            case IMAGETYPE_PNG:
                $src = @imagecreatefrompng($path);
                break;
            case IMAGETYPE_WEBP:
                $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
                break;
            case IMAGETYPE_GIF:
                $src = @imagecreatefromgif($path);
                break;
            default:
                $src = false;
        }
        if (!$src) return null;

        $canvas = imagecreatetruecolor(imagesx($src), imagesy($src));
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, imagesx($src), imagesy($src), $white);
        imagecopy($canvas, $src, 0, 0, 0, 0, imagesx($src), imagesy($src));
        ob_start();
        imagejpeg($canvas, null, 100);
        $data = ob_get_clean();
        imagedestroy($src);
        imagedestroy($canvas);
        return ['data' => $data, 'width' => $info[0], 'height' => $info[1]];
    }
}
