<?php

namespace App\Contracts;

use App\Data\VideoInspectionResult;

interface VideoProbe
{
    public function inspect(string $absolutePath): VideoInspectionResult;
}
