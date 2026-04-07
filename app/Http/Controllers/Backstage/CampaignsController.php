<?php

namespace App\Http\Controllers\Backstage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backstage\Campaigns\StoreRequest;
use App\Http\Requests\Backstage\Campaigns\UpdateRequest;
use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        $data = $request->validated();

        try {
            DB::transaction(function () use ($data) {
                $campaign = Campaign::create($data);

                Log::info('Backstage campaign created', [
                    'campaign_id' => $campaign->id,
                    'name' => $campaign->name,
                    'slug' => $campaign->slug,
                ]);
            });
        } catch (\Throwable $exception) {
            Log::error('Backstage campaign creation failed', [
                'name' => $data['name'] ?? null,
                'slug' => $data['slug'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            session()->flash('error', 'The campaign could not be created.');

            return redirect()->back()->withInput();
        }

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
        $data = $request->validated();

        try {
            DB::transaction(function () use ($data, $campaign) {
                $campaign->update($data);

                Log::info('Backstage campaign updated', [
                    'campaign_id' => $campaign->id,
                    'name' => $campaign->name,
                    'slug' => $campaign->slug,
                ]);
            });
        } catch (\Throwable $exception) {
            Log::error('Backstage campaign update failed', [
                'campaign_id' => $campaign->id,
                'error' => $exception->getMessage(),
            ]);

            session()->flash('error', 'The campaign could not be updated.');

            return redirect()->back()->withInput();
        }

        session()->flash('success', 'The campaign details have been updated!');

        return redirect()->route('backstage.campaigns.edit', $campaign->id);
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        $campaignId = $campaign->id;
        $slug = $campaign->slug;
        $name = $campaign->name;

        try {
            DB::transaction(function () use ($campaign) {
                $campaign->delete();
            });

            Log::info('Backstage campaign deleted', [
                'campaign_id' => $campaignId,
                'name' => $name,
                'slug' => $slug,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Backstage campaign deletion failed', [
                'campaign_id' => $campaignId,
                'error' => $exception->getMessage(),
            ]);

            session()->flash('error', 'The campaign could not be deleted.');

            return redirect()->back();
        }

        session()->flash('success', 'The campaign has been deleted!');

        return redirect()->route('backstage.campaigns.index');
    }

    public function use(Campaign $campaign): RedirectResponse
    {
        session()->put('activeCampaign', $campaign->id);

        Log::info('Backstage active campaign selected', [
            'campaign_id' => $campaign->id,
            'slug' => $campaign->slug,
        ]);

        return redirect()->route('backstage.campaigns.index');
    }
}
