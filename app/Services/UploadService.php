<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;
use Illuminate\Http\UploadedFile;

/**
 * Safe image upload (maintenance photos, payment receipts).
 *  - Checks the upload error, size limit, REAL MIME type (finfo) and that it decodes as an image.
 *  - Ignores the client file name; stores under a random name with a whitelisted extension.
 *  - Files live in storage/app/private/uploads/<folder>/ (NOT inside public/), so the web server never
 *    serves them directly; they are delivered through FileController after an authorization check.
 */
final class UploadService
{
    public static function baseDir(): string
    {
        return storage_path('app/private/uploads');
    }

    /** @return string|null stored file name, or null when no file was sent */
    public static function image(?UploadedFile $file, string $folder): ?string
    {
        if ($file === null) {
            return null;
        }
        if (!$file->isValid()) {
            throw new InvalidArgumentException('The file upload failed. Please try again.');
        }
        if ($file->getSize() > (int) config('bhms.upload.max_bytes')) {
            throw new InvalidArgumentException('The image is too large (max 2 MB).');
        }

        $path    = $file->getRealPath();
        $mime    = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $allowed = config('bhms.upload.allowed');
        if (!isset($allowed[$mime]) || @getimagesize($path) === false) {
            throw new InvalidArgumentException('Only JPG, PNG or WEBP images are allowed.');
        }

        $dir = self::baseDir() . '/' . $folder;
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Upload folder is not writable.');
        }

        $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        $file->move($dir, $name);
        return $name;
    }

    public static function path(string $folder, string $name): ?string
    {
        // basename() blocks path traversal such as ../../.env
        $full = self::baseDir() . '/' . $folder . '/' . basename($name);
        return is_file($full) ? $full : null;
    }
}
