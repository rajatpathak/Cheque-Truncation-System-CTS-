<?php

namespace App\Http\Controllers\ImageStorage;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Instrument;
use App\Services\NotificationService;

class ImageController extends Controller
{
    public function __construct(private NotificationService $notify) {}

    /**
     * Retrieve a cheque image with magnification support.
     * Images are served with secure authenticated URLs only.
     */
    public function retrieve(Request $request, string $instrumentId): mixed
    {
        $instrument = Instrument::where('instrument_id', $instrumentId)->firstOrFail();
        $mode       = $request->query('mode', 'GREY');    // GREY | BW | UV

        $imagePath = match (strtoupper($mode)) {
            'BW'  => $instrument->image_path_bw,
            'UV'  => $instrument->image_path_uv,
            default => $instrument->image_path_grey,
        };

        if (!$imagePath || !file_exists($imagePath)) {
            return response()->json(['error' => 'IMAGE_NOT_FOUND'], 404);
        }

        $mimeType = mime_content_type($imagePath);
        return response()->file($imagePath, [
            'Content-Type'        => $mimeType,
            'Content-Disposition' => 'inline',
            'Cache-Control'       => 'no-store, no-cache',
            'X-Frame-Options'     => 'SAMEORIGIN',
        ]);
    }

    public function retrieveUV(Request $request, string $instrumentId): mixed
    {
        $instrument = Instrument::where('instrument_id', $instrumentId)->firstOrFail();

        if (!$instrument->image_path_uv || !file_exists($instrument->image_path_uv)) {
            return response()->json(['error' => 'UV_IMAGE_NOT_FOUND'], 404);
        }

        return response()->file($instrument->image_path_uv, ['Content-Type' => mime_content_type($instrument->image_path_uv)]);
    }

    public function serveInstrumentImage(Request $request, string $id): mixed
    {
        return $this->retrieve($request, $id);
    }

    /**
     * Extract and serve a region snippet for data entry display.
     */
    public function serveSnippet(Request $request, string $id): mixed
    {
        $instrument = Instrument::where('instrument_id', $id)->firstOrFail();
        $field      = $request->query('field', 'amount_figures');

        $imagick = new \Imagick($instrument->image_path_grey);
        $regions = [
            'amount_figures' => ['x' => 750, 'y' => 120, 'w' => 300, 'h' => 60],
            'amount_words'   => ['x' => 50,  'y' => 140, 'w' => 700, 'h' => 50],
            'payee_name'     => ['x' => 50,  'y' => 80,  'w' => 600, 'h' => 50],
            'date'           => ['x' => 700, 'y' => 40,  'w' => 300, 'h' => 50],
        ];

        $r = $regions[$field] ?? ['x' => 0, 'y' => 0, 'w' => 400, 'h' => 100];
        $imagick->cropImage($r['w'], $r['h'], $r['x'], $r['y']);

        return response($imagick->getImageBlob(), 200, ['Content-Type' => 'image/jpeg']);
    }

    public function magnify(Request $request, string $instrumentId): mixed
    {
        $instrument = Instrument::where('instrument_id', $instrumentId)->firstOrFail();
        $zoom       = min((float) $request->query('zoom', 2.0), 8.0);
        $x          = (int) $request->query('x', 0);
        $y          = (int) $request->query('y', 0);
        $w          = (int) $request->query('w', 200);
        $h          = (int) $request->query('h', 100);

        $imagick = new \Imagick($instrument->image_path_grey);
        $imagick->cropImage($w, $h, $x, $y);
        $imagick->resizeImage((int)($w * $zoom), (int)($h * $zoom), \Imagick::FILTER_LANCZOS, 1);

        return response($imagick->getImageBlob(), 200, ['Content-Type' => 'image/jpeg']);
    }

    public function extractQRBarcode(Request $request, string $instrumentId): JsonResponse
    {
        $instrument = Instrument::where('instrument_id', $instrumentId)->firstOrFail();

        $response = \Http::post(config('services.ai_engine.url') . '/qr-extract', [
            'image' => $instrument->image_path_grey,
        ]);

        return response()->json($response->json());
    }

    public function archive(Request $request, string $instrumentId): JsonResponse
    {
        $instrument = Instrument::where('instrument_id', $instrumentId)->firstOrFail();

        // Move to long-term archive (WORM-compliant storage)
        $archivePath = storage_path("app/archive/{$instrument->instrument_id}");
        @mkdir($archivePath, 0750, true);

        foreach (['image_path_grey', 'image_path_bw', 'image_path_uv'] as $field) {
            if ($instrument->$field && file_exists($instrument->$field)) {
                rename($instrument->$field, "{$archivePath}/" . basename($instrument->$field));
                $instrument->$field = "{$archivePath}/" . basename($instrument->$field);
            }
        }

        $instrument->update(['is_archived' => true, 'archived_at' => now()]);

        return response()->json(['status' => 'ARCHIVED', 'archive_path' => $archivePath]);
    }

    public function retrieveArchived(Request $request, string $instrumentId): mixed
    {
        $instrument = Instrument::where('instrument_id', $instrumentId)
                                ->where('is_archived', true)
                                ->firstOrFail();
        return $this->retrieve($request, $instrumentId);
    }

    public function emailImage(Request $request, string $instrumentId): JsonResponse
    {
        $request->validate(['account_number' => 'required|string']);

        $instrument = Instrument::where('instrument_id', $instrumentId)->firstOrFail();
        $message    = "Please find the cheque image for cheque no. {$instrument->cheque_number} attached.";

        $sent = $this->notify->sendEmail(
            $request->account_number,
            $message,
            $instrument->image_path_grey
        );

        return response()->json(['status' => $sent ? 'SENT' : 'FAILED']);
    }

    public function purge(Request $request, string $instrumentId): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $instrument = Instrument::where('instrument_id', $instrumentId)->firstOrFail();

        // Only allow purge after archive period (10 years)
        $ageYears = $instrument->created_at->diffInYears(now());
        $maxYears = config('cts.image_storage.archive_years', 10);

        if ($ageYears < $maxYears) {
            return response()->json([
                'error'   => 'PURGE_NOT_ALLOWED',
                'message' => "Image must be retained for {$maxYears} years. Current age: {$ageYears} years.",
            ], 403);
        }

        foreach (['image_path_grey', 'image_path_bw', 'image_path_uv'] as $field) {
            if ($instrument->$field && file_exists($instrument->$field)) {
                unlink($instrument->$field);
            }
        }

        activity()->on($instrument)->withProperties(['reason' => $request->reason])->log('IMAGE_PURGED');
        $instrument->update(['image_path_grey' => null, 'image_path_bw' => null, 'image_path_uv' => null]);

        return response()->json(['status' => 'PURGED']);
    }
}
