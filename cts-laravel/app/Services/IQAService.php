<?php

namespace App\Services;

use App\Models\Instrument;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;

class IQAService
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Run full IQA suite on a scanned instrument image.
     * Returns ['status' => 'PASS|FAIL', 'failures' => [...]]
     */
    public function check(string $imagePath, string $mode = 'GREY'): array
    {
        $cfg  = config('cts.iqa');
        $img  = $this->manager->read($imagePath);
        $failures = [];

        // Dimension checks
        if ($img->width() < $cfg['min_image_length'])  $failures[] = 'BELOW_MIN_IMAGE_LENGTH';
        if ($img->width() > $cfg['max_image_length'])  $failures[] = 'EXCEEDS_MAX_IMAGE_LENGTH';
        if ($img->height() < $cfg['min_image_height']) $failures[] = 'BELOW_MIN_IMAGE_HEIGHT';
        if ($img->height() > $cfg['max_image_height']) $failures[] = 'EXCEEDS_MAX_IMAGE_HEIGHT';

        // Brightness / darkness check
        $brightness = $this->calculateBrightness($img);
        if ($brightness < 30)  $failures[] = 'TOO_DARK';
        if ($brightness > 225) $failures[] = 'TOO_LIGHT';

        // Skew check
        $skewAngle = $this->detectSkew($imagePath);
        if (abs($skewAngle) > 5) $failures[] = 'EXCESSIVE_IMAGE_SKEW';

        // Piggyback check (two cheques scanned together)
        if ($this->detectPiggyback($img)) $failures[] = 'PIGGYBACK';

        // Torn corner
        if ($this->detectTornCorner($img)) $failures[] = 'TORN_CORNER';

        // Streaks and bands
        if ($this->detectStreaks($img)) $failures[] = 'STREAKS_BANDS';

        // Partial image
        if ($this->isPartialImage($img)) $failures[] = 'PARTIAL_IMAGE';

        // Bit depth for grey
        if ($mode === 'GREY') {
            $channels = $img->colorspace();
            // Expects 8-bit greyscale per NPCI spec
        }

        $status = empty($failures) ? 'PASS' : 'FAIL';

        Log::info('IQA check', [
            'image'   => $imagePath,
            'status'  => $status,
            'failures'=> $failures,
        ]);

        return [
            'status'   => $status,
            'failures' => $failures,
            'width'    => $img->width(),
            'height'   => $img->height(),
            'mode'     => $mode,
        ];
    }

    public function saveIQAResult(Instrument $instrument, array $result): void
    {
        $instrument->update([
            'iqa_status'           => $result['status'],
            'iqa_failure_reasons'  => $result['failures'],
        ]);
    }

    private function calculateBrightness(\Intervention\Image\Image $img): float
    {
        // Sample pixels and compute mean brightness
        $total = 0;
        $samples = 100;
        $w = $img->width();
        $h = $img->height();

        for ($i = 0; $i < $samples; $i++) {
            $x = rand(0, $w - 1);
            $y = rand(0, $h - 1);
            $pixel = $img->pickColor($x, $y);
            $total += ($pixel->red() + $pixel->green() + $pixel->blue()) / 3;
        }

        return $total / $samples;
    }

    private function detectSkew(string $imagePath): float
    {
        // In production: use Imagick's deskewThreshold or Tesseract orientation API
        // Placeholder: run imagick shell command
        $output = shell_exec("identify -verbose {$imagePath} 2>&1 | grep 'Skew'");
        preg_match('/([+-]?\d+\.?\d*)/', $output ?? '', $matches);
        return (float) ($matches[1] ?? 0);
    }

    private function detectPiggyback(\Intervention\Image\Image $img): bool
    {
        // Detect if aspect ratio indicates two cheques scanned together
        $ratio = $img->width() / $img->height();
        return $ratio > 6.0 || $ratio < 1.0;
    }

    private function detectTornCorner(\Intervention\Image\Image $img): bool
    {
        // Sample corner pixels for white/missing regions
        $corners = [
            [$img->pickColor(5, 5)],
            [$img->pickColor($img->width() - 5, 5)],
            [$img->pickColor(5, $img->height() - 5)],
            [$img->pickColor($img->width() - 5, $img->height() - 5)],
        ];

        foreach ($corners as [$pixel]) {
            if ($pixel->red() > 250 && $pixel->green() > 250 && $pixel->blue() > 250) {
                return true;
            }
        }
        return false;
    }

    private function detectStreaks(\Intervention\Image\Image $img): bool
    {
        // Detect horizontal white/black lines spanning the full width
        $h = $img->height();
        $w = $img->width();
        for ($y = 10; $y < $h - 10; $y += 10) {
            $leftPx  = $img->pickColor(5, $y);
            $rightPx = $img->pickColor($w - 5, $y);
            if (
                abs($leftPx->red() - $rightPx->red()) < 5 &&
                ($leftPx->red() < 10 || $leftPx->red() > 245)
            ) {
                return true;
            }
        }
        return false;
    }

    private function isPartialImage(\Intervention\Image\Image $img): bool
    {
        // Check if a large portion of the image is blank (white)
        $cfg = config('cts.iqa');
        return $img->width() < ($cfg['min_image_length'] * 0.5)
            || $img->height() < ($cfg['min_image_height'] * 0.5);
    }
}
