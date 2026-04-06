<?php

namespace App\Http\Controllers\Backstage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backstage\Campaigns\StoreRequest;
use App\Http\Requests\Backstage\Campaigns\UpdateRequest;
use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CampaignsController extends Controller
{
    public function index(): View
    {
        return view('backstage.campaigns.index');
    }

    public function create(): View
    {
        $campaign = new Campaign;
        $campaign->matches_to_win = config('scratchgame.matchestowin', 3);
        $campaign->max_tries = config('scratchgame.maxtries', 10);
        return view('backstage.campaigns.create', [
            'campaign' => $campaign,
        ]);
    }

    public function store(StoreRequest $request): RedirectResponse
    {
        Campaign::create($request->validated());

        session()->flash('success', 'The campaign has been created!');

        return redirect()->route('backstage.campaigns.index');
    }

    public function edit(Campaign $campaign): View
    {
        return view('backstage.campaigns.edit', [
            'campaign' => $campaign,
        ]);
    }

    public function update(UpdateRequest $request, Campaign $campaign): RedirectResponse
    {
        $campaign->update($request->validated());

        session()->flash('success', 'The campaign details have been updated!');

        return redirect()->route('backstage.campaigns.edit', $campaign->id);
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        $campaign->delete();

        session()->flash('success', 'The campaign has been deleted!');

        return redirect()->route('backstage.campaigns.index');
    }

    public function use(Campaign $campaign): RedirectResponse
    {
        session()->put('activeCampaign', $campaign->id);

        return redirect()->route('backstage.campaigns.index');
    }
}
