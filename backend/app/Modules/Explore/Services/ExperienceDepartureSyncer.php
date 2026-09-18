<?php

namespace App\Modules\Explore\Services;

use App\Enums\BookingStatus;
use App\Modules\Explore\Models\TourismExperience;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Synchronise les dates de départ d'un circuit à la modification (F21).
 *
 * Remplace-tout, sauf garde-fou : une date déjà réservée (réservations non
 * annulées portant sa date) ne peut pas disparaître du tableau envoyé — le
 * client qui a réservé cette session ne doit jamais se retrouver avec une
 * date de départ qui n'existe plus.
 */
class ExperienceDepartureSyncer
{
    /**
     * @param  array<int, array{id?: int, start_date: string, seats_total: int}>  $departures
     *
     * @throws ValidationException si une date retirée porte des réservations actives.
     */
    public function sync(TourismExperience $experience, array $departures): void
    {
        DB::transaction(function () use ($experience, $departures) {
            $existing = $experience->departures()->get()->keyBy('id');
            $keptIds = [];

            foreach ($departures as $row) {
                $id = $row['id'] ?? null;

                if ($id && $existing->has($id)) {
                    $existing[$id]->update([
                        'start_date' => $row['start_date'],
                        'seats_total' => $row['seats_total'],
                    ]);
                    $keptIds[] = $id;

                    continue;
                }

                $created = $experience->departures()->create([
                    'start_date' => $row['start_date'],
                    'seats_total' => $row['seats_total'],
                ]);
                $keptIds[] = $created->id;
            }

            $removedIds = $existing->keys()->diff($keptIds);

            foreach ($removedIds as $id) {
                $departure = $existing[$id];

                $hasActiveBookings = $experience->bookings()
                    ->whereDate('start_date', $departure->start_date)
                    ->whereNotIn('status', BookingStatus::valeursAnnulees())
                    ->exists();

                if ($hasActiveBookings) {
                    throw ValidationException::withMessages([
                        'departures' => ["Le départ du {$departure->start_date->format('d/m/Y')} a déjà des réservations : il ne peut pas être retiré."],
                    ]);
                }

                $departure->delete();
            }
        });
    }
}
