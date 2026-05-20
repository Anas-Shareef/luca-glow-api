<?php

// Load Laravel bootstrap
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use Illuminate\Http\UploadedFile;

try {
    echo "1. Finding first product...\n";
    $product = Product::first();
    if (!$product) {
        throw new Exception("No products found in the database. Please seed the database first.");
    }
    echo "Product found: ID = {$product->id}, Name = {$product->name}\n\n";

    echo "2. Creating a mock image file...\n";
    // Create a temporary 100x100 PNG image
    $tempFile = tempnam(sys_get_temp_dir(), 'test_img') . '.png';
    $img = imagecreatetruecolor(100, 100);
    $color = imagecolorallocate($img, 255, 182, 193); // Pink
    imagefill($img, 0, 0, $color);
    imagepng($img, $tempFile);
    imagedestroy($img);

    echo "Mock image created at: {$tempFile} (Size: " . filesize($tempFile) . " bytes)\n\n";

    echo "3. Wrapping mock file in Laravel UploadedFile...\n";
    $file = new UploadedFile(
        $tempFile,
        'test_product_image.png',
        'image/png',
        null,
        true
    );

    echo "4. Attempting to add media to product...\n";
    $media = $product->addMedia($file)
        ->usingFileName(Illuminate\Support\Str::uuid() . '.webp')
        ->toMediaCollection('gallery');

    echo "\n🎉 SUCCESS! Media successfully added to the product!\n";
    echo "Media ID: {$media->id}\n";
    echo "Mime Type: {$media->mime_type}\n";
    echo "Size: {$media->size} bytes\n";
    echo "Disk: {$media->disk}\n";
    echo "URL: {$media->getUrl()}\n";

} catch (Throwable $e) {
    echo "\n❌ ERROR ENCOUNTERED:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
} finally {
    if (isset($tempFile) && file_exists($tempFile)) {
        unlink($tempFile);
    }
}
