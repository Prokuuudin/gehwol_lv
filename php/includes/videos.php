<?php
// Homepage videos. Older installations do not have videos.json yet, so the video that was
// previously hard-coded in the homepage template remains available until the admin first saves.

require_once __DIR__ . '/storage.php';

function default_videos(): array
{
    return [[
        'id' => 1,
        'title' => 'GEHWOL Academy vebinārs «Ready for Winter» — padomi un risinājumi pēdu kopšanai aukstajā sezonā (angļu valodā)',
        'video' => 'video/video_ready_for_winter.mp4',
        'poster' => 'img/video-poster.jpg',
        'sort_order' => 10,
        'published' => true,
        'created_at' => '2026-10-20 00:00:00',
        'updated_at' => '2026-10-20 00:00:00',
    ]];
}

/** Existing saved videos, or the original built-in video on installations not migrated yet. */
function load_videos(): array
{
    return is_file(storage_path('videos')) ? load_collection('videos') : default_videos();
}

/** Persist the built-in video before the admin starts changing the collection. */
function initialize_videos(): void
{
    if (is_file(storage_path('videos'))) {
        return;
    }
    with_collection_lock('videos', null, function (): void {
        if (!is_file(storage_path('videos'))) {
            write_collection('videos', default_videos(), null);
        }
    });
}

function is_safe_video_path(string $path): bool
{
    return (bool)preg_match('~^(video|uploads/videos)/[a-zA-Z0-9_.@/-]+\.mp4$~', $path)
        && !str_contains($path, '..');
}
