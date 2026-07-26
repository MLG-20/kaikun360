<?php

namespace App\Modules\Admin\Services;

use App\Models\User;
use App\Modules\Admin\Models\Attendance;
use App\Modules\Core\Enums\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Feuille de présence mensuelle de l'équipe back-office (pointeuse, F7.1.c).
 *
 * Agrège les sessions de présence (`attendances`) en deux vues consultables par
 * l'administrateur :
 *   - le **détail** d'un employé sur un mois (jour par jour, avec le cumul
 *     d'heures) — `forUser()` ;
 *   - le **récapitulatif** de toute l'équipe sur un mois (total et jours de
 *     présence par personne) — `summary()`.
 *
 * L'agrégation est faite en PHP (volumes de back-office modestes) pour rester
 * indépendante du moteur SQL. Seules les sessions **soldées** (sortie pointée)
 * comptent dans les totaux d'heures ; une session encore ouverte est signalée
 * mais n'ajoute pas d'heures tant qu'elle n'est pas fermée.
 */
class AttendanceSheet
{
    /**
     * Détail mensuel d'un employé : jours travaillés, sessions et cumul.
     *
     * @return array<string, mixed>
     */
    public function forUser(User $user, CarbonImmutable $month): array
    {
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();

        $sessions = Attendance::query()
            ->where('user_id', $user->id)
            ->whereBetween('started_at', [$start, $end])
            ->orderBy('started_at')
            ->get();

        // Regroupement par jour civil (date de l'entrée).
        $days = $sessions
            ->groupBy(fn (Attendance $a) => $a->started_at->toDateString())
            ->map(fn (Collection $ofDay, string $date) => [
                'date' => $date,
                'sessions' => $ofDay->map(fn (Attendance $a) => $this->session($a))->values(),
                'total_minutes' => $this->totalMinutes($ofDay),
            ])
            ->values();

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'month' => $start->format('Y-m'),
            'days' => $days,
            'total_minutes' => $this->totalMinutes($sessions),
        ];
    }

    /**
     * Récapitulatif de toute l'équipe sur le mois : total d'heures et nombre de
     * jours de présence par employé, plus l'indicateur « en poste maintenant ».
     *
     * @return array<string, mixed>
     */
    public function summary(CarbonImmutable $month): array
    {
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();

        $sessions = Attendance::query()
            ->whereBetween('started_at', [$start, $end])
            ->with(['user' => fn ($q) => $q->select('id', 'name', 'email')])
            ->get()
            // On ne restitue que les membres de l'équipe (un compte rétrogradé
            // depuis reste ainsi hors de la feuille).
            ->filter(fn (Attendance $a) => $a->user?->hasAnyRole(UserRole::staff()) ?? false)
            ->groupBy('user_id');

        $employees = $sessions->map(function (Collection $ofUser) {
            /** @var Attendance $first */
            $first = $ofUser->first();

            return [
                'user' => [
                    'id' => $first->user->id,
                    'name' => $first->user->name,
                    'email' => $first->user->email,
                ],
                'total_minutes' => $this->totalMinutes($ofUser),
                'days_present' => $ofUser
                    ->map(fn (Attendance $a) => $a->started_at->toDateString())
                    ->unique()
                    ->count(),
                'currently_on_duty' => $ofUser->contains(fn (Attendance $a) => $a->isOpen()),
            ];
        })
            ->sortByDesc('total_minutes')
            ->values();

        return [
            'month' => $start->format('Y-m'),
            'employees' => $employees,
        ];
    }

    /**
     * Normalise une session pour l'affichage.
     *
     * @return array<string, mixed>
     */
    private function session(Attendance $a): array
    {
        return [
            'id' => $a->id,
            'started_at' => $a->started_at,
            'ended_at' => $a->ended_at,
            'duration_minutes' => $a->durationMinutes(),
            'is_open' => $a->isOpen(),
        ];
    }

    /**
     * Somme des durées (minutes) des sessions SOLDÉES d'une collection.
     *
     * @param  Collection<int, Attendance>  $sessions
     */
    private function totalMinutes(Collection $sessions): int
    {
        return (int) $sessions->sum(fn (Attendance $a) => $a->durationMinutes() ?? 0);
    }
}
