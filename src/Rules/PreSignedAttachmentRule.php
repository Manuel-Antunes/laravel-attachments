<?php

namespace GSMeira\LaravelAttachments\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;

class PreSignedAttachmentRule implements ValidationRule
{
    protected AwsS3V3Adapter $fs;

    public function __construct(?AwsS3V3Adapter $fs = null)
    {
        $this->fs = $fs ?: Storage::disk(config('filesystems.default'));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Handle legacy path-based validation
        if (
            is_array($value) &&
            array_key_exists('path', $value) &&
            $this->fs->has($value['path'])
        ) {
            return;
        }

        // Handle new metadata format validation
        if (
            is_array($value) &&
            array_key_exists('name', $value) &&
            array_key_exists('extname', $value) &&
            array_key_exists('mimeType', $value) &&
            array_key_exists('size', $value) &&
            array_key_exists('tempPath', $value) &&
            is_string($value['name']) &&
            is_string($value['extname']) &&
            is_string($value['mimeType']) &&
            is_int($value['size']) &&
            is_string($value['tempPath']) &&
            $value['size'] > 0 &&
            $this->fs->has($value['tempPath']) // Verify temp file exists
        ) {
            return;
        }

        $fail('laravel-attachments::validation.pre_signed_attachment')->translate();
    }
}
