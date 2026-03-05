<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function dailySummary(Request $request)
    {
        $from   git= $request->query('from'); // YYYY-MM-DD
        $to     = $request->query('to');   // YYYY-MM-DD
        $group  = (string) $request->query('group', 'driver'); // driver|carrier|client|job
        $metric = (string) $request->query('metric', 'margin_total_sum'); // margin_total_sum|tons_sum

        if (!$from || !$to) {
            return response()->json([
                'ok' => false,
                'message' => 'Date range required: from, to',
            ], 422);
        }

        // Group (what becomes columns in the report)
        $groupMap = [
            'driver' => [
                'expr'  => "TRIM(CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')))",
                'label' => 'Driver',
            ],
            'carrier' => [
                'expr'  => "COALESCE(carrier_name,'')",
                'label' => 'Carrier',
            ],
            'client' => [
                'expr'  => "COALESCE(client_name,'')",
                'label' => 'Client',
            ],
            'job' => [
                'expr'  => "COALESCE(pl_job,'')",
                'label' => 'Job Site',
            ],
        ];

        if (!isset($groupMap[$group])) {
            $group = 'driver';
        }

        // Metrics
        $metricMap = [
            'margin_total_sum' => [
                'expr'   => 'COALESCE(SUM(margin_total),0)',
                'label'  => 'Margin Total (SUM)',
                'format' => 'money',
            ],
            'tons_sum' => [
                'expr'   => 'COALESCE(SUM(tons),0)',
                'label'  => 'Tons (SUM)',
                'format' => 'tons',
            ],
        ];

        if (!isset($metricMap[$metric])) {
            $metric = 'margin_total_sum';
        }

        $groupExpr   = $groupMap[$group]['expr'];
        $groupLabel  = $groupMap[$group]['label'];
        $metricExpr  = $metricMap[$metric]['expr'];
        $metricLabel = $metricMap[$metric]['label'];
        $metricFmt   = $metricMap[$metric]['format'];

        // fatloads.load_date is VARCHAR(10) like MM-DD-YYYY
        $raw = DB::table('fatloads')
            ->selectRaw("
                DATE_FORMAT(STR_TO_DATE(load_date, '%m-%d-%Y'), '%Y-%m-%d') as d,
                {$groupExpr} as series_name,
                {$metricExpr} as total
            ")
            ->whereRaw("STR_TO_DATE(load_date, '%m-%d-%Y') BETWEEN ? AND ?", [$from, $to])
            ->whereNotNull('load_date')
            ->groupBy('d', 'series_name')
            ->orderBy('d')
            ->get();

        $dates  = [];
        $series = [];
        $matrix = [];

        foreach ($raw as $r) {
            $date = $r->d;
            $name = (string) $r->series_name;

            if (!$date) continue;
            if (trim($name) === '') continue;

            $dates[$date] = true;
            $series[$name] = true;

            if (!isset($matrix[$date])) $matrix[$date] = [];
            $matrix[$date][$name] = (float) $r->total;
        }

        $dates = array_keys($dates);
        sort($dates);

        $series = array_keys($series);
        sort($series);

        return response()->json([
            'ok' => true,
            'group' => $group,
            'group_label' => $groupLabel,
            'metric' => $metric,
            'metric_label' => $metricLabel,
            'metric_format' => $metricFmt, // money|tons
            'dates' => $dates,
            'series' => $series,
            'matrix' => $matrix,
        ]);
    }
}
