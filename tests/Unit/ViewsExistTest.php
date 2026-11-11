<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\View;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/** Every view a controller or class renders exists, so a page can never 500 with "View [...] not found". */
class ViewsExistTest extends TestCase
{
    public function test_every_view_named_in_app_code_exists(): void
    {
        $missing = [];
        foreach ((new Finder)->files()->in(app_path())->name('*.php') as $file) {
            preg_match_all("/\bview\(\s*'([a-z0-9_.\-]+)'/", $file->getContents(), $matches);
            foreach ($matches[1] as $name) {
                if (! View::exists($name)) {
                    $missing[] = $name.' (in '.$file->getRelativePathname().')';
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)), 'Views referenced in app/ but not found.');
    }
}
