<?php

return [
    'enabled' => (bool) env('CMS_MEDIA_ENABLED', false),
    'video_enabled' => (bool) env('CMS_MEDIA_VIDEO_ENABLED', false),
    'quota_mb' => (int) env('CMS_MEDIA_QUOTA_MB', 2048),
    'ffmpeg' => env('CMS_FFMPEG_PATH', '/usr/bin/ffmpeg'),
    'ffprobe' => env('CMS_FFPROBE_PATH', '/usr/bin/ffprobe'),
];
