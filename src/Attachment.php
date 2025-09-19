<?php

namespace GSMeira\LaravelAttachments;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class Attachment
{
    public function __construct(
        public ?string $name = null,
        public ?string $extname = null,
        public ?string $mimeType = null,
        public ?int $size = null,
        public ?string $url = null,
        public ?string $tempPath = null, // Temporary path from signed upload
        public bool $persisted = false, // Whether the file has been moved to final location
        protected ?string $disk = null,
        protected ?string $folder = null,
        protected bool $preComputeUrl = false,
        public bool $isLocal = false,
    ) {}

    /**
     * Computed property for file path based on folder, name, and extension
     */
    public function getPath(): ?string
    {
        if (!$this->name || !$this->extname) {
            return null;
        }

        $filename = $this->name;

        return $this->folder
            ? $this->folder . '/' . $filename
            : $filename;
    }

    /**
     * Alias for getPath() for backward compatibility
     */
    public function path(): ?string
    {
        return $this->getPath();
    }

    public static function fromUploadedFile(
        UploadedFile $file,
        ?string $disk = null,
        ?string $folder = null,
        bool $preComputeUrl = false
    ): self {
        return new self(
            name: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            extname: $file->getClientOriginalExtension(),
            mimeType: $file->getMimeType(),
            size: $file->getSize(),
            disk: $disk ?? config('filesystems.default', 'local'),
            folder: $folder ?? '',
            preComputeUrl: $preComputeUrl,
            isLocal: true,
        );
    }

    public static function fromMetadata(
        array $metadata,
        ?string $disk = null,
        ?string $folder = null,
        bool $preComputeUrl = false
    ): self {
        // For new metadata format, we assume the file needs to be moved from temp
        $attachment = new self(
            name: $metadata['name'],
            extname: $metadata['extname'],
            mimeType: $metadata['mimeType'],
            size: $metadata['size'],
            url: null, // Will be generated after move
            tempPath: $metadata['tempPath'] ?? null, // Temporary S3 path from signed upload
            disk: $disk ?? config('filesystems.default', 'local'),
            folder: $folder ?? '',
            preComputeUrl: $preComputeUrl,
            isLocal: false,

        );

        return $attachment;
    }

    public static function fromDb(?array $data): ?self
    {
        if (!$data) {
            return null;
        }

        $toReturn = new self(
            name: $data['name'] ?? null,
            extname: $data['extname'] ?? null,
            mimeType: $data['mimeType'] ?? null,
            size: $data['size'] ?? null,
            url: $data['url'] ?? null,
            tempPath: $data['tempPath'] ?? null,
            disk: $data['disk'] ?? null,
            folder: $data['folder'] ?? null,
            preComputeUrl: $data['preComputeUrl'] ?? false,
            isLocal: false,
            persisted: $data['persisted'] ?? false,
        );
        $toReturn->computeUrl();
        return $toReturn;
    }

    public function toDb(): ?array
    {
        if (!$this->getPath()) {
            return null;
        }

        return [
            'path' => $this->getPath(),
            'name' => $this->name,
            'extname' => $this->extname,
            'mimeType' => $this->mimeType,
            'size' => $this->size,
            'url' => $this->url(), // Include the URL in the stored data
            'disk' => $this->disk,
            'folder' => $this->folder,
            'preComputeUrl' => $this->preComputeUrl,
            'persisted' => $this->persisted,
            'tempPath' => $this->tempPath,
        ];
    }

    /**
     * Check if this attachment needs to be moved from temp to final location
     */
    public function needsMoving(): bool
    {
        return $this->persisted === false && $this->tempPath !== null;
    }

    /**
     * Move file from temporary location to final location with generated ID
     */
    public function moveFromTempToFinal(): void
    {
        if (!$this->needsMoving()) {
            return;
        }

        // Generate unique filename with ID
        $this->name = uniqid(); // Set the name property for computed path

        $fs = Storage::disk($this->disk);

        // Move from temp path to final path
        if (Storage::disk($this->disk)->exists($this->tempPath)) {
            $fs->move($this->tempPath, $this->getPath());
            $this->persisted = true; // Mark as persisted
            // Update the attachment properties
            $this->url = $this->url(); // Generate the final URL
            $this->tempPath = null; // Clear temp path
        }
    }

    public function save(): void
    {
        // For files uploaded via the new metadata workflow,
        // the file is already in S3 at the correct path
        // No additional processing needed
        if ($this->isLocal && $this->getPath()) {
            // Legacy support for UploadedFile workflow
            $finalPath = Storage::disk($this->disk)->putFileAs(
                $this->folder ?? '',
                new UploadedFile($this->getPath(), basename($this->getPath())),
                basename($this->getPath())
            );
            $this->persisted = true;

            $this->isLocal = false;
        }
    }

    public function delete(): void
    {
        if ($this->getPath()) {
            Storage::disk($this->disk)->delete($this->getPath());
        }
    }

    public function computeUrl(): void
    {
        $this->url = $this->url();
    }

    public function getDisk(): ?string
    {
        return $this->disk;
    }

    public function setDisk(?string $disk): void
    {
        $this->disk = $disk;
    }

    public function getFolder(): ?string
    {
        return $this->folder;
    }

    public function setFolder(?string $folder): void
    {
        $this->folder = $folder;
    }

    public function setPreComputeUrl(bool $preComputeUrl): void
    {
        $this->preComputeUrl = $preComputeUrl;
    }

    public function url(): ?string
    {
        if (!$this->getPath()) {
            return null;
        }

        $fs = Storage::disk($this->disk);
        try {
            if ($fs->getVisibility($this->getPath()) === 'private') {
                return $this->preComputeUrl
                    ? $fs->temporaryUrl($this->getPath(), now()->addMinutes(15))
                    : null;
            }

            return $fs->url($this->getPath());
        } catch (\Exception $e) {
            return $fs->url($this->getPath());
        }
    }
}
