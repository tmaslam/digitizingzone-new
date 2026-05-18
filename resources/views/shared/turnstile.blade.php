@if (\App\Support\TurnstileVerifier::enabled())
    <div>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        <div class="cf-turnstile" data-sitekey="{{ \App\Support\TurnstileVerifier::siteKey() }}"></div>
        <small style="display:block;margin-top:8px;color:#65707d;line-height:1.6;">
            This quick verification helps protect the website from bots and abusive signup or login attempts.
        </small>
    </div>
@endif
