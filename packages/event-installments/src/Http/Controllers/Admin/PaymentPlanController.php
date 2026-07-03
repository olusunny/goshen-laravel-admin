<?php

namespace Personal\EventInstallments\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Personal\EventInstallments\Http\Requests\Admin\StorePaymentPlanRequest;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\PaymentPlan;

class PaymentPlanController extends Controller
{
    use AuthorizesRequests;

    public function store(StorePaymentPlanRequest $request, Event $event)
    {
        $data = $request->validated();
        $data['currency'] = strtoupper($data['currency']);
        $data['is_active'] = $request->boolean('is_active', true);

        $event->paymentPlans()->create($data);

        return back()->with('status', 'Payment plan added.');
    }

    public function destroy(Event $event, PaymentPlan $paymentPlan)
    {
        $this->authorize('update', $event);
        abort_unless($paymentPlan->event_id === $event->id, 404);
        $paymentPlan->delete();

        return back()->with('status', 'Payment plan removed.');
    }
}
