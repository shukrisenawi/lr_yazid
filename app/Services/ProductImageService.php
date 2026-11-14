<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductImageService
{
    public function store(UploadedFile $file): string
    {
        $image = @imagecreatefromstring($file->getContent());
        if ($image === false) {
            throw ValidationException::withMessages(['images' => 'The uploaded image could not be processed.']);
        }
        $width = imagesx($image);
        $height = imagesy($image);
        $ratio = min(1, 1800 / max($width, $height));
        $output = imagecreatetruecolor(max(1, (int) ($width * $ratio)), max(1, (int) ($height * $ratio)));
        imagealphablending($output, false);
        imagesavealpha($output, true);
        imagecopyresampled($output, $image, 0, 0, 0, 0, imagesx($output), imagesy($output), $width, $height);
        ob_start();
        imagewebp($output, null, 85);
        $contents = ob_get_clean();
        imagedestroy($image);
        imagedestroy($output);
        $path = 'products/'.Str::uuid().'.webp';
        if ($contents === false || $contents === '' || ! Storage::disk('public')->put($path, $contents)) {
            throw ValidationException::withMessages(['images' => 'The image could not be stored. Please try again.']);
        }

        return $path;
    }
}
