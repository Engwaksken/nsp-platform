<?php

/*
| Venture Growth Index (M03). Scoring thresholds live in each rubric version
| (so results stay reproducible); this file only holds platform rules.
*/
return [
    // A rubric can only be approved when it uses exactly the ten owner dimensions
    // (App\Growth\OwnerDimensions) with their exact titles.
    'require_owner_dimensions' => (bool) env('NSP_VGI_REQUIRE_OWNER_DIMENSIONS', true),
];
