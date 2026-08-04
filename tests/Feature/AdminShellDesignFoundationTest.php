<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminShellDesignFoundationTest extends TestCase
{
    public function test_admin_shell_provides_opt_in_responsive_accessibility_foundations(): void
    {
        $shell = file_get_contents(resource_path('views/filament/admin-shell.blade.php'));

        $this->assertStringContainsString('--goshen-admin-space-4:', $shell);
        $this->assertStringContainsString('.goshen-admin-section', $shell);
        $this->assertStringContainsString('.goshen-admin-state', $shell);
        $this->assertStringContainsString('.goshen-admin-table-wrap', $shell);
        $this->assertStringContainsString(':focus-visible', $shell);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $shell);
        $this->assertStringNotContainsString('transition: all', $shell);
    }
}
