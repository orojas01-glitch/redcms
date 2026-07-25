<?php
/**
 * Pure, provider-safe video URL recognition shared by administrator and public
 * rendering. Callers receive data only; supplied iframe/embed HTML is never
 * accepted or executed.
 */

if (!function_exists('red_video_url_scalar')) {
    function red_video_url_scalar($value)
    {
        return is_array($value) ? '' : trim((string) $value);
    }
}

if (!function_exists('red_video_url_query_values')) {
    function red_video_url_query_values($query, $name)
    {
        $values = [];
        foreach (explode('&', (string) $query) as $pair) {
            if ($pair === '') {
                continue;
            }
            $parts = explode('=', $pair, 2);
            if (urldecode($parts[0]) === $name) {
                $values[] = urldecode((string) ($parts[1] ?? ''));
            }
        }
        return $values;
    }
}

if (!function_exists('red_video_url_external_data')) {
    function red_video_url_external_data($url, $host)
    {
        return [
            'provider' => 'external',
            'id' => '',
            'canonical_url' => $url,
            'embed_url' => '',
            'privacy_embed_url' => '',
            'thumbnail_url' => '',
            'provider_label' => $host,
        ];
    }
}

if (!function_exists('red_video_url_empty_data')) {
    function red_video_url_empty_data()
    {
        return [
            'provider' => '',
            'id' => '',
            'canonical_url' => '',
            'embed_url' => '',
            'privacy_embed_url' => '',
            'thumbnail_url' => '',
            'provider_label' => '',
        ];
    }
}

if (!function_exists('red_video_url_data')) {
    function red_video_url_data($value)
    {
        $url = red_video_url_scalar($value);
        if ($url === ''
            || strlen($url) > 2048
            || preg_match('/[\x00-\x1F\x7F]/', $url) === 1
        ) {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return null;
        }

        $host = rtrim(strtolower((string) $parts['host']), '.');
        $path = (string) ($parts['path'] ?? '');
        $segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
        $youtubeHosts = [
            'youtube.com',
            'www.youtube.com',
            'm.youtube.com',
            'music.youtube.com',
            'youtube-nocookie.com',
            'www.youtube-nocookie.com',
            'youtu.be',
            'www.youtu.be',
        ];

        if (in_array($host, $youtubeHosts, true)) {
            $videoId = '';
            if ($host === 'youtu.be' || $host === 'www.youtu.be') {
                $videoId = (string) ($segments[0] ?? '');
            } elseif (($segments[0] ?? '') === 'watch') {
                $videoIds = red_video_url_query_values($parts['query'] ?? '', 'v');
                $videoId = count($videoIds) === 1 ? (string) $videoIds[0] : '';
            } elseif (in_array(($segments[0] ?? ''), ['embed', 'shorts', 'live'], true)) {
                $videoId = (string) ($segments[1] ?? '');
            }

            if (preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) !== 1) {
                return null;
            }

            return [
                'provider' => 'youtube',
                'id' => $videoId,
                'canonical_url' => 'https://www.youtube.com/watch?v='.$videoId,
                'embed_url' => 'https://www.youtube.com/embed/'.$videoId.'?wmode=transparent',
                'privacy_embed_url' => 'https://www.youtube-nocookie.com/embed/'.$videoId.'?rel=0&playsinline=1',
                'thumbnail_url' => 'https://i.ytimg.com/vi/'.$videoId.'/hqdefault.jpg',
                'provider_label' => 'YouTube',
            ];
        }

        if (in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
            $videoId = '';
            $videoIndex = null;
            $segmentHash = '';
            $queryHashes = red_video_url_query_values($parts['query'] ?? '', 'h');
            if (count($queryHashes) > 1) {
                return null;
            }
            if ($host === 'player.vimeo.com' && ($segments[0] ?? '') === 'video') {
                $videoId = (string) ($segments[1] ?? '');
                $videoIndex = 1;
            } else {
                for ($index = count($segments) - 1; $index >= 0; $index--) {
                    if (preg_match('/^[0-9]{1,12}$/', (string) $segments[$index]) === 1) {
                        $videoId = (string) $segments[$index];
                        $videoIndex = $index;
                        break;
                    }
                }
            }

            if (preg_match('/^[0-9]{1,12}$/', $videoId) !== 1) {
                return null;
            }

            if ($videoIndex !== null && isset($segments[$videoIndex + 1])) {
                $segmentHash = (string) $segments[$videoIndex + 1];
            }
            $queryHash = count($queryHashes) === 1 ? (string) $queryHashes[0] : '';
            if ($segmentHash !== '' && $queryHash !== '' && $segmentHash !== $queryHash) {
                return null;
            }
            $privacyHash = $queryHash !== '' ? $queryHash : $segmentHash;
            if ($privacyHash !== '' && preg_match('/^[A-Za-z0-9]{6,64}$/', $privacyHash) !== 1) {
                return null;
            }
            $canonicalUrl = 'https://vimeo.com/'.$videoId.($privacyHash !== '' ? '/'.$privacyHash : '');
            $embedUrl = 'https://player.vimeo.com/video/'.$videoId.
                ($privacyHash !== '' ? '?h='.rawurlencode($privacyHash) : '');

            return [
                'provider' => 'vimeo',
                'id' => $videoId,
                'canonical_url' => $canonicalUrl,
                'embed_url' => $embedUrl,
                'privacy_embed_url' => $embedUrl,
                'thumbnail_url' => '',
                'provider_label' => 'Vimeo',
            ];
        }

        if (preg_match('/(^|\.)(youtube\.com|youtube-nocookie\.com|youtu\.be|vimeo\.com)(\.|$)/', $host) === 1) {
            return null;
        }

        return red_video_url_external_data($url, $host);
    }
}

if (!function_exists('red_video_url_normalize')) {
    function red_video_url_normalize($value)
    {
        $data = red_video_url_data($value);
        return is_array($data) ? (string) $data['canonical_url'] : '';
    }
}
