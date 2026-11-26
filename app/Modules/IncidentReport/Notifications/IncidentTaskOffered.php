<?php

namespace App\Modules\IncidentReport\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Notifikasi in-app ke relawan saat tugas ditawarkan (EIR-11).
 * Tidak memuat kontak pelapor atau koordinat; relawan membuka detail tugas di aplikasi.
 */
class IncidentTaskOffered extends Notification
{
    public function __construct(
        public readonly string $assignmentId,
        public readonly string $incidentId,
        public readonly string $ticketCode,
        public readonly string $category,
        public readonly string $urgency,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'module' => 'IncidentReport',
            'type' => 'incident.task_offered',
            'assignment_id' => $this->assignmentId,
            'incident_id' => $this->incidentId,
            'ticket_code' => $this->ticketCode,
            'category' => $this->category,
            'urgency' => $this->urgency,
            'message' => "Tugas insiden {$this->ticketCode} ditawarkan kepada Anda.",
        ];
    }
}
