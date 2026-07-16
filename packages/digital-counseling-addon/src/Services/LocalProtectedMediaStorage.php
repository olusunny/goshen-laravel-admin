<?php

namespace ChurchTools\DigitalCounseling\Services;

use ChurchTools\DigitalCounseling\Contracts\ProtectedMediaStorageContract;
use Illuminate\Http\UploadedFile;

class LocalProtectedMediaStorage implements ProtectedMediaStorageContract
{
    public function storeVoiceNote(UploadedFile $file): array
    {
        $disk = (string) config('counseling.media.disk', 'local');
        $path = $file->store((string) config('counseling.media.path', 'counseling/voice-notes'), $disk);

        return [
            'disk' => $disk,
            'path' => $path,
            'mime' => $file->getMimeType(),
            'size_bytes' => $file->getSize() ?: null,
        ];
    }

    public function storeAttachment(UploadedFile $file): array
    {
        $disk = (string) config('counseling.media.disk', 'local');
        $path = $file->store((string) config('counseling.media.attachment_path', 'counseling/attachments'), $disk);

        return [
            'disk' => $disk,
            'path' => $path,
            'mime' => $file->getMimeType(),
            'size_bytes' => $file->getSize() ?: null,
            'original_name' => $file->getClientOriginalName(),
        ];
    }
}
