<?php

namespace App\Services\Oidc;

use RuntimeException;

/** Messages are safe to show to the user; details stay in the previous exception. */
class OidcException extends RuntimeException {}
