<?php

namespace App\Http\Controllers\Console;

use App\Domain\Organisation\Models\Department;
use App\Domain\Reports\Services\DepartmentReport;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * LLD 08 §3/§5: the department report and its CSV export.
 */
class ReportController extends Controller
{
    public function __construct(private readonly DepartmentReport $reports) {}

    public function department(Request $request, Department $department): Response|StreamedResponse
    {
        Gate::authorize('view-department-report', $department);

        $from = $this->date($request->query('from'), now()->startOfMonth());
        $to = $this->date($request->query('to'), now());
        $report = $this->reports->forDepartment($department, $from, $to);

        if ($request->query('format') === 'csv') {
            Gate::authorize('export-department-report', $department);

            return $this->csv($department, $report, $from, $to);
        }

        $activity = $this->reports->activity($department, $from, $to);

        return Inertia::render('console/reports/Department', [
            'department' => ['slug' => $department->slug, 'name' => (string) $department->localized('name')],
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'report' => [
                ...$report,
                'activity' => [
                    'data' => collect($activity->items())->map(fn (Activity $entry): array => AuditController::row($entry))->all(),
                    'meta' => [
                        'current_page' => $activity->currentPage(),
                        'last_page' => $activity->lastPage(),
                        'total' => $activity->total(),
                    ],
                ],
            ],
            'can' => ['export' => $request->user()->can('export-department-report', $department)],
        ]);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function csv(Department $department, array $report, CarbonInterface $from, CarbonInterface $to): StreamedResponse
    {
        $filename = sprintf('report-%s-%s-%s.csv', $department->slug, $from->toDateString(), $to->toDateString());

        return response()->streamDownload(function () use ($department, $report, $from, $to): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['department', 'from', 'to']);
            fputcsv($out, [$department->name_en, $from->toDateString(), $to->toDateString()]);
            fputcsv($out, []);
            fputcsv($out, ['area', 'measure', 'value']);

            foreach (array_diff_key($report, ['areas' => true]) as $area => $measures) {
                foreach ($this->flatten($measures) as $measure => $value) {
                    fputcsv($out, [$area, $measure, $value]);
                }
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, scalar|null>
     */
    private function flatten(array $values, string $prefix = ''): array
    {
        $flat = [];

        foreach ($values as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $flat = is_array($value) ? [...$flat, ...$this->flatten($value, $name)] : [...$flat, $name => $value];
        }

        return $flat;
    }

    private function date(mixed $value, CarbonInterface $fallback): CarbonInterface
    {
        return is_string($value) && strtotime($value) !== false ? Carbon::parse($value) : $fallback;
    }
}
