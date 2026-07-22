<?php
$sourceUrl = $_GET['url'] ?? '';
$sourceUrl = trim((string)$sourceUrl);

if ($sourceUrl === '' || !preg_match('#^https?://#i', $sourceUrl)) {
    http_response_code(400);
    exit('Invalid media URL.');
}

$headers = get_headers($sourceUrl, 1);
if ($headers === false) {
    http_response_code(404);
    exit('Media not found.');
}

$contentType = '';
if (is_array($headers) && isset($headers['Content-Type'])) {
    $contentType = is_array($headers['Content-Type']) ? $headers['Content-Type'][0] : $headers['Content-Type'];
} elseif (is_array($headers) && isset($headers[0])) {
    $contentType = $headers[0];
}

if ($contentType === '') {
    $contentType = 'application/octet-stream';
}

$size = null;
if (is_array($headers) && isset($headers['Content-Length'])) {
    $size = is_array($headers['Content-Length']) ? $headers['Content-Length'][0] : $headers['Content-Length'];
}

header('Content-Type: ' . $contentType);
header('Accept-Ranges: bytes');
if ($size !== null) {
    header('Content-Length: ' . $size);
}
header('Cache-Control: no-store');

readfile($sourceUrl);
