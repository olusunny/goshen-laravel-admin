<?php

namespace ChurchTools\DigitalCounseling\Contracts;

use Illuminate\Http\UploadedFile;

interface ProtectedMediaStorageContract
{
    /**
     * @return array{disk: string, path: string, mime: string|null, size_bytes: int|null}
     */
    public function storeVoiceNote(UploadedFile $file): array;
}
