<?php

namespace App\Registry;

use RuntimeException;

/** A rule of the registry was not met. The message is safe to show to the user. */
class RegistryException extends RuntimeException {}
