<?php
// Categories and questions are archived through versioned, CSRF-protected forms.
require "conn.php";
require "includes/functions.php";
requireAdmin();
http_response_code(405);
exit(
    "Use Edit category to archive it. Deletion is disabled to preserve records."
);
