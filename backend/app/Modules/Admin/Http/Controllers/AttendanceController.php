<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Admin\Http\Resources\AttendanceResource;
use App\Modules\Admin\Models\Attendance;
use App\Modules\Admin\Services\AttendanceSheet;
use App\Modules\Core\Enums\UserRole;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pointeuse de l'équipe back-office (F7.1.c).
 *
 * Deux périmètres :
 *   - **personnel** (tout membre de l'équipe, garde `consulter:dashboard-admin`) :
 *     pointer son entrée / sa sortie, consulter son propre pointage. Ces actions
 *     ne portent jamais d'identifiant : elles s'appliquent au compte connecté,
 *     donc un agent ne peut pointer que POUR LUI-MÊME.
 *   - **supervision** (administrateur, garde `gerer:utilisateurs`) : la feuille
 *     de présence mensuelle de l'équipe (détail par employé ou récapitulatif),
 *     consultable en JSON ou exportable en CSV.
 */
class AttendanceController extends Controller
{
    /**
     * Pointer l'entrée. POST /api/v1/admin/attendance/clock-in
     *
     * Ouvre une session pour le compte connecté. Refuse (422) s'il reste une
     * session ouverte (on solde d'abord la sortie précédente).
     */
    public function clockIn(Request $request): JsonResponse
    {
        $user = $request->user();

        if (Attendance::query()->where('user_id', $user->id)->open()->exists()) {
            abort(422, 'Vous avez déjà pointé une entrée sans pointer la sortie correspondante.');
        }

        $session = Attendance::create([
            'user_id' => $user->id,
            'started_at' => now(),
        ]);

        activity()->causedBy($user)->performedOn($session)->log('Pointage d’entrée (back-office)');

        return ApiResponse::created(['attendance' => AttendanceResource::make($session)]);
    }

    /**
     * Pointer la sortie. POST /api/v1/admin/attendance/clock-out
     *
     * Solde la session ouverte du compte connecté. Refuse (422) s'il n'y a pas
     * de session ouverte.
     */
    public function clockOut(Request $request): JsonResponse
    {
        $user = $request->user();

        $session = Attendance::query()->where('user_id', $user->id)->open()->latest('started_at')->first();

        if ($session === null) {
            abort(422, 'Aucune entrée en cours : pointez d’abord une entrée.');
        }

        $session->update(['ended_at' => now()]);

        activity()->causedBy($user)->performedOn($session)->log('Pointage de sortie (back-office)');

        return ApiResponse::success(['attendance' => AttendanceResource::make($session)]);
    }

    /**
     * Mon pointage. GET /api/v1/admin/attendance/me
     *
     * État courant (session ouverte le cas échéant), sessions du mois en cours et
     * cumul d'heures — le tout pour le compte connecté uniquement.
     */
    public function me(Request $request, AttendanceSheet $sheet): JsonResponse
    {
        $user = $request->user();

        $current = Attendance::query()->where('user_id', $user->id)->open()->latest('started_at')->first();

        return ApiResponse::success([
            'on_duty' => $current !== null,
            'current' => $current !== null ? AttendanceResource::make($current) : null,
            'month' => $sheet->forUser($user, CarbonImmutable::now()),
        ]);
    }

    /**
     * Feuille de présence de l'équipe. GET /api/v1/admin/attendance
     *
     * Paramètres : `month` (format `Y-m`, défaut = mois courant), `user`
     * (identifiant d'un employé → détail jour par jour ; absent → récapitulatif
     * de toute l'équipe), `format` (`json` par défaut, ou `csv` pour l'export).
     */
    public function sheet(Request $request, AttendanceSheet $sheet): JsonResponse|StreamedResponse
    {
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'user' => ['nullable', 'integer', 'exists:users,id'],
            'format' => ['nullable', 'in:json,csv'],
        ]);

        $month = isset($data['month'])
            ? CarbonImmutable::createFromFormat('Y-m', $data['month'])->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();

        // Détail d'un employé précis.
        if (isset($data['user'])) {
            $member = User::findOrFail($data['user']);
            if (! $member->hasAnyRole(UserRole::staff())) {
                abort(404);
            }

            $detail = $sheet->forUser($member, $month);

            return ($data['format'] ?? 'json') === 'csv'
                ? $this->streamUserCsv($detail)
                : ApiResponse::success($detail);
        }

        // Récapitulatif de toute l'équipe.
        $summary = $sheet->summary($month);

        return ($data['format'] ?? 'json') === 'csv'
            ? $this->streamSummaryCsv($summary)
            : ApiResponse::success($summary);
    }

    /**
     * Détail mensuel d'un employé au format CSV (une ligne par jour).
     *
     * @param  array<string, mixed>  $detail
     */
    private function streamUserCsv(array $detail): StreamedResponse
    {
        $filename = 'presence-'.$detail['user']['id'].'-'.$detail['month'].'.csv';

        return response()->streamDownload(function () use ($detail) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'sessions', 'minutes', 'heures']);
            foreach ($detail['days'] as $day) {
                fputcsv($out, [
                    $day['date'],
                    count($day['sessions']),
                    $day['total_minutes'],
                    $this->toHours($day['total_minutes']),
                ]);
            }
            fputcsv($out, ['TOTAL', '', $detail['total_minutes'], $this->toHours($detail['total_minutes'])]);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Récapitulatif d'équipe au format CSV (une ligne par employé).
     *
     * @param  array<string, mixed>  $summary
     */
    private function streamSummaryCsv(array $summary): StreamedResponse
    {
        $filename = 'presence-equipe-'.$summary['month'].'.csv';

        return response()->streamDownload(function () use ($summary) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['employe', 'email', 'jours_presents', 'minutes', 'heures', 'en_poste']);
            foreach ($summary['employees'] as $line) {
                fputcsv($out, [
                    $line['user']['name'],
                    $line['user']['email'],
                    $line['days_present'],
                    $line['total_minutes'],
                    $this->toHours($line['total_minutes']),
                    $line['currently_on_duty'] ? 'oui' : 'non',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Convertit des minutes en un libellé « HHhMM » lisible.
     */
    private function toHours(int $minutes): string
    {
        return sprintf('%dh%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
