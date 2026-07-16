<?php

namespace ChurchTools\DigitalCounseling\Contracts;

use Illuminate\Http\UploadedFile;

interface ProtectedMediaStorageContract
{
    /**
     * @return array{disk: string, path: string, mime: string|null, size_bytes: int|null}
     */
    public function storeVoiceNote(UploadedFile $file): array;

    /**
     * @return array{disk: string, path: string, mime: string|null, size_bytes: int|null, original_name: string|null}
     */
    public function storeAttachment(UploadedFile $file): array;
}
