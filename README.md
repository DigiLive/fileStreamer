# fileStreamer

[![GitHub release](https://img.shields.io/github/v/release/DigiLive/fileStreamer?include_prereleases)](https://github.com/DigiLive/gitChangelog/releases)
[![License](https://img.shields.io/badge/License-BSD%203--Clause-blue.svg)](https://opensource.org/licenses/BSD-3-Clause)

This library serves a file according to the headers which are sent with a http
request. It supports resumable downloads or streaming the content of a file to a
client.

If you have any questions, comments or ideas concerning this library, please
consult the code documentation at first.
Create a new [issue](https://github.com/DigiLive/fileStreamer/issues/new) if
your concerns remain unanswered.

## Features

* Inline disposition.
* Attachment disposition.
* Serve a complete file.
* Serve a single byte range of a file.
* Serve multiple byte ranges of a file.

## Requirements

* PHP ^7.4
* ext-fileinfo *

## Installation

The preferred method is to install the library
with [Composer](http://getcomposer.org).

```sh
> composer require digilive/file-streamer:^1
```

Set the version constraint to a value which suits you best.  
Alternatively you can download the latest release
from [GitHub](https://github.com/DigiLive/fileStreamer/releases).

## Example use

```php
<?php

use DigiLive\FileStreamer\FileStreamer;

// Use composer's auto loader.
$requiredFile = 'Path/To/vendor/autoload.php';

// Or include the library manually.
// $requiredFile = 'Path/To/FileStreamer.php';

require_once $requiredFile;

// Instantiate the library.
$fileStreamer = new FileStreamer('Path/To/File/To/Serve.ext');
// Set inline disposition if wished.
$fileStreamer->setInline();
$fileStreamer->start();
// Execution of PHP will terminate when FileStreamer::start() is finished.
```
