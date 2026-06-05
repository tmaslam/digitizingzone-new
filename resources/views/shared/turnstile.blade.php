@if (\App\Support\TurnstileVerifier::enabled())
    <div>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        <div class="cf-turnstile" data-sitekey="{{ \App\Support\TurnstileVerifier::siteKey() }}" data-callback="turnstileCallback" data-error-callback="turnstileErrorCallback"></div>
        <input type="hidden" name="cf-turnstile-response" id="cf-turnstile-response" value="">
        <small style="display:block;margin-top:8px;color:#65707d;line-height:1.6;">
            This quick verification helps protect the website from bots and abusive signup or login attempts.
        </small>
        <script>
            (function () {
                var tokenInput = document.getElementById('cf-turnstile-response');
                var turnstileReady = false;

                window.turnstileCallback = function (token) {
                    if (tokenInput) {
                        tokenInput.value = token;
                    }
                    turnstileReady = true;
                };

                window.turnstileErrorCallback = function () {
                    if (tokenInput) {
                        tokenInput.value = '';
                    }
                    turnstileReady = false;
                };

                document.addEventListener('DOMContentLoaded', function () {
                    var widgetDiv = document.querySelector('.cf-turnstile');
                    if (!widgetDiv) return;

                    var form = widgetDiv.closest('form');
                    if (!form) return;

                    form.addEventListener('submit', function (e) {
                        var token = tokenInput ? tokenInput.value.trim() : '';
                        if (!turnstileReady || token === '') {
                            e.preventDefault();
                            alert('Please wait for the security verification to complete before signing in.');
                            return false;
                        }
                    });
                });
            })();
        </script>
    </div>
@endif
