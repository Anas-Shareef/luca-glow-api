<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    | 'public' for local development, 's3' for production (configure in .env)
    |--------------------------------------------------------------------------
    */
    'disk_name' => env('MEDIA_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Max File Size
    | 5MB limit for product images, 10MB for slider banners
    |--------------------------------------------------------------------------
    */
    'max_file_size' => 1024 * 1024 * 10,

    /*
    |--------------------------------------------------------------------------
    | Queue Conversions
    | Process WebP conversions in background queue for better UX
    |--------------------------------------------------------------------------
    */
    'queue_conversions_by_default' => env('QUEUE_MEDIA_CONVERSIONS', false),

    /*
    |--------------------------------------------------------------------------
    | Media Model
    |--------------------------------------------------------------------------
    */
    'media_model' => Spatie\MediaLibrary\MediaCollections\Models\Media::class,

    /*
    |--------------------------------------------------------------------------
    | Use Unique File Names
    |--------------------------------------------------------------------------
    */
    'file_namer' => Spatie\MediaLibrary\Support\FileNamer\DefaultFileNamer::class,

    /*
    |--------------------------------------------------------------------------
    | Path Generator
    | Organises uploads as: media/{model_type}/{model_id}/{conversion}/
    |--------------------------------------------------------------------------
    */
    'path_generator' => Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator::class,

    /*
    |--------------------------------------------------------------------------
    | URL Generator
    |--------------------------------------------------------------------------
    */
    'url_generator' => Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator::class,

    /*
    |--------------------------------------------------------------------------
    | Responsive Images
    | Generates multiple srcset sizes automatically
    |--------------------------------------------------------------------------
    */
    'responsive_images' => [
        'width_calculator' => Spatie\MediaLibrary\ResponsiveImages\WidthCalculator\FileSizeOptimizedWidthCalculator::class,
        'use_tiny_placeholders' => true,
        'tiny_placeholder_generator' => Spatie\MediaLibrary\ResponsiveImages\TinyPlaceholderGenerator\Blurred::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Optimizations (WebP)
    |--------------------------------------------------------------------------
    */
    'image_optimizers' => [
        Spatie\ImageOptimizer\Optimizers\Jpegoptim::class => [
            '-m85',
            '--strip-all',
            '--all-progressive',
        ],
        Spatie\ImageOptimizer\Optimizers\Pngquant::class => [
            '--force',
        ],
        Spatie\ImageOptimizer\Optimizers\Optipng::class => [
            '-i0',
            '-o2',
            '-quiet',
        ],
        Spatie\ImageOptimizer\Optimizers\Svgo::class => [
            '--disable=cleanupIDs',
        ],
        Spatie\ImageOptimizer\Optimizers\Cwebp::class => [
            '-m 6',
            '-pass 10',
            '-mt',
            '-q 90',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Temporary Upload Directory
    |--------------------------------------------------------------------------
    */
    'temporary_upload_directory' => null,

    'jobs' => [
        'perform_conversions'    => Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob::class,
        'generate_responsive_images' => Spatie\MediaLibrary\ResponsiveImages\Jobs\GenerateResponsiveImagesJob::class,
    ],

    'image_driver' => env('IMAGE_DRIVER', 'gd'),

    'ffmpeg_path'  => env('FFMPEG_PATH',  '/usr/bin/ffmpeg'),
    'ffprobe_path' => env('FFPROBE_PATH', '/usr/bin/ffprobe'),

    'version_urls' => false,

    'force_lazy_loading' => env('FORCE_LAZY_LOADING', false),

];
