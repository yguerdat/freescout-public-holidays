@extends('layouts.app')

@section('title_full', __('Public Holidays') . ' - ' . __('Settings'))

@section('body_attrs')@parent data-page="publicholidays-settings"@endsection

@section('sidebar')
    @include('partials/sidebar_menu_toggle')
    <div class="sidebar-title">{{ __('Manage') }}</div>
    <ul class="sidebar-menu">
        <li class="active"><a href="{{ route('publicholidays.settings') }}"><i class="glyphicon glyphicon-calendar"></i> {{ __('Public Holidays') }}</a></li>
    </ul>
@endsection

@section('content')
<div class="section-heading">{{ __('Public Holidays') }}</div>

<div class="col-xs-12"
     id="publicholidays-settings-page"
     data-generate-url="{{ route('publicholidays.generate') }}"
     data-store-url="{{ route('publicholidays.store_holiday') }}"
     data-delete-url="{{ route('publicholidays.delete_holiday') }}"
     data-working-text="{{ __('Working...') }}"
     data-confirm-delete="{{ __('Remove this holiday?') }}">

    {{-- Today status --}}
    @php $st = $todayStatus; @endphp
    <div class="alert {{ $st['office_open'] ? 'alert-success' : 'alert-warning' }}">
        <strong>{{ __('Today') }} ({{ \Carbon\Carbon::parse($st['date'])->format('d.m.Y') }}):</strong>
        @if($st['office_open'])
            {{ __('Office open.') }}
        @else
            @if($st['holiday'])
                {{ __('Closed — public holiday:') }} <strong>{{ $st['holiday']['name'] }}</strong>.
            @else
                {{ __('Closed (weekend).') }}
            @endif
            {{ __('Next working day:') }} <strong>{{ \Carbon\Carbon::parse($st['next_working_day'])->format('d.m.Y') }}</strong>.
        @endif
    </div>

    <form method="POST" action="{{ route('publicholidays.settings') }}" class="form-horizontal margin-top">
        {{ csrf_field() }}
        <input type="hidden" name="year" value="{{ $year }}">

        {{-- Cantons --}}
        <div class="form-group">
            <label class="col-sm-3 control-label">{{ __('Observed cantons') }}</label>
            <div class="col-sm-7">
                <div class="row">
                    @foreach($allCantons as $code => $label)
                        <div class="col-sm-6">
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="cantons[]" value="{{ $code }}"
                                        {{ in_array($code, $cantons) ? 'checked' : '' }}>
                                    {{ $label }} <small class="text-muted">({{ $code }})</small>
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="help-block">{{ __('The canton(s) your office observes. This drives the "we are closed today" logic. Default: Jura (CH-JU).') }}</p>
            </div>
        </div>

        {{-- Weekends --}}
        <div class="form-group">
            <label class="col-sm-3 control-label">{{ __('Weekends') }}</label>
            <div class="col-sm-7">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="weekends_closed" value="1" {{ $service->weekendsClosed() ? 'checked' : '' }}>
                        {{ __('Treat Saturdays and Sundays as closed (for "next working day").') }}
                    </label>
                </div>
            </div>
        </div>

        {{-- Subject prefix --}}
        <div class="form-group">
            <label class="col-sm-3 control-label">{{ __('Auto-reply subject') }}</label>
            <div class="col-sm-7">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="subject_prefix_enabled" value="1" {{ \Option::get('publicholidays.subject_prefix_enabled', false) ? 'checked' : '' }}>
                        {{ __('Prefix the auto-reply subject on holidays') }}
                    </label>
                </div>
                <input type="text" class="form-control" name="subject_prefix"
                    value="{{ \Option::get('publicholidays.subject_prefix', '[Fermé aujourd\'hui]') }}"
                    placeholder="[Fermé aujourd'hui]">
            </div>
        </div>

        {{-- Notice templates --}}
        <div class="form-group">
            <label class="col-sm-3 control-label">{{ __('Holiday notice') }}</label>
            <div class="col-sm-9">
                <p class="help-block">
                    {{ __('HTML shown via the :var variable on holidays. Placeholders:', ['var' => '{%holiday.notice%}']) }}
                    <code>{holiday}</code>, <code>{date}</code>, <code>{next_working_day}</code>.
                </p>
                @foreach(['fr' => 'Français', 'en' => 'English', 'de' => 'Deutsch'] as $loc => $locLabel)
                    <label>{{ $locLabel }}</label>
                    <textarea class="form-control" name="notice_template_{{ $loc }}" rows="3" style="font-family:monospace;font-size:12px;">{{ \Option::get('publicholidays.notice_template_' . $loc, \Modules\PublicHolidays\Services\HolidayService::defaultNoticeTemplate($loc)) }}</textarea>
                @endforeach
            </div>
        </div>

        {{-- API token --}}
        <div class="form-group">
            <label class="col-sm-3 control-label">{{ __('API token (optional)') }}</label>
            <div class="col-sm-7">
                <input type="text" class="form-control" name="api_token"
                    value="{{ \Option::get('publicholidays.api_token', '') }}"
                    placeholder="{{ __('Leave empty to use the FreeScout API key') }}">
                <p class="help-block">{{ __('The REST API accepts the FreeScout "API & Webhooks" key. Optionally set a dedicated token here as well.') }}</p>
            </div>
        </div>

        <div class="form-group margin-top">
            <div class="col-sm-7 col-sm-offset-3">
                <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
            </div>
        </div>
    </form>

    <hr>

    {{-- Holidays for a year --}}
    <div class="form-horizontal">
        <div class="form-group">
            <label class="col-sm-3 control-label">{{ __('Holidays') }}</label>
            <div class="col-sm-9">
                <div class="form-inline" style="margin-bottom:12px;">
                    <label>{{ __('Year') }}:</label>
                    <select id="ph-year" class="form-control input-sm" style="width:auto;">
                        @for($y = (int) date('Y') - 1; $y <= (int) date('Y') + 3; $y++)
                            <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                    <button type="button" class="btn btn-warning btn-sm" id="ph-generate-btn">
                        <i class="glyphicon glyphicon-refresh"></i> {{ __('Generate / refresh') }}
                    </button>
                    <span id="ph-generate-result" class="margin-left"></span>
                </div>

                <table class="table table-striped table-condensed">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Canton') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($holidays as $h)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($h->date)->format('d.m.Y') }} <small class="text-muted">{{ \Carbon\Carbon::parse($h->date)->locale(app()->getLocale())->isoFormat('ddd') }}</small></td>
                            <td>{{ $h->localizedName() }}</td>
                            <td>{{ $h->canton ?: '—' }}</td>
                            <td>
                                @if($h->type === 'national')
                                    <span class="label label-default">{{ __('National') }}</span>
                                @elseif($h->type === 'custom')
                                    <span class="label label-info">{{ __('Custom') }}</span>
                                @else
                                    <span class="label label-primary">{{ __('Cantonal') }}</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <button type="button" class="btn btn-link btn-xs text-danger ph-delete" data-id="{{ $h->id }}">
                                    <i class="glyphicon glyphicon-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-muted">{{ __('No holidays for this year yet. Click "Generate / refresh".') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>

                {{-- Add custom holiday --}}
                <div class="well well-sm">
                    <div class="form-inline">
                        <strong>{{ __('Add a custom day') }}:</strong>
                        <input type="date" id="ph-custom-date" class="form-control input-sm">
                        <input type="text" id="ph-custom-name" class="form-control input-sm" placeholder="{{ __('Name') }}">
                        <select id="ph-custom-canton" class="form-control input-sm" style="width:auto;">
                            <option value="">{{ __('All / global') }}</option>
                            @foreach($allCantons as $code => $label)
                                <option value="{{ $code }}">{{ $code }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-default btn-sm" id="ph-add-btn">{{ __('Add') }}</button>
                        <span id="ph-add-result" class="margin-left"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <hr>

    {{-- API documentation --}}
    <div class="form-horizontal">
        <div class="form-group">
            <label class="col-sm-3 control-label">{{ __('REST API') }}</label>
            <div class="col-sm-9">
                <p class="help-block">{{ __('Authenticate with the header') }} <code>X-FreeScout-API-Key: &lt;key&gt;</code>.</p>
                <pre style="font-size:12px;">GET  {{ url('/api/publicholidays/status') }}
GET  {{ url('/api/publicholidays/status') }}?date=2026-06-23
GET  {{ url('/api/publicholidays') }}?year={{ $year }}
GET  {{ url('/api/publicholidays/upcoming') }}?limit=5</pre>
                <p class="help-block">{{ __('Example response of /status:') }}</p>
                <pre style="font-size:12px;">{
  "date": "2026-06-23",
  "office_open": false,
  "is_holiday": true,
  "holiday": { "name": "Commémoration du plébiscite jurassien", "key": "jura_plebiscite", "canton": "CH-JU", "type": "cantonal" },
  "next_working_day": "2026-06-24"
}</pre>
            </div>
        </div>
    </div>
</div>
@endsection
