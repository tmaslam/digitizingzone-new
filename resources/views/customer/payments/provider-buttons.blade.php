@if (! empty($paymentProviders))
    <div style="display:flex; gap:12px; flex-wrap:wrap;">
        @foreach ($paymentProviders as $provider)
            <button
                class="{{ $loop->first ? 'button' : 'button secondary' }}"
                type="submit"
                name="provider"
                value="{{ $provider['key'] }}"
            >
                {{ ($buttonPrefix ?? 'Continue With').' '.$provider['label'] }}
            </button>
        @endforeach
    </div>
@else
    <div class="content-note">
        <strong>Payment setup pending:</strong>
        No hosted payment provider is configured for this environment yet.
    </div>
@endif
