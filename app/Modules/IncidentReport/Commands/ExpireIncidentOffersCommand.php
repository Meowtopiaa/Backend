<?php

namespace App\Modules\IncidentReport\Commands;

use App\Modules\IncidentReport\Services\IncidentDispatchService;
use Illuminate\Console\Command;

/**
 * PRD 8.7: tawaran tugas tanpa respons dalam 15 menit mengembalikan tiket ke WAITING_ASSIGNMENT.
 * Jadwalkan tiap menit oleh PM di routes/console.php:
 *   Schedule::command('incident:expire-offers')->everyMinute();
 * Selain itu, kedaluwarsa juga diproses saat antrean admin atau daftar tugas relawan dibuka.
 */
class ExpireIncidentOffersCommand extends Command
{
    protected $signature = 'incident:expire-offers';

    protected $description = 'Kembalikan tawaran tugas insiden yang tidak direspons dalam 15 menit ke antrean';

    public function handle(IncidentDispatchService $dispatch): int
    {
        $count = $dispatch->expireStaleOffers();
        $this->info("{$count} tawaran tugas kedaluwarsa dikembalikan ke antrean.");

        return self::SUCCESS;
    }
}
