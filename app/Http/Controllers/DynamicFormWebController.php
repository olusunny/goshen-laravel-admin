<?php

namespace App\Http\Controllers;

use App\Models\DynamicForm;
use App\Models\DynamicFormSubmission;
use App\Services\DynamicFormService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class DynamicFormWebController extends Controller
{
    public function __construct(private readonly DynamicFormService $forms) {}

    public function index(): View
    {
        $forms = DynamicForm::query()
            ->with('fields')
            ->open()
            ->where('visibility', DynamicForm::VISIBILITY_PUBLIC)
            ->orderByDesc('id')
            ->get();

        return view('dynamic-forms.index', [
            'forms' => $forms,
        ]);
    }

    public function show(string $form): View
    {
        $form = $this->publicFormFromKey($form);

        abort_unless($form && $form->isOpen(), 404);

        return view('dynamic-forms.show', [
            'form' => $form,
        ]);
    }

    public function store(Request $request, string $form): RedirectResponse
    {
        $form = $this->publicFormFromKey($form);

        abort_unless($form && $form->isOpen(), 404);

        try {
            $result = $this->forms->submit($form, $request, null, 'web_form');
        } catch (ValidationException $exception) {
            return back()
                ->withInput()
                ->withErrors($exception->errors());
        } catch (RuntimeException $exception) {
            return back()
                ->withInput()
                ->withErrors(['form' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['form' => 'This form could not be submitted right now. Please try again shortly.']);
        }

        /** @var DynamicFormSubmission $submission */
        $submission = $result['submission'];
        $checkoutUrl = trim((string) data_get($result, 'checkout.checkout_url', ''));

        if ($checkoutUrl !== '') {
            return redirect()->away($checkoutUrl);
        }

        return redirect()
            ->route('dynamic-forms.show', $form->slug)
            ->with('status', $form->thank_you_message ?: 'Thank you. Your form has been submitted.')
            ->with('submission_reference', $submission->reference);
    }

    private function publicFormFromKey(string $key): ?DynamicForm
    {
        $key = trim($key);
        if ($key === '') {
            return null;
        }

        return DynamicForm::query()
            ->with('fields')
            ->where('visibility', DynamicForm::VISIBILITY_PUBLIC)
            ->when(
                ctype_digit($key),
                fn ($query) => $query->whereKey((int) $key)->orWhere('slug', $key),
                fn ($query) => $query->where('slug', $key),
            )
            ->first();
    }
}
