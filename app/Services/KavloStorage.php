<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class KavloStorage
{
    public function publicDiskName(): string
    {
        return (string) config('cms.storage.public_disk', env('MEDIA_DISK', 'public'));
    }

    public function publicDisk(): Filesystem
    {
        return Storage::disk($this->publicDiskName());
    }

    public function publicUrl(string $path): string
    {
        return $this->publicDisk()->url($path);
    }

    public function publicRoot(): ?string
    {
        return config("filesystems.disks.{$this->publicDiskName()}.root");
    }
}
