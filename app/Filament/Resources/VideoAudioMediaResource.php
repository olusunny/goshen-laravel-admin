<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VideoAudioMediaResource\Pages;

class VideoAudioMediaResource extends MediaItemResource
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-play-circle';

    protected static ?string $navigationLabel = 'Video & Audio';

    protected static ?string $modelLabel = 'video or audio item';

    protected static ?string $pluralModelLabel = 'Video & Audio';

    protected static ?int $navigationSort = 1;

    /**
     * Music is managed with audio because it uses the same playback and upload workflow.
     *
     * @return array<int, string>
     */
    protected static function manageableTypes(): array
    {
        return ['audio', 'music', 'video'];
    }

    /**
     * @return array<string, string>
     */
    protected static function mediaTypeOptions(): array
    {
        return [
            'audio' => 'Audio',
            'music' => 'Music',
            'video' => 'Video',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVideoAudioMedia::route('/'),
            'create' => Pages\CreateVideoAudioMedia::route('/create'),
            'edit' => Pages\EditVideoAudioMedia::route('/{record}/edit'),
        ];
    }
}
