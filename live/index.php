<?php
// Give the audience a dedicated URL while reusing the compiled quiz application.
$page = file_get_contents(__DIR__.'/../play/index.html');
if ($page === false) {
    http_response_code(503);
    exit('The live screen is unavailable. Build the frontend first.');
}
echo str_replace('./assets/', '../play/assets/', $page);
