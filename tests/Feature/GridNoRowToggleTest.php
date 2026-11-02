<?php

namespace Tests\Feature;

use Tests\TestCase;

class GridNoRowToggleTest extends TestCase
{
    /** Aktif/Nonaktif hanya di bilah form; baris grid master cuma boleh punya ikon Edit (actions="edit"). */
    public function test_no_grid_draws_the_row_deactivate_button(): void
    {
        $views = glob(resource_path('views/*/index.blade.php'));
        $this->assertNotEmpty($views);
        foreach ($views as $v) {
            $this->assertStringNotContainsString('actions="master"', file_get_contents($v), basename(dirname($v)));
        }
    }
}
