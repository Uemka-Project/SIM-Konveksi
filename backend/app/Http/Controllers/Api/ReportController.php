<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ReportFilterRequest;
use App\Services\ReportService;
use App\Support\SimplePdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const ABILITIES = [
        'sales' => 'reports.sales',
        'purchases' => 'reports.purchases',
        'stock-card' => 'reports.stock_card',
        'productivity' => 'reports.productivity',
    ];

    public function __construct(private readonly ReportService $reports) {}

    public function sales(ReportFilterRequest $request): JsonResponse
    {
        Gate::authorize(self::ABILITIES['sales']);

        return $this->reportResponse('sales', $request->validated());
    }

    public function purchases(ReportFilterRequest $request): JsonResponse
    {
        Gate::authorize(self::ABILITIES['purchases']);

        return $this->reportResponse('purchases', $request->validated());
    }

    public function stockCard(ReportFilterRequest $request): JsonResponse
    {
        Gate::authorize(self::ABILITIES['stock-card']);

        return $this->reportResponse('stock-card', $request->validated());
    }

    public function productivity(ReportFilterRequest $request): JsonResponse
    {
        Gate::authorize(self::ABILITIES['productivity']);

        return $this->reportResponse('productivity', $request->validated());
    }

    /**
     * Export a report as CSV or PDF.
     */
    public function export(ReportFilterRequest $request, string $report): Response
    {
        abort_unless(isset(self::ABILITIES[$report]), 404);

        Gate::authorize(self::ABILITIES[$report]);

        $filters = $request->validated();
        $format = $filters['format'] ?? 'csv';
        $content = $this->build($report, $filters);
        $table = $this->reports->tableFor($report, $content);
        $filename = $this->reports->filename($report, $filters, $format);

        if ($format === 'pdf') {
            $pdf = SimplePdf::render(
                $table['title'],
                $this->reports->pdfLines($report, $content),
            );

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'no-store, no-cache',
            ]);
        }

        return response($this->csv($table['headings'], $table['rows']), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function reportResponse(string $type, array $filters): JsonResponse
    {
        return response()->json([
            'data' => $this->build($type, $filters),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function build(string $type, array $filters): array
    {
        return match ($type) {
            'sales' => $this->reports->sales($filters),
            'purchases' => $this->reports->purchases($filters),
            'stock-card' => $this->reports->stockCard($filters),
            'productivity' => $this->reports->productivity($filters),
            default => abort(404),
        };
    }

    /**
     * @param  list<string>  $headings
     * @param  list<list<string|int|float|null>>  $rows
     */
    private function csv(array $headings, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return '';
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headings, ';', '"', '');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ';', '"', '');
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content === false ? '' : $content;
    }
}
