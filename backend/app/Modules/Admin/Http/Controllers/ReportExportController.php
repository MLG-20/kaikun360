<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\AccountingReporter;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export comptable & reporting du back-office (B13.5).
 *
 * Réservé à la permission `gerer:paiements`. Consolide les flux financiers sur
 * une période (réservations + reversements) et les restitue en JSON (défaut) ou
 * en CSV téléchargeable (grand livre des réservations).
 */
class ReportExportController extends Controller
{
    /**
     * GET /api/v1/admin/reports/export
     *
     * Paramètres : `from`, `to` (dates, facultatives), `format` (json|csv).
     */
    public function export(Request $request, AccountingReporter $reporter): JsonResponse|StreamedResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'format' => ['nullable', 'in:json,csv'],
        ]);

        $from = isset($data['from']) ? CarbonImmutable::parse($data['from']) : null;
        $to = isset($data['to']) ? CarbonImmutable::parse($data['to']) : null;

        $report = $reporter->report($from, $to);

        if (($data['format'] ?? 'json') === 'csv') {
            return $this->streamCsv($report);
        }

        return ApiResponse::success($report);
    }

    /**
     * Grand livre des réservations au format CSV (téléchargement).
     *
     * @param  array<string, mixed>  $report
     */
    private function streamCsv(array $report): StreamedResponse
    {
        $filename = 'export-comptable-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');

            // En-têtes de colonnes.
            fputcsv($out, ['reference', 'date', 'type', 'amount_xof', 'commission_xof', 'status']);

            foreach ($report['bookings'] as $line) {
                fputcsv($out, [
                    $line['reference'],
                    $line['date'],
                    $line['type'],
                    $line['amount_xof'],
                    $line['commission_xof'],
                    $line['status'],
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
