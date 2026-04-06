@csrf

@include('backstage.partials.forms.text', [
    'field' => 'name',
    'label' => 'Name',
    'value' => old('name') ?? $campaign?->name,
])

@include('backstage.partials.forms.number', [
    'field' => 'matches_to_win',
    'label' => 'No. of matches to win',
    'value' => old('matches_to_win') ?? $campaign?->matches_to_win,
    'min' => 2,
    'max' => 5,
])

@include('backstage.partials.forms.number', [
    'field' => 'max_tries',
    'label' => 'Max tiles scratchable',
    'value' => old('max_tries') ?? $campaign?->max_tries,
    'min' => 1,
    'max' => 25,
])

@include('backstage.partials.forms.select', [
    'field' => 'timezone',
    'label' => 'Timezone',
    'value' => old('timezone') ?? $campaign?->timezone,
    'options' => $campaign->getAvailableTimezones(),
])

@include('backstage.partials.forms.starts-ends', [
    'starts_at' => old('starts_at') ?? ($campaign->starts_at === null ? $campaign->starts_at : $campaign->starts_at->format('d-m-Y H:i:s')),
    'ends_at' => old('ends_at') ?? ($campaign->ends_at === null ? $campaign->ends_at : $campaign->ends_at->format('d-m-Y H:i:s')),
])

@include('backstage.partials.forms.submit')
