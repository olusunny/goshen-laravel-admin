<?php

namespace App\Enums;

enum GoshenExperienceVideoUploadStatus: string
{
    case Received = 'received';
    case Queued = 'queued';
    case Uploading = 'uploading';
    case AwaitingYoutubeQuota = 'awaiting_youtube_quota';
    case YoutubeProcessing = 'youtube_processing';
    case ReadyForReview = 'ready_for_review';
    case Failed = 'failed';
    case ReauthRequired = 'reauth_required';
    case Cancelled = 'cancelled';
}
