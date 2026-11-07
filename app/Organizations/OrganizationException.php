<?php

namespace App\Organizations;

use RuntimeException;

/** A rule was broken; the message is safe to show to the person who tried. */
class OrganizationException extends RuntimeException {}
