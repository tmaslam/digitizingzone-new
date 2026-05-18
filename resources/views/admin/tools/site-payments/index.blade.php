@extends('layouts.admin')

@section('title', 'Site Payments')
@section('page_title', 'Site Payments')
@section('page_subtitle', 'Choose one active hosted payment provider per website. Customers will only see the selected provider for that site.')

@section('content')
    <section class="content-card stack">
        <div class="section-head">
            <div>
                <h3>Payment Provider By Site</h3>
                <p>Use one hosted provider at a time for each website to keep checkout simpler for customers and easier to operate internally.</p>
            </div>
        </div>

        @if (session('success'))
            <div class="alert success">{{ session('success') }}</div>
        @endif

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Site</th>
                        <th>Current Provider</th>
                        <th>Provider Readiness</th>
                        <th>Update</th>
                    </tr>
                </thead>
                <tbody>
                    @if (collect($sites)->isEmpty())
                        <tr>
                            <td colspan="4">No active sites are available yet.</td>
                        </tr>
                    @else
                    @foreach ($sites as $site)
                        @php
                            $siteContext = \App\Support\SiteResolver::fromLegacyKey((string) $site->legacy_key);
                            $currentProvider = trim((string) ($site->active_payment_provider ?: \App\Support\HostedPaymentProviders::defaultProvider($siteContext)));
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $site->brand_name ?: $site->name ?: $site->legacy_key }}</strong><br>
                                <span class="muted">{{ $site->legacy_key }}</span>
                            </td>
                            <td>{{ \App\Support\HostedPaymentProviders::label($currentProvider) }}</td>
                            <td>
                                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                    @foreach ($providers as $providerKey => $providerLabel)
                                        <span class="badge" style="{{ \App\Support\HostedPaymentProviders::isReady($providerKey) ? '' : 'opacity:.65;' }}">
                                            {{ $providerLabel }}: {{ \App\Support\HostedPaymentProviders::isReady($providerKey) ? 'Ready' : 'Missing Config' }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td>
                                <form method="post" action="{{ url('/v/site-payments/'.$site->id.'/edit') }}" style="display:flex; gap:10px; flex-wrap:wrap; align-items:end;">
                                    @csrf
                                    <label style="min-width:220px;">
                                        Provider
                                        <select name="active_payment_provider">
                                            @foreach ($providers as $providerKey => $providerLabel)
                                                <option value="{{ $providerKey }}" @selected($currentProvider === $providerKey)>{{ $providerLabel }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <button class="button" type="submit">Save</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </section>
@endsection
