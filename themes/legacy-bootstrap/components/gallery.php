<?php
/**
 * Legacy Bootstrap public Gallery component view.
 *
 * Core supplies exact layout dimensions, ordered RED_C_Gallery records, and
 * prepared Gallery, Video, Banner, link, and target inputs. This compatibility
 * view adds the shared stack/carousel Gallery presentation while preserving
 * the Video and Banner branches and performing no database, request, session,
 * media mutation, or component dispatch.
 */
$context = red_legacy_public_gallery_context_validate($redThemeGalleryContext ?? null);
$dimensions = $context['dimensions'];
$width = $dimensions['Width'];
$vWidth = $dimensions['vWidth'];
$vHeight = $dimensions['vHeight'];
$galleryIndex = 0;

foreach ($context['rows'] as $preparedRow) {
    $row = $preparedRow['record'];
    $galleryType = $row['GalleryType'];
    $target = $preparedRow['link']['target'];

    switch ($galleryType) {
        case 'Gallery':
            $galleryIndex++;
            $photos = $preparedRow['gallery']['photos'];
            if (!$photos) {
                break;
            }

            $presentation = $preparedRow['gallery']['presentation'];
            $isCarousel = $presentation === 'carousel';
            $width = $isCarousel ? $dimensions['Width'] : $preparedRow['gallery']['width'];
            $recordId = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $row['RecordID']);
            if (!is_string($recordId) || $recordId === '') {
                $recordId = 'gallery';
            }
            $galleryId = 'red-public-gallery-' . $recordId . '-' . $galleryIndex;
            $trackId = $galleryId . '-track';
            $instructionsId = $galleryId . '-instructions';
            $galleryTitle = red_public_plain_text($row['Title']);
            $galleryLabel = $galleryTitle !== '' ? $galleryTitle : 'Photo gallery';
            $galleryClasses = 'red-public-gallery red-public-gallery--' . $presentation;
            $galleryAttributes = ' class="' . $galleryClasses . '" aria-label="' .
                red_public_html($galleryLabel) . '" data-red-gallery="' . $presentation . '"';
            if ($isCarousel) {
                $galleryAttributes .= ' data-red-gallery-carousel aria-roledescription="carousel"';
                if (count($photos) > 1) {
                    $galleryAttributes .= ' tabindex="0" aria-describedby="' . $instructionsId . '"';
                }
            }

            echo '<section' . $galleryAttributes . '>';
            if ($isCarousel && count($photos) > 1) {
                echo '<p class="red-public-gallery__sr-only" id="' . $instructionsId . '">' .
                    'Use the previous and next buttons or the left and right arrow keys to change photos.</p>';
            }
            $itemsTag = $isCarousel ? 'div' : 'ul';
            $itemTag = $isCarousel ? 'div' : 'li';
            echo '<' . $itemsTag . ' class="red-public-gallery__items" id="' . $trackId . '"' .
                ($isCarousel ? '' : ' role="list"') . '>';

            $photoCount = count($photos);
            foreach ($photos as $photoIndex => $photo) {
                $caption = red_public_plain_text($photo['title']);
                $photoUrl = (string) ($photo['url'] ?? '');
                $slideNumber = $photoIndex + 1;
                $slideLabel = 'Photo ' . $slideNumber . ' of ' . $photoCount;
                if ($caption !== '') {
                    $slideLabel .= ': ' . $caption;
                }
                $imageAlt = $caption !== ''
                    ? $caption
                    : $galleryLabel . ', photo ' . $slideNumber . ' of ' . $photoCount;
                $itemAttributes = ' class="red-public-gallery__item"';
                if ($isCarousel) {
                    $itemAttributes .= ' data-red-gallery-slide role="group" aria-roledescription="slide"' .
                        ' aria-label="' . red_public_html($slideLabel) . '"';
                }

                echo '<' . $itemTag . $itemAttributes . '><figure class="red-public-gallery__figure">';
                if ($photoUrl !== '') {
                    echo '<a class="red-public-gallery__link" href="' . red_public_html($photoUrl) . '">';
                }
                echo '<img class="red-gallery-image red-public-gallery__image" src="/images/resize.php?w=' .
                    red_public_html($width) . '&amp;img=/images/gallery/' . red_public_html($photo['file']) . '" alt="' .
                    red_public_html($imageAlt) . '" loading="lazy" decoding="async">';
                if ($photoUrl !== '') {
                    echo '</a>';
                }
                if ($caption !== '') {
                    echo '<figcaption class="red-public-gallery__caption" aria-hidden="true">' .
                        red_public_display_text($photo['title']) . '</figcaption>';
                }
                echo '</figure></' . $itemTag . '>';
            }
            echo '</' . $itemsTag . '>';

            if ($isCarousel && $photoCount > 1) {
                echo '<div class="red-public-gallery__controls" data-red-gallery-controls>';
                echo '<button class="red-public-gallery__button red-public-gallery__button--previous" type="button" ' .
                    'data-red-gallery-previous aria-controls="' . $trackId . '" aria-label="Previous photo">' .
                    '<span aria-hidden="true">&#8592;</span></button>';
                echo '<div class="red-public-gallery__dots" role="group" aria-label="Choose a photo">';
                foreach ($photos as $photoIndex => $photo) {
                    $slideNumber = $photoIndex + 1;
                    echo '<button class="red-public-gallery__dot" type="button" data-red-gallery-dot="' .
                        $photoIndex . '" aria-controls="' . $trackId . '" aria-label="Show photo ' .
                        $slideNumber . ' of ' . $photoCount . '"' . ($photoIndex === 0 ? ' aria-current="true"' : '') .
                        '><span aria-hidden="true"></span></button>';
                }
                echo '</div>';
                echo '<button class="red-public-gallery__button red-public-gallery__button--next" type="button" ' .
                    'data-red-gallery-next aria-controls="' . $trackId . '" aria-label="Next photo">' .
                    '<span aria-hidden="true">&#8594;</span></button>';
                echo '</div>';
                echo '<p class="red-public-gallery__sr-only" data-red-gallery-status aria-live="polite" ' .
                    'aria-atomic="true">Photo 1 of ' . $photoCount . '</p>';
            }
            echo '</section>';
            break;

        case 'Video':
            if ($row['Title'] <> '') {
                echo '<h3>' . red_public_display_text($row['Title']) . '</h3>';
            }

            $video = $preparedRow['video'];
            $videoTitle = red_public_plain_text($row['Title']);
            $iframeTitle = $videoTitle !== '' ? $videoTitle : 'Embedded video';
            switch ($video['provider']) {
                case 'vimeo':
                    $player = '<iframe src="' . red_public_html($video['embed_url']) . '" width="' . $vWidth .
                        '" height="' . $vHeight . '" title="' . red_public_html($iframeTitle) .
                        '" loading="lazy" frameborder="0" allow="fullscreen; picture-in-picture" allowfullscreen></iframe>';
                    echo '<div class="js-video vimeo">';
                    echo $player;
                    echo '</div>';
                    break;

                case 'youtube':
                    $player = '<iframe width="' . $vWidth . '" height="' . $vHeight . '" src="' .
                        red_public_html($video['embed_url']) . '" title="' . red_public_html($iframeTitle) .
                        '" loading="lazy" frameborder="0" allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>';
                    echo '<div class="js-video widescreen">';
                    echo $player;
                    echo '</div>';
                    break;

                case 'external':
                    echo '<p class="red-public-video-external"><a class="link-1" href="' .
                        red_public_html($video['canonical_url']) . '" target="_blank" rel="noopener noreferrer">' .
                        'Watch video on ' . red_public_html($video['provider_label']) . '</a></p>';
                    break;
            }

            if ($row['ShortDesc'] != '') {
                echo $row['ShortDesc'];
            }

            if ($preparedRow['link']['href'] !== '') {
                $link = 'href="' . red_public_html($preparedRow['link']['href']) . '" target="' . red_public_html($target) . '"';
                $rel = $target === '_blank' ? ' rel="noopener noreferrer"' : '';
                echo '<a ' . $link . $rel . ' class="link-1">Read More</a><div class="clear-1"></div>';
            }
            break;

        case 'Banner':
            if ($row['Link'] <> '') {
                $link = 'href="' . red_public_html($preparedRow['link']['href']) . '" target="' . red_public_html($target) . '"';
                echo '<figure class="img-indent"><a ' . $link . ' title=""><img class="red-gallery-image" src="/images/gallery/' . red_public_html($preparedRow['banner']['image']) . '" alt=""></a></figure>';
            } else {
                echo '<figure class="img-indent"><img class="red-gallery-image" src="/images/gallery/' . red_public_html($preparedRow['banner']['image']) . '" alt=""></figure>';
            }
            break;
    }
}
