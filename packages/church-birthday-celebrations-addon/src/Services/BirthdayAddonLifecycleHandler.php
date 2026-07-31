<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Services;

use App\Contracts\Addons\AddonLifecycleHandler;

class BirthdayAddonLifecycleHandler implements AddonLifecycleHandler
{
    public function deactivate(): void { app(BirthdayLifecycleService::class)->purgeAll(); }
    public function uninstall(): void { app(BirthdayLifecycleService::class)->purgeAll(); }
}
