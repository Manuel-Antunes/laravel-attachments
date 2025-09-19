<?php

namespace GSMeira\LaravelAttachments\DTO;

use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\AwsS3V3Adapter;

class AttachmentDTO
{
    public function __construct(
        public string $path,
        public string $name,
        public string $extname,
        public string $mimeType,
        public int $size,
        public string $disk = 's3',
        public bool $precomputeUrl = true
    ) {}

    public function url(): ?string
    {
        $fs = $this->fs();

        try {
            if ($fs->getVisibility($this->path) === 'private') {
                return $this->precomputeUrl
                    ? $fs->temporaryUrl($this->path, now()->addMinutes(15))
                    : null;
            }

            return $fs->url($this->path);
        } catch (\Exception $e) {
            return $fs->url($this->path);
        }
    }

    public function exists(): bool
    {
        return $this->fs()->exists($this->path);
    }

    public function delete(): void
    {
        $this->fs()->delete($this->path);
    }

    protected function fs(): FilesystemAdapter|AwsS3V3Adapter
    {
        return Storage::disk($this->disk);
    }
}
