<?php

namespace Personal\EventInstallments\Policies;

use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Support\AuthorizesEventInstallments;

class EventPolicy
{
    use AuthorizesEventInstallments;

    public function viewAny($user): bool
    {
        return $this->managesEvent($user) || $this->managesFinance($user);
    }

    public function view($user, Event $event): bool
    {
        return $this->viewAny($user);
    }

    public function create($user): bool
    {
        return $this->managesEvent($user);
    }

    public function update($user, Event $event): bool
    {
        return $this->managesEvent($user, $event);
    }

    public function delete($user, Event $event): bool
    {
        return $this->managesEvent($user, $event);
    }
}
