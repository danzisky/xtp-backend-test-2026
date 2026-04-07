<?php

namespace App\Livewire\Backstage;

use App\Models\Game;

class GameTable extends TableComponent
{
    public $sortField = 'finished_at';

    public $extraFilters = 'games-filters';

    public $prizeId = null;

    public $account = null;

    public $startDate = null;

    public $endDate = null;

    public function export() {}

    public function render()
    {
        $columns = [
            [
                'title' => 'id',
                'sort' => true,
            ],
            [
                'title' => 'account',
                'sort' => true,
            ],

            [
                'title' => 'prize name', // updated to show prize name instead
                'nested' => 'prize.name',
                'sort' => false,
            ],

            [
                'title' => 'status',
                'sort' => true,
            ],

            [
                'title' => 'max tries',
                'attribute' => 'max_tries',
                'sort' => true,
            ],

            [
                'title' => 'matches to win',
                'attribute' => 'matches_to_win',
                'sort' => true,
            ],

            [
                'title' => 'finished at',
                'attribute' => 'finished_at',
                'sort' => true,
            ],
        ];

        return view('livewire.backstage.table', [
            'columns' => $columns,
            'resource' => 'games',
            'rows' => Game::filter($this->account, (int) $this->prizeId, $this->startDate, $this->endDate)
                ->with('prize:id,name')
                ->where(function ($query) {
                    $query
                        ->where('games.campaign_id', session('activeCampaign'))
                        ->orWhereNull('games.prize_id');
                })
                ->orderBy($this->sortField, $this->sortDesc ? 'DESC' : 'ASC')
                ->paginate($this->perPage),
        ]);
    }
}
