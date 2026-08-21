<?php

namespace App\Services\YouTube;

final readonly class YouTubeChannel
{
    public function __construct(
        public string $id,
        public string $title,
    ) {}
}
