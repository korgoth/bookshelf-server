<?php

namespace Bookshelf\Core;

// PHP doesn't allow you to use expressions in the definition of const, therefore we have to use this
// see: http://stackoverflow.com/a/2787565

define('rootdir', __DIR__ . '/../../../../');
define('libdir', Constants::ROOT_DIR . 'lib');

class Constants {
    const ROOT_DIR = rootdir;
    const LIB_DIR = libdir;

    const VERSION_TEXT = '0.0.1a'; // TODO: decide on a proper versioning scheme
    const VERSION_CODE = 1;
}