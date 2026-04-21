<?php

namespace App\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class DatePathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        $date = $media->created_at ?? now();

        return implode('/', [
            $date->format('Y'),
            $date->format('m'),
            $date->format('d'),
            $media->uuid,
        ]).'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media).'conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getPath($media).'responsive/';
    }
}
