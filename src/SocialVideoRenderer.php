<?php

namespace Misteryomi\SocialPublisher;

use Illuminate\Support\Facades\Log;

/**
 * Combines a JPEG card image with an audio file into an MP4 using FFmpeg.
 *
 * The output is a short static-image video: the card fills the frame for the
 * duration of the audio track. Suitable for Instagram Reels and TikTok when
 * uploaded via Buffer.
 *
 * Returns raw MP4 bytes on success, or null when FFmpeg is unavailable or fails.
 * Failures are logged and never throw — callers should fall back to image posting.
 */
class SocialVideoRenderer
{
    /**
     * Render an MP4 from JPEG image bytes and a local audio file.
     *
     * @param  string  $imageBytes  Raw JPEG bytes (e.g. from SocialCardRenderer::render()).
     * @param  string  $audioPath   Absolute path to the audio file (MP3, AAC, WAV).
     * @return string|null          Raw MP4 bytes, or null on failure.
     */
    public function render(string $imageBytes, string $audioPath): ?string
    {
        if (! $this->ffmpegAvailable()) {
            Log::warning('SocialVideoRenderer: ffmpeg not found — skipping video render');

            return null;
        }

        if (! is_file($audioPath)) {
            Log::warning('SocialVideoRenderer: audio file not found', ['path' => $audioPath]);

            return null;
        }

        $tmpImg = sys_get_temp_dir().'/'.uniqid('sj_card_', true).'.jpg';
        $tmpMp4 = sys_get_temp_dir().'/'.uniqid('sj_reel_', true).'.mp4';

        try {
            file_put_contents($tmpImg, $imageBytes);

            // -loop 1        treat the still image as a looping video stream
            // -shortest      stop encoding when the audio ends
            // -pix_fmt       yuv420p is required for broad platform compatibility
            // -movflags      faststart shifts the index to the front for faster Buffer upload
            $cmd = sprintf(
                'ffmpeg -y -loop 1 -i %s -i %s -shortest -c:v libx264 -c:a aac -pix_fmt yuv420p -movflags +faststart %s 2>&1',
                escapeshellarg($tmpImg),
                escapeshellarg($audioPath),
                escapeshellarg($tmpMp4),
            );

            exec($cmd, $output, $exitCode);

            if ($exitCode !== 0 || ! is_file($tmpMp4) || filesize($tmpMp4) === 0) {
                Log::warning('SocialVideoRenderer: ffmpeg failed', [
                    'exit_code' => $exitCode,
                    'output'    => implode("\n", array_slice($output, -10)),
                ]);

                return null;
            }

            return file_get_contents($tmpMp4);
        } finally {
            @unlink($tmpImg);
            @unlink($tmpMp4);
        }
    }

    private function ffmpegAvailable(): bool
    {
        exec('ffmpeg -version 2>&1', $_, $code);

        return $code === 0;
    }
}
