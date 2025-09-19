<?php

namespace GSMeira\LaravelAttachments\Casts;

use GSMeira\LaravelAttachments\Attachment;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Http\UploadedFile;

class AttachmentCast implements CastsAttributes
{
    public function __construct(
        protected string $disk = 'local',
        protected string $folder = '',
        protected bool $preComputeUrl = false
    ) {}

    public function get($model, string $key, $value, array $attributes): ?Attachment
    {
        $data = $value ? json_decode($value, true) : null;
        $meta = [
            'disk' => $this->disk,
            'folder' => $this->folder,
            'preComputeUrl' => $this->preComputeUrl,
        ];
        return $data
            ? Attachment::fromDb(array_merge($data, $meta))
            : null;
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value instanceof UploadedFile) {
            $attachment = Attachment::fromUploadedFile(
                $value,
                $this->disk,
                $this->folder,
                $this->preComputeUrl
            );
        } elseif ($value instanceof Attachment) {
            $attachment = $value;
        } elseif (is_array($value)) {
            // Handle new metadata format from frontend
            if (isset($value['name'], $value['extname'], $value['mimeType'], $value['size'])) {
                $attachment = Attachment::fromMetadata(
                    $value,
                    $this->disk,
                    $this->folder,
                    $this->preComputeUrl
                );
            } else {
                // Handle legacy database format
                $attachment = Attachment::fromDb($value);
            }
        } else {
            return null;
        }

        return json_encode($attachment->toDb());
    }
}
