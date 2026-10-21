<?php

namespace Modules\Ecommerce\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class EcommerceMediaService
{
    public function upload(UploadedFile $file, string $folder = 'misc'): string
    {
        $base = public_path('uploads/ecommerce/' . trim($folder, '/'));
        if (! File::isDirectory($base)) {
            File::makeDirectory($base, 0755, true);
        }

        $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $name = ($name !== '' ? $name : 'file') . '-' . time() . '-' . Str::random(6)
            . '.' . strtolower($file->getClientOriginalExtension() ?: 'png');

        $file->move($base, $name);

        return 'uploads/ecommerce/' . trim($folder, '/') . '/' . $name;
    }

    public function delete(?string $relativePath): void
    {
        if (! $relativePath) {
            return;
        }

        $full = public_path(ltrim(str_replace('public/', '', $relativePath), '/'));
        if (File::isFile($full)) {
            File::delete($full);
        }
    }

    public function url(?string $relativePath, ?string $fallback = null): string
    {
        if ($relativePath) {
            return asset('public/' . ltrim(str_replace('public/', '', $relativePath), '/'));
        }

        return $fallback ?: asset('public/uploads/dummy_images/default-picture.png');
    }
}
