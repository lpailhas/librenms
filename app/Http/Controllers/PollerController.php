<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Poller;
use App\Models\PollerCluster;
use App\Models\PollerGroup;
use Carbon\Carbon;
use Illuminate\Http\Request;
use LibreNMS\Config;

class PollerController extends Controller
{
    public $rrdstep;

    public function __construct()
    {
        $this->rrdstep = Config::get('rrd.step');
    }

    public function logTab(Request $request)
    {
        return view('poller.log', [
            'current_tab' => 'log',
            'filter' => $request->input('filter', 'active')
        ]);
    }

    public function groupsTab()
    {
        return view('poller.groups', [
            'current_tab' => 'groups',
            'poller_groups' => PollerGroup::query()->withCount('devices')->get(),
            'default_group_id' => Config::get('default_poller_group'),
            'ungrouped_count' => Device::where('poller_group', 0)->count(),
        ]);
    }

    public function pollerTab()
    {
        return view('poller.poller', [
            'current_tab' => 'poller',
            'pollers' => $this->poller(),
            'poller_cluster' => $this->pollerCluster(),
        ]);
    }

    public function performanceTab()
    {
        return view('poller.performance', ['current_tab' => 'performance']);
    }

    protected function pollerStatus($poller, $last)
    {
        $since_last_poll = Carbon::parse($last)->diffInSeconds();

        $poller->row_class = $this->checkTimeSinceLastPoll($since_last_poll);
        $poller->long_not_polled = (\Auth::user()->hasGlobalAdmin() && ($since_last_poll > ($this->rrdstep * 2)));

        return $poller;
    }

    private function poller()
    {
        return Poller::query()->orderBy('poller_name')->get()->map(function ($poller) {
            return $this->pollerStatus($poller, $poller->last_polled);
        });
    }

    private function pollerCluster()
    {
        return PollerCluster::with('stats')->orderBy('poller_name')->get()->map(function ($poller) {
            return $this->pollerStatus($poller, $poller->last_report);
        });
    }

    private function checkTimeSinceLastPoll($seconds)
    {
        if ($seconds >= $this->rrdstep) {
            return 'danger';
        } elseif ($seconds >= ($this->rrdstep * 0.95)) {
            return 'warning';
        }

        return 'success';
    }
}
