<?php

/*
 * BSD 3-Clause License
 *
 * Copyright (c) 2022, Ferry Cools (DigiLive)
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice, this
 *    list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its
 *    contributors may be used to endorse or promote products derived from
 *    this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */

// Instantiate composer's auto loader.

require __DIR__ . '/vendor/autoload.php';

// My options.
$release         = [
    'determined' => false,
    'version'    => 'v2.0.0',
    'date'       => '2022-10-02',
];
$logType         = 'MarkDown';
$gitProvider     = 'github';
$tagOrder        = 'desc';
$titleOrder      = 'asc';
$changelogLabels = ['Add', 'Cut', 'Fix', 'Bump', 'Optimize'];

// Default settings.
$changelogSettings = [
    'headTagName' => 'Next Release',
    'headTagDate' => 'Soon',
    'tagOrder'    => $tagOrder,
    'titleOrder'  => $titleOrder,
];

// Settings for determined release.
if ($release['determined']) {
    $changelogSettings['headTagName'] = $release['version'];
    $changelogSettings['headTagDate'] = $release['date'];
}

// Git provider settings.
$github = [
    'urls'     => [
        'commit'       => 'https://github.com/DigiLive/fileStreamer/commit/{commit}',
        'issue'        => 'https://github.com/DigiLive/fileStreamer/issue/{issue}',
        // No merge-request url because GitHub does not make a difference between issues and pull-requests.
        'mergeRequest' => null,
    ],
    'patterns' => [
        'issue'        => '#(\d+)',
        // No merge-request pattern because GitHub does not make a difference between issues and pull-requests.
        'mergeRequest' => null,
    ],
];

// Setup changelog.
$className = "DigiLive\\GitChangelog\\Renderers\\$logType";
$changelog = new $className();

$changelog->setUrl('commit', $$gitProvider['urls']['commit']);
$changelog->setUrl('issue', $$gitProvider['urls']['issue']);
$changelog->setUrl('mergeRequest', $$gitProvider['urls']['mergeRequest']);

$changelog->setPattern('issue', $$gitProvider['patterns']['issue']);
$changelog->setPattern('mergeRequest', $$gitProvider['patterns']['mergeRequest']);

$changelog->setLabels(...$changelogLabels);

$changelog->setOptions($changelogSettings);

//$changelog->setFromTag('v0.1.0');
//$changelog->setToTag('v0.1.0');

// Create changelog.
try {
    $start = microtime(true);
    $changelog->build();
    $fileName          = 'MarkDown' == $logType ? 'CHANGELOG.md' : 'CHANGELOG.html';
    $time_elapsed_secs = microtime(true) - $start;
    echo "Building Time: $time_elapsed_secs seconds\n";
    file_put_contents($fileName, $changelog->get());
} catch (Exception $e) {
    echo $e->getMessage();
}
