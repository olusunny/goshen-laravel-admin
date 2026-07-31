<?php

namespace App\Contracts\Addons;

interface AddonLifecycleHandler
{
    public function deactivate(): void;

    public function uninstall(): void;
}
