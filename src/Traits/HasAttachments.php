<?php

namespace GSMeira\LaravelAttachments\Traits;

use GSMeira\LaravelAttachments\Attachment;

trait HasAttachments
{
    protected array $attached = [];
    protected array $detached = [];

    public static function bootHasAttachments(): void
    {
        static::saving(function ($model) {
            foreach ($model->getCasts() as $field => $cast) {
                if (str_contains($cast, 'AttachmentCast')) {
                    // Get the original raw value from database (JSON string)
                    $originalRawValue = $model->getOriginal($field);
                    $existingFile = null;
                    
                    if ($originalRawValue) {
                        // If it's already an Attachment object, use it directly
                        if ($originalRawValue instanceof Attachment) {
                            $existingFile = $originalRawValue;
                        } else {
                            // If it's a JSON string, decode and create Attachment
                            $originalData = is_string($originalRawValue) 
                                ? json_decode($originalRawValue, true) 
                                : $originalRawValue;
                            $existingFile = $originalData ? Attachment::fromDb($originalData) : null;
                        }
                    }
                    
                    $newFile = $model->{$field};

                    // Skip when the attachment property hasn't been updated
                    if ($existingFile === $newFile) {
                        continue;
                    }

                    // There was an existing file, but there is no new file. 
                    // Hence we must remove the existing file.
                    if ($existingFile && !$newFile) {
                        $model->detached[] = $existingFile;
                        continue;
                    }

                    // If there is a new file and it needs to be moved from temp to final location
                    if ($newFile instanceof Attachment && $newFile->needsMoving()) {
                        $model->attached[] = $newFile;

                        // If there was an existing file, then we must get rid of it
                        if ($existingFile) {
                            $model->detached[] = $existingFile;
                        }

                        // Move the file from temp to final location with generated ID
                        $newFile->moveFromTempToFinal();
                    }
                }
            }
        });

        static::saved(function ($model) {
            foreach ($model->detached as $file) {
                $file->delete();
            }
            $model->detached = [];
            $model->attached = [];
        });

        static::deleted(function ($model) {
            foreach ($model->getCasts() as $field => $cast) {
                if (str_contains($cast, 'AttachmentCast') && $model->{$field}) {
                    $model->{$field}->delete();
                }
            }
        });
    }
}
