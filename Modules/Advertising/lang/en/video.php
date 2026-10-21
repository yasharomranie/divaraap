<?php

declare(strict_types=1);

return [
    'caption_language' => 'Caption file language',
    'format' => 'Optional video advertisement', 'clip' => 'Ready video',
    'hint' => 'Choose an active ready video from the existing module: valid public-site MP4 up to 20 MB, belonging to an active listing and owner. No new conversion or autoplay is added. Changed video content requires saving and review again. The banner image is the poster; the destination is the video’s existing listing.',
    'invalid' => 'Choose an existing active ready MP4 up to 20 MB with an active listing and owner.',
    'transcript' => 'Full video transcript and visual description',
    'transcript_hint' => 'Describe speech and important visual information in up to 2,000 characters. Add timed captions for spoken content as well.',
    'transcript_invalid' => 'A complete text alternative of 1–2,000 characters is required.',
    'captions' => 'Video captions', 'captions_hint' => 'Valid WebVTT beginning with WEBVTT, up to 128 KB. Caption timing and text must match the video.',
    'captions_invalid' => 'Choose an existing valid WebVTT caption file up to 128 KB.',
    'optional' => 'Playback is optional; the video loads and plays when you choose.',
    'read_transcript' => 'Read video transcript', 'destination' => 'View listing and chat',
    'unsupported' => 'Your browser cannot play this video; the transcript and listing link are available.',
    'error' => 'Video playback failed; the transcript and listing link remain available.',
];
