<?php
// The old public board exposed answers. All play now uses the audience-safe API.
header("Location: index.php", true, 303);
exit();
