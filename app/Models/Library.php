<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Library extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['name'];

    public static function singleton(): self
    {
        return static::firstOrCreate(['id' => 1], ['name' => 'Global Library']);
    }
}
