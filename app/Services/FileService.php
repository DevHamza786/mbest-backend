<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileService
{
    /**
     * Store uploaded file
     */
    public function storeFile(UploadedFile $file, string $directory = 'uploads', string $disk = 'public'): array
    {
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $fileName = Str::random(40) . '.' . $extension;
        $path = $file->storeAs($directory, $fileName, $disk);

        return [
            'file_path' => $path,
            'file_name' => $originalName,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'url' => Storage::disk($disk)->url($path),
        ];
    }

    /**
     * Store avatar file
     */
    public function storeAvatar(UploadedFile $file): string
    {
        $fileName = Str::random(40) . '.' . $file->getClientOriginalExtension();
        return $file->storeAs('avatars', $fileName, 'public');
    }

    /**
     * Store assignment submission file
     */
    public function storeAssignmentSubmission(UploadedFile $file, int $assignmentId, int $studentId): array
    {
        $directory = "assignments/submissions/{$assignmentId}/{$studentId}";
        return $this->storeFile($file, $directory);
    }

    /**
     * Store resource file
     */
    public function storeResource(UploadedFile $file): array
    {
        return $this->storeFile($file, 'resources');
    }

    /**
     * Store message attachment
     */
    public function storeMessageAttachment(UploadedFile $file, int $messageId): array
    {
        $directory = "messages/attachments/{$messageId}";
        return $this->storeFile($file, $directory);
    }

    /**
     * Delete file
     */
    public function deleteFile(string $path, string $disk = 'public'): bool
    {
        if (Storage::disk($disk)->exists($path)) {
            return Storage::disk($disk)->delete($path);
        }
        return false;
    }

    /**
     * Get file URL
     */
    public function getFileUrl(string $path, string $disk = 'public'): string
    {
        return Storage::disk($disk)->url($path);
    }

    /**
     * Validate file type
     */
    public function validateFileType(UploadedFile $file, array $allowedTypes): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());
        return in_array($extension, $allowedTypes);
    }

    /**
     * Validate file size (in KB)
     */
    public function validateFileSize(UploadedFile $file, int $maxSizeKB): bool
    {
        $fileSizeKB = $file->getSize() / 1024;
        return $fileSizeKB <= $maxSizeKB;
    }
}

