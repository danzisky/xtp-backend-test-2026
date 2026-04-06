<?php

namespace App\Http\Controllers\Backstage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backstage\Prizes\StoreRequest;
use App\Http\Requests\Backstage\Prizes\UpdateRequest;
use App\Models\Prize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PrizeController extends Controller
{
    public function index(): View
    {
        return view('backstage.prizes.index');
    }

    public function create(): View
    {
        return view('backstage.prizes.create', [
            'prize' => new Prize,
        ]);
    }

    public function store(StoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['campaign_id'] = session('activeCampaign');

        try {
            DB::transaction(function () use ($data) {
                $prize = Prize::create($data);

                Log::info('Backstage prize created', [
                    'prize_id' => $prize->id,
                    'campaign_id' => $prize->campaign_id,
                    'name' => $prize->name,
                ]);
            });
        } catch (\Throwable $exception) {
            Log::error('Backstage prize creation failed', [
                'campaign_id' => $data['campaign_id'] ?? null,
                'name' => $data['name'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            session()->flash('error', 'The prize could not be created.');

            return redirect()->back()->withInput();
        }

        session()->flash('success', 'The prize has been created!');

        return redirect()->route('backstage.prizes.index');
    }

    public function edit(Prize $prize): View
    {
        return view('backstage.prizes.edit', [
            'prize' => $prize,
        ]);
    }

    public function update(UpdateRequest $request, Prize $prize): RedirectResponse
    {
        $data = $request->validated();
        $data['campaign_id'] = session('activeCampaign');

        try {
            DB::transaction(function () use ($data, $prize) {
                $prize->update($data);

                Log::info('Backstage prize updated', [
                    'prize_id' => $prize->id,
                    'campaign_id' => $prize->campaign_id,
                    'name' => $prize->name,
                ]);
            });
        } catch (\Throwable $exception) {
            Log::error('Backstage prize update failed', [
                'prize_id' => $prize->id,
                'error' => $exception->getMessage(),
            ]);

            session()->flash('error', 'The prize could not be updated.');

            return redirect()->back()->withInput();
        }

        session()->flash('success', 'The prize has been updated!');

        return redirect()->route('backstage.prizes.edit', $prize->id);
    }

    public function destroy(Prize $prize): RedirectResponse
    {
        $prizeId = $prize->id;
        $campaignId = $prize->campaign_id;
        $name = $prize->name;

        try {
            DB::transaction(function () use ($prize) {
                $prize->delete();
            });

            Log::info('Backstage prize deleted', [
                'prize_id' => $prizeId,
                'campaign_id' => $campaignId,
                'name' => $name,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Backstage prize deletion failed', [
                'prize_id' => $prizeId,
                'error' => $exception->getMessage(),
            ]);

            session()->flash('error', 'The prize could not be deleted.');

            return redirect()->back();
        }

        session()->flash('success', 'The prize has been deleted!');

        return redirect()->route('backstage.prizes.index');
    }
}
