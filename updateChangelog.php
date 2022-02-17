<?php

/**
 * Update Changelog
 *
 * A library for streaming a local file to a client.
 * Supports inline and attachment disposition, single file download and resumable, single/multiple range downloads.
 *
 * PHP version 7.4 or greater
 *
 * @package         DigiLive\FileStreamer
 * @author          Ferry Cools <info@DigiLive.nl>
 * @copyright   (c) 2022 Ferry Cools
 * @license         New BSD License http://www.opensource.org/licenses/bsd-license.php
 * @version         1.0.0
 * @link            https://github.com/DigiLive/fileStreamer
 */

use DigiLive\GitChangelog\Renderers\Html;
use DigiLive\GitChangelog\Renderers\MarkDown;

// Instantiate composer's auto loader.
require __DIR__ . '/vendor/autoload.php';

// Options for undetermined release
$changelogOptions = [
    'headTagName' => 'First Release',
    'headTagDate' => 'Soon',
    'titleOrder' => 'ASC',
];

// Options for determined release
/*$changelogOptions = [
    'headTagName' => 'v1.0.0',
    'headTagDate' => '2021-06-08',
    'titleOrder' => 'ASC',
];*/

$changelogLabels = ['Add', 'Cut', 'Fix', 'Bump', 'Optimize'];

// Setup markdown changelog.
$markDownLog            = new MarkDown();
$markDownLog->commitUrl = 'https://github.com/DigiLive/fileStreamer/commit/{hash}';
$markDownLog->issueUrl  = 'https://github.com/DigiLive/fileStreamer/issues/{issue}';
$markDownLog->setOptions($changelogOptions);
$markDownLog->setLabels(...$changelogLabels);
//$markDownLog->setToTag('v1.0.0');
$markDownLog->fetchTags(true);

// Setup html changelog.
/*$htmlLog = new Html();
$htmlLog->setOptions($changelogOptions);
$htmlLog->commitUrl = 'https://github.com/DigiLive/fileStreamer/commit/{hash}';
$htmlLog->issueUrl  = 'https://github.com/DigiLive/fileStreamer/issues/{issue}';
$htmlLog->setLabels(...$changelogLabels);
$htmlLog->setToTag('v1.0.0');
$htmlLog->fetchTags(true);*/

// Generate and save.
try {
    $markDownLog->build();
    $markDownLog->save('CHANGELOG.md');
/*    $htmlLog->build();
    $htmlLog->save('CHANGELOG.html');*/
} catch (Exception $e) {
    exit($e);
}
