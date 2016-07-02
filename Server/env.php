<?php

set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__)."/lib");

spl_autoload_register(function ($className) {
    include_once strtr(trim($className, "\\/"), "\\", "/").".php";
});

require_once dirname(__FILE__)."/config.php";
