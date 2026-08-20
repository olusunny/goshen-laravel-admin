<?php

namespace Tests\Feature;

use Tests\TestCase;

class MemberPortalRetreatMaterialsLayoutTest extends TestCase
{
    public function test_retreat_materials_keep_the_display_name_readable_on_mobile(): void
    {
        $portal = file_get_contents(resource_path('views/member/portal.blade.php'));
        $start = strpos($portal, 'function materialFileName(material)');
        $end = strpos($portal, 'function walletAmount', $start);

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $materials = substr($portal, $start, $end - $start);

        $this->assertStringContainsString('function materialTypeLabel(material)', $materials);
        $this->assertStringContainsString("return 'PDF document';", $materials);
        $this->assertStringContainsString("const details = [materialTypeLabel(material), materialFileSize(material)].filter(Boolean).join(' · ');", $materials);
        $this->assertStringNotContainsString('const details = [materialFileName(material)', $materials);
        $this->assertStringContainsString('class="retreat-material-title"', $materials);
        $this->assertStringContainsString('class="item-meta retreat-material-meta"', $materials);

        $this->assertStringContainsString('@media (max-width: 560px)', $portal);
        $this->assertStringContainsString('.retreat-material {', $portal);
        $this->assertStringContainsString('grid-template-columns: 46px minmax(0, 1fr);', $portal);
        $this->assertStringContainsString('.retreat-material-download {', $portal);
        $this->assertStringContainsString('grid-column: 2;', $portal);
        $this->assertStringContainsString('justify-self: start;', $portal);
    }
}
