@extends('layouts.customer-guest')

@section('title', $siteContext->displayLabel().' Sign Up')

@section('content')
    <div class="container guest-shell">
        <section class="panel form-panel auth-panel">
            <h2>Member Sign Up</h2>
            <p class="muted">Complete the signup form below to create your customer account.</p>

            <div class="info-note">
                <strong>Email verification required</strong><br>
                We will email your activation link after signup. If it does not appear in your inbox, check spam or junk.
            </div>

            @if ($errors->any())
                <div class="alert">{{ $errors->first() }}</div>
            @endif

            <form method="post" action="{{ url('/sign-up.php') }}" data-validate-form novalidate>
                @csrf
                <section class="form-section">
                    <div class="section-heading">
                        <h3>Your Details</h3>
                        <p>Start with the essentials so we can create and verify your account.</p>
                    </div>

                    <div class="grid">
                        <label class="form-field" data-form-field>
                            <span class="field-label">First Name <span class="field-meta required" aria-hidden="true">*</span></span>
                            <input type="text" name="first_name" value="{{ old('first_name') }}" autocomplete="given-name" required>
                            <span class="field-error" data-field-error aria-live="polite"></span>
                        </label>
                        <label class="form-field" data-form-field>
                            <span class="field-label">Last Name <span class="field-meta required" aria-hidden="true">*</span></span>
                            <input type="text" name="last_name" value="{{ old('last_name') }}" autocomplete="family-name" required>
                            <span class="field-error" data-field-error aria-live="polite"></span>
                        </label>
                        <label class="form-field" data-form-field style="grid-column: 1 / -1">
                            <span class="field-label">Country <span class="field-meta required" aria-hidden="true">*</span></span>
                            <input
                                type="search"
                                name="selCountry"
                                value="{{ old('selCountry', 'United States') }}"
                                placeholder="Start typing or choose from the full list"
                                autocomplete="country-name"
                                required
                                data-country-input
                                data-country-strict
                                data-country-options='@json($countries)'
                            >
                            <div class="country-results" data-country-results aria-label="Matching countries"></div>
                            <span class="field-help">Click the field to view the full country list, or start typing to narrow it down.</span>
                            <span class="field-error" data-field-error aria-live="polite"></span>
                        </label>
                        <label class="form-field" data-form-field>
                            <span class="field-label">Email Address <span class="field-meta required" aria-hidden="true">*</span></span>
                            <input type="email" name="useremail" value="{{ old('useremail') }}" autocomplete="email" required>
                            <span class="field-error" data-field-error aria-live="polite"></span>
                        </label>
                        <label class="form-field" data-form-field>
                            <span class="field-label">Confirm Email Address <span class="field-meta required" aria-hidden="true">*</span></span>
                            <input type="email" name="confirmuseremail" value="{{ old('confirmuseremail') }}" autocomplete="off" required data-match="useremail" data-match-message="The confirm email address must match the email address.">
                            <span class="field-error" data-field-error aria-live="polite"></span>
                        </label>
                        <label class="form-field" data-form-field>
                            <span class="field-label">Password <span class="field-meta required" aria-hidden="true">*</span></span>
                            <input type="password" name="user_psw" minlength="6" autocomplete="new-password" required>
                            <span class="field-help">Use at least 6 characters.</span>
                            <span class="field-error" data-field-error aria-live="polite"></span>
                        </label>
                        <label class="form-field" data-form-field>
                            <span class="field-label">Confirm Password <span class="field-meta required" aria-hidden="true">*</span></span>
                            <input type="password" name="confirm_psw" minlength="6" autocomplete="new-password" required data-match="user_psw" data-match-message="The confirm password must match the password.">
                            <span class="field-help" aria-hidden="true">&nbsp;</span>
                            <span class="field-error" data-field-error aria-live="polite"></span>
                        </label>
                        <label class="form-field" data-form-field>
                            <span class="field-label">Telephone <span class="field-meta required" aria-hidden="true">*</span></span>
                            <input type="text" name="telephone_num" value="{{ old('telephone_num') }}" autocomplete="tel" inputmode="tel" required>
                            <span class="field-error" data-field-error aria-live="polite"></span>
                        </label>
                        <input type="hidden" name="package_type" value="BASIC">
                    </div>
                </section>

                <section class="form-section">
                    <div class="section-heading">
                        <h3>How Would You Like To Start?</h3>
                        <p>Choose the signup path that works best for your account.</p>
                    </div>

                    <div class="form-field" data-form-field>
                        <div class="field-label">Signup Preference <span class="field-meta required" aria-hidden="true">*</span></div>
                        <div class="radio-group">
                            <div class="radio-option">
                                <input type="radio" name="term" value="ip" @checked(old('term', 'ip') === 'ip') required data-group-error="Please select a signup preference.">
                                <div>
                                    <strong>Secure welcome payment</strong><br>
                                    <span>After email verification, complete the $1 payment to activate your account and receive the new customer offer.</span>
                                </div>
                            </div>
                            <div class="radio-option">
                                <input type="radio" name="term" value="dc" @checked(old('term') === 'dc') required data-group-error="Please select a signup preference.">
                                <div>
                                    <strong>Manual account review</strong><br>
                                    <span>After email verification, your account will remain pending until it is approved by admin.</span>
                                </div>
                            </div>
                        </div>
                        <span class="field-error" data-field-error aria-live="polite"></span>
                    </div>
                </section>

                <section class="form-section">
                    <div class="section-heading">
                        <h3>Referral Details</h3>
                        <p>Help us understand how you found us.</p>
                    </div>

                    <div class="grid">
                        <label class="form-field" data-form-field>
                            <span class="field-label">How did you hear about us? <span class="field-meta required" aria-hidden="true">*</span></span>
                            <select name="refraloptions" required>
                                <option value="">Select One</option>
                                @foreach (['Google Search', 'Facebook', 'Instagram', 'Friend / Referral', 'Existing Customer', 'Others'] as $referralOption)
                                    <option value="{{ $referralOption }}" @selected(old('refraloptions') === $referralOption)>{{ $referralOption }}</option>
                                @endforeach
                            </select>
                            <span class="field-error" data-field-error aria-live="polite"></span>
                        </label>
                        <label class="form-field" data-form-field>
                            <span class="field-label">Referral Code or Details</span>
                            <input type="text" name="refralcode" value="{{ old('refralcode') }}" autocomplete="off">
                            <span class="field-error" data-field-error aria-live="polite"></span>
                        </label>
                    </div>
                </section>

                <section class="form-section">
                    <div class="section-heading">
                        <h3>Business Details</h3>
                        <p>Add your company information if you want it on your customer profile.</p>
                    </div>

                    <div class="grid">
                        <label class="form-field" data-form-field>
                            <span class="field-label">Company Name</span>
                            <input type="text" name="company_name" value="{{ old('company_name') }}" autocomplete="organization">
                            <span class="field-error" data-field-error aria-live="polite"></span>
                        </label>
                        <label class="form-field" data-form-field>
                            <span class="field-label">Company Type</span>
                            <select name="selCompanyTypes">
                                <option value="">Company Type</option>
                                @foreach ($companyTypes as $type)
                                    <option value="{{ $type }}" @selected(old('selCompanyTypes') === $type)>{{ $type }}</option>
                                @endforeach
                            </select>
                            <span class="field-error" data-field-error aria-live="polite"></span>
                        </label>
                    </div>

                    <label class="form-field" data-form-field>
                        <span class="field-label">Company Address</span>
                        <textarea name="company_address" autocomplete="street-address">{{ old('company_address') }}</textarea>
                        <span class="field-error" data-field-error aria-live="polite"></span>
                    </label>
                </section>

                <section class="form-section">
                    <label class="terms-row" data-form-field>
                        <input type="checkbox" name="terms" value="1" @checked(old('terms')) required>
                        <span class="terms-copy">
                            <span class="terms-line"><span class="field-meta required" aria-hidden="true">*</span><span>I have read the <a href="{{ url('/terms.php') }}" target="_blank" rel="noopener">Terms &amp; Conditions</a> thoroughly, and I agree.</span></span>
                            <span class="field-error" data-field-error aria-live="polite"></span>
                        </span>
                    </label>
                </section>

                @include('shared.turnstile')

                <div class="actions">
                    <button type="submit">Create Account</button>
                    <a class="button secondary" href="{{ url('/login.php') }}">Already Have An Account?</a>
                </div>
            </form>
        </section>
    </div>
@endsection
