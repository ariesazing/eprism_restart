<?php

namespace App\Support\Html;

use HTMLPurifier_URIScheme_data;

/**
 * HTMLPurifier's built-in data: URI scheme hard-codes its allowed image types to
 * image/jpeg, image/gif, and image/png (see vendor/ezyang/htmlpurifier/library/
 * HTMLPurifier/URIScheme/data.php) — image/webp isn't in that list. Research submission
 * chapters now paste images in as WebP (see resources/js/document-editor/index.js's
 * capPastedImageSize()), so without this, HTMLPurifier would silently strip every one of
 * those <img> tags out of a chapter's content the moment it's autosaved. Register this in
 * place of the stock scheme (see SubmissionSectionService::sanitizeRichText()) to allow it
 * through the same base64-validation the other three types already get.
 */
class WebpAllowedDataUriScheme extends HTMLPurifier_URIScheme_data
{
    public $allowed_types = [
        'image/jpeg' => true,
        'image/gif' => true,
        'image/png' => true,
        'image/webp' => true,
    ];
}
