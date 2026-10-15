<?php

declare(strict_types=1);

return ['root' => is_file(base_path('bootstrap/hosting-public.php')) ? base_path() : null];
