<?php

namespace App\Http\Controllers;

use App\Services\MaintenanceService;
use App\Services\PaymentService;
use App\Services\UploadService;
use Illuminate\Http\Request;

/**
 * Authorized file delivery:  /file?type=maintenance&id=5   |   /file?type=receipt&id=12
 * Uploaded images are stored outside public/, so this checks the user may see the record first.
 */
class FileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->asArray();
        $id   = (int) $request->query('id', 0);
        $type = (string) $request->query('type', '');
        $file = null;

        if ($type === 'maintenance') {
            $ticket = MaintenanceService::find($id);
            if ($ticket && $ticket['photo'] && MaintenanceService::canView($user, $ticket)) {
                $file = UploadService::path('maintenance', $ticket['photo']);
            }
        } elseif ($type === 'receipt') {
            $row = PaymentService::receipt($id, $user);      // enforces tenant ownership / blocks staff
            if ($row && $row['receipt_image']) {
                $file = UploadService::path('receipts', $row['receipt_image']);
            }
        }

        abort_if($file === null, 404, 'File not found.');

        return response()->file($file, [
            'Content-Type'        => (new \finfo(FILEINFO_MIME_TYPE))->file($file),
            'Content-Disposition' => 'inline; filename="upload"',
            'Cache-Control'       => 'private, max-age=3600',
        ]);
    }
}
