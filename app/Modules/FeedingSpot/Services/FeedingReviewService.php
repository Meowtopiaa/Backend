<?php

namespace App\Modules\FeedingSpot\Services;

use App\Models\User;
use App\Modules\Core\Services\PointService;
use App\Modules\FeedingSpot\Events\FeedingVerified;
use App\Modules\FeedingSpot\Models\FeedingActivity;
use App\Modules\FeedingSpot\Models\FeedingAggregate;
use App\Modules\FeedingSpot\Models\FeedingEvidence;
use App\Modules\FeedingSpot\Models\FeedingReview;
use App\Modules\FeedingSpot\Models\FeedingRuleResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Service terpadu untuk antrean review & keputusan reviewer (MEOW-12 & MEOW-22).
 * Digunakan bersama oleh REST API Admin dan Filament Resource.
 */
class FeedingReviewService
{
    public function __construct(
        private readonly PointService $pointService,
        private readonly FeedingRewardService $rewardService,
    ) {}

    /**
     * Query dasar antrean review (AFS-07, MEOW-12, MEOW-22).
     * Menyembunyikan pengajuan milik reviewer sendiri bila $excludeSelf = true,
     * dan tidak memuat data pribadi sensitif (tanpa phone, identity_number, email).
     */
    public function getReviewQueueQuery(
        ?User $reviewer = null,
        bool $excludeSelf = true,
        string $status = 'UNDER_REVIEW'
    ): Builder {
        $query = FeedingActivity::query()
            ->where('status', strtoupper($status))
            ->with([
                'user:id,name,user_type',
                'spot:id,code,name,latitude,longitude,status',
                'cat:id,unique_code,name',
                'evidence',
                'ruleResults',
                'reviews.reviewer:id,name',
            ]);

        if ($excludeSelf && $reviewer !== null) {
            $query->where('user_id', '!=', $reviewer->id);
        }

        return $query;
    }

    /**
     * Format satu item aktivitas dalam antrean review beserta signed URL foto 10 menit,
     * flags sistem, dan hasil feeding_rule_results, tanpa data pribadi pengguna.
     */
    public function formatQueueItem(FeedingActivity $a): array
    {
        return [
            'id' => $a->id,
            'feeding_record_ref' => $a->feeding_record_ref,
            'status' => $a->status,
            'reward_status' => $a->reward_status,
            'points_awarded' => $a->points_awarded,
            'potential_points' => $a->potential_points,
            'food_type' => $a->food_type,
            'food_amount' => $a->food_amount,
            'notes' => $a->notes,
            'flags' => $a->flags ?? [],
            'scanned_at' => $a->scanned_at?->toIso8601String(),
            'submitted_at' => $a->finalized_at?->toIso8601String(),
            'verified_at' => $a->status === 'VERIFIED' ? $a->finalized_at?->toIso8601String() : null,
            'user' => $a->user ? [
                'id' => $a->user->id,
                'name' => $a->user->name,
                'user_type' => $a->user->user_type,
            ] : null,
            'spot' => $a->spot ? [
                'id' => $a->spot->id,
                'code' => $a->spot->code,
                'name' => $a->spot->name,
            ] : null,
            'cat' => $a->cat ? [
                'id' => $a->cat->id,
                'unique_code' => $a->cat->unique_code,
                'name' => $a->cat->name,
            ] : null,
            'evidence' => $a->evidence->map(fn (FeedingEvidence $e) => [
                'id' => $e->id,
                'media_id' => $e->media_id,
                'signed_url' => $e->signed_url,
                'media_url' => $e->media_url,
                'expires_in_seconds' => 600,
                'captured_at' => $e->captured_at?->toIso8601String(),
                'sort_order' => $e->sort_order,
            ])->values(),
            'rule_results' => $a->ruleResults->map(fn (FeedingRuleResult $r) => [
                'rule' => $r->rule,
                'result' => $r->result,
                'detail' => $r->detail,
            ])->values(),
        ];
    }

    /**
     * Eksekusi keputusan reviewer (VERIFIED atau REJECTED) dengan:
     * - Cek permission feeding.review (403)
     * - Larangan self-review (403 SELF_REVIEW_FORBIDDEN)
     * - Alasan wajib (disimpan ke feeding_reviews)
     * - Idempotensi keputusan: keputusan yang sama dikirim dua kali tidak memberi/mencabut poin dua kali
     * - Integrasi PointService::award() & PointService::reverse()
     */
    public function reviewActivity(
        FeedingActivity $activity,
        User $reviewer,
        string $decision,
        ?string $reason
    ): array {
        if (empty(config('permission.column_names.role_pivot_key'))) {
            config([
                'permission.column_names.role_pivot_key' => 'role_id',
                'permission.column_names.permission_pivot_key' => 'permission_id',
            ]);
        }

        if (!$reviewer->can('feeding.review')) {
            throw new HttpException(403, 'Anda tidak memiliki izin feeding.review.');
        }

        // AFS-07: Reviewer tidak boleh me-review pengajuannya sendiri
        if ($activity->user_id === $reviewer->id) {
            throw new HttpException(
                403,
                'Reviewer tidak diperbolehkan memeriksa pengajuan pemberian pakan miliknya sendiri.'
            );
        }

        $decision = strtoupper(trim($decision));
        if (!in_array($decision, ['VERIFIED', 'REJECTED'], true)) {
            throw ValidationException::withMessages([
                'decision' => ['Keputusan review harus bernilai VERIFIED atau REJECTED.'],
            ]);
        }

        $trimmedReason = $reason !== null ? trim($reason) : '';
        if ($trimmedReason === '') {
            throw ValidationException::withMessages([
                'reason' => ['Alasan keputusan review wajib diisi.'],
            ]);
        }

        return DB::transaction(function () use ($activity, $reviewer, $decision, $trimmedReason) {
            /** @var FeedingActivity $locked */
            $locked = FeedingActivity::with(['user', 'spot'])
                ->lockForUpdate()
                ->findOrFail($activity->id);

            $latestReview = FeedingReview::query()
                ->where('activity_id', $locked->id)
                ->latest('created_at')
                ->latest('id')
                ->first();

            // MEOW-22: Jika pengajuan sudah diputuskan reviewer lain lebih dulu, tolak dengan 409 CONFLICT
            if (
                in_array($locked->status, ['VERIFIED', 'REJECTED'], true)
                && $latestReview !== null
                && $latestReview->reviewer_id !== $reviewer->id
            ) {
                throw new HttpException(
                    409,
                    'Pengajuan ini sudah diputuskan oleh reviewer lain. Muat ulang antrean review.'
                );
            }

            // Idempotensi: jika keputusan yang sama dikirim dua kali oleh reviewer yang sama, jangan beri poin dua kali
            if ($locked->status === $decision) {
                $snapshot = $locked->reward_snapshot ?? [];
                $pointsAwarded = (int) ($snapshot['points_awarded'] ?? ($decision === 'VERIFIED' ? $locked->potential_points : 0));
                $rewardCapped = (bool) ($snapshot['reward_capped'] ?? false);

                return [
                    'activity_id' => $locked->id,
                    'decision' => $decision,
                    'status' => $locked->status,
                    'reward_status' => $locked->reward_status,
                    'points_awarded' => $decision === 'VERIFIED' ? $pointsAwarded : 0,
                    'credited_points' => $decision === 'VERIFIED' ? $pointsAwarded : 0,
                    'reward_capped' => $rewardCapped,
                    'is_replay' => true,
                    'reviewed_by' => $reviewer->id,
                    'reviewed_at' => now()->toIso8601String(),
                ];
            }

            // Jika sudah REJECTED dan mencoba diubah ke VERIFIED, tolak 409 CONFLICT
            if ($locked->status === 'REJECTED') {
                throw new HttpException(
                    409,
                    'Pengajuan ini sudah diputuskan ditolak sebelumnya dan tidak dapat diubah.'
                );
            }

            if (!in_array($locked->status, ['UNDER_REVIEW', 'SUBMITTED', 'VERIFIED'], true)) {
                throw new HttpException(
                    422,
                    "Aktivitas berstatus {$locked->status} tidak dapat direview."
                );
            }

            $oldStatus = $locked->status;
            $oldRewardStatus = $locked->reward_status;

            FeedingReview::create([
                'activity_id' => $locked->id,
                'reviewer_id' => $reviewer->id,
                'decision' => $decision,
                'reason' => $trimmedReason,
            ]);

            if ($decision === 'VERIFIED') {
                $formulaSnapshot = $this->rewardService->calculateRewardSnapshot($locked, now());
                $award = $this->pointService->award(
                    user: $locked->user,
                    ruleKey: 'feeding.verified',
                    sourceModule: 'FeedingSpot',
                    sourceType: 'feeding_activity',
                    sourceRefId: $locked->id,
                    idempotencyKey: "feeding.verified:{$locked->id}"
                );

                $rewardCapped = (bool) ($award['reward_capped'] ?? false) || ($formulaSnapshot['reward_capped'] ?? false);
                $pointsAwarded = $rewardCapped ? 0 : (int) ($award['points'] ?? 0);
                $snapshot = $locked->reward_snapshot ?? [];
                $newRewardStatus = $pointsAwarded > 0 ? 'CREDITED' : 'NOT_ELIGIBLE';

                $locked->update([
                    'status' => 'VERIFIED',
                    'reward_status' => $newRewardStatus,
                    'potential_points' => $pointsAwarded,
                    'reward_snapshot' => array_merge($snapshot, $formulaSnapshot, [
                        'points_awarded' => $pointsAwarded,
                        'reward_capped' => $rewardCapped,
                        'transaction_id' => $award['transaction_id'] ?? null,
                        'review_reason' => $trimmedReason,
                    ]),
                ]);

                $this->recordReviewAuditLog(
                    activity: $locked,
                    reviewer: $reviewer,
                    action: 'FEEDING_REVIEW_APPROVED',
                    oldStatus: $oldStatus,
                    oldRewardStatus: $oldRewardStatus,
                    newStatus: 'VERIFIED',
                    newRewardStatus: $newRewardStatus,
                    pointsAwarded: $pointsAwarded,
                    reason: $trimmedReason
                );

                $this->refreshSpotAggregate($locked->spot_id);
                event(new FeedingVerified($locked->fresh()));

                return [
                    'activity_id' => $locked->id,
                    'decision' => 'VERIFIED',
                    'status' => 'VERIFIED',
                    'reward_status' => $newRewardStatus,
                    'points_awarded' => $pointsAwarded,
                    'credited_points' => $pointsAwarded,
                    'reward_capped' => $rewardCapped,
                    'is_replay' => (bool) ($award['is_replay'] ?? false),
                    'reviewed_by' => $reviewer->id,
                    'reviewed_at' => now()->toIso8601String(),
                ];
            }

            // REJECTED: jika sebelumnya sudah CREDITED, jalankan reversal poin (AFS-12)
            if ($locked->reward_status === 'CREDITED') {
                $reversal = $this->rewardService->reverseReward(
                    $locked,
                    $trimmedReason,
                    $reviewer->id
                );
                $locked->update(['status' => 'REJECTED']);

                $this->recordReviewAuditLog(
                    activity: $locked,
                    reviewer: $reviewer,
                    action: 'FEEDING_REVIEW_REJECTED',
                    oldStatus: $oldStatus,
                    oldRewardStatus: $oldRewardStatus,
                    newStatus: 'REJECTED',
                    newRewardStatus: 'REVERSED',
                    pointsAwarded: 0,
                    reason: $trimmedReason
                );

                $this->refreshSpotAggregate($locked->spot_id);

                return [
                    'activity_id' => $locked->id,
                    'decision' => 'REJECTED',
                    'status' => 'REJECTED',
                    'reward_status' => 'REVERSED',
                    'points_awarded' => 0,
                    'reversed_points' => $reversal['reversed_points'],
                    'new_balance' => $reversal['new_balance'],
                    'reward_capped' => false,
                    'is_replay' => false,
                    'reviewed_by' => $reviewer->id,
                    'reviewed_at' => now()->toIso8601String(),
                ];
            }

            $snapshot = $locked->reward_snapshot ?? [];
            $locked->update([
                'status' => 'REJECTED',
                'reward_status' => 'NOT_ELIGIBLE',
                'reward_snapshot' => array_merge($snapshot, [
                    'points_awarded' => 0,
                    'review_reason' => $trimmedReason,
                ]),
            ]);

            $this->recordReviewAuditLog(
                activity: $locked,
                reviewer: $reviewer,
                action: 'FEEDING_REVIEW_REJECTED',
                oldStatus: $oldStatus,
                oldRewardStatus: $oldRewardStatus,
                newStatus: 'REJECTED',
                newRewardStatus: 'NOT_ELIGIBLE',
                pointsAwarded: 0,
                reason: $trimmedReason
            );

            $this->refreshSpotAggregate($locked->spot_id);

            return [
                'activity_id' => $locked->id,
                'decision' => 'REJECTED',
                'status' => 'REJECTED',
                'reward_status' => 'NOT_ELIGIBLE',
                'points_awarded' => 0,
                'reward_capped' => false,
                'is_replay' => false,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * Query read-only Riwayat Keputusan Review manual dengan filter (MEOW-30).
     * Hanya memuat keputusan manual di feeding_reviews; tidak menampilkan PII pengguna.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getReviewHistoryQuery(array $filters = []): Builder
    {
        $query = FeedingReview::query()
            ->with([
                'activity.user:id,name,user_type',
                'activity.spot:id,code,name',
                'activity.evidence',
                'activity.ruleResults',
                'reviewer:id,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (!empty($filters['decision'])) {
            $query->where('decision', strtoupper(trim((string) $filters['decision'])));
        }

        if (!empty($filters['reviewer_id'])) {
            $query->where('reviewer_id', trim((string) $filters['reviewer_id']));
        }

        if (!empty($filters['spot_id'])) {
            $spotId = trim((string) $filters['spot_id']);
            $query->whereHas('activity', fn (Builder $q) => $q->where('spot_id', $spotId));
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', (string) $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', (string) $filters['date_to']);
        }

        return $query;
    }

    /**
     * Format satu item Riwayat Review read-only tanpa data pribadi sensitif (MEOW-30).
     *
     * @return array<string, mixed>
     */
    public function formatReviewHistoryItem(FeedingReview $review): array
    {
        $activity = $review->activity;

        return [
            'id' => $review->id,
            'activity_id' => $review->activity_id,
            'feeding_record_ref' => $activity?->feeding_record_ref,
            'decision' => $review->decision,
            'reason' => $review->reason,
            'decided_at' => $review->created_at?->toIso8601String(),
            'feeding_occurred_at' => ($activity?->finalized_at ?? $activity?->scanned_at)?->toIso8601String(),
            'flags' => $activity?->flags ?? [],
            'reward_status' => $activity?->reward_status,
            'reviewer' => $review->reviewer ? [
                'id' => $review->reviewer->id,
                'name' => $review->reviewer->name,
            ] : null,
            'user' => $activity?->user ? [
                'id' => $activity->user->id,
                'name' => $activity->user->name,
                'user_type' => $activity->user->user_type,
            ] : null,
            'spot' => $activity?->spot ? [
                'id' => $activity->spot->id,
                'code' => $activity->spot->code,
                'name' => $activity->spot->name,
            ] : null,
            'evidence' => $activity
                ? $activity->evidence->map(fn (FeedingEvidence $e) => [
                    'id' => $e->id,
                    'media_id' => $e->media_id,
                    'signed_url' => $e->signed_url,
                    'expires_in_seconds' => 600,
                    'captured_at' => $e->captured_at?->toIso8601String(),
                    'sort_order' => $e->sort_order,
                ])->values()->all()
                : [],
            'rule_results' => $activity
                ? $activity->ruleResults->map(fn (FeedingRuleResult $r) => [
                    'rule' => $r->rule,
                    'result' => $r->result,
                    'detail' => $r->detail,
                ])->values()->all()
                : [],
        ];
    }

    /**
     * Catat keputusan review (VERIFIED / REJECTED) ke dalam tabel audit_logs.
     */
    private function recordReviewAuditLog(
        FeedingActivity $activity,
        User $reviewer,
        string $action,
        string $oldStatus,
        string $oldRewardStatus,
        string $newStatus,
        string $newRewardStatus,
        int $pointsAwarded,
        string $reason
    ): void {
        $spot = $activity->relationLoaded('spot') ? $activity->spot : $activity->spot()->first();
        $feeder = $activity->relationLoaded('user') ? $activity->user : $activity->user()->first();

        DB::table('audit_logs')->insert([
            'id' => (string) Str::ulid(),
            'actor_id' => $reviewer->id,
            'action' => $action,
            'subject_type' => 'feeding_activity',
            'subject_id' => $activity->id,
            'result' => 'SUCCESS',
            'reason' => json_encode([
                'old' => [
                    'status' => $oldStatus,
                    'reward_status' => $oldRewardStatus,
                ],
                'new' => [
                    'status' => $newStatus,
                    'reward_status' => $newRewardStatus,
                    'points_awarded' => $pointsAwarded,
                    'review_reason' => $reason,
                    'feeding_record_ref' => $activity->feeding_record_ref,
                    'spot_id' => $spot?->id,
                    'spot_code' => $spot?->code,
                    'spot_name' => $spot?->name,
                    'feeder_name' => $feeder?->name ?? 'Relawan Kampus',
                    'food_type' => $activity->food_type,
                    'food_amount' => $activity->food_amount,
                ],
                'changed_fields' => ['status', 'reward_status', 'review_reason'],
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Perbarui agregat 14 hari terakhir untuk spot (feeding_aggregates).
     */
    public function refreshSpotAggregate(string $spotId): FeedingAggregate
    {
        $windowStart = now()->subDays(14)->startOfDay();
        $windowEnd = now();

        $verifiedCount = FeedingActivity::query()
            ->where('spot_id', $spotId)
            ->where('status', 'VERIFIED')
            ->where('finalized_at', '>=', $windowStart)
            ->count();

        $pendingCount = FeedingActivity::query()
            ->where('spot_id', $spotId)
            ->where('status', 'UNDER_REVIEW')
            ->where('finalized_at', '>=', $windowStart)
            ->count();

        return FeedingAggregate::updateOrCreate(
            ['spot_id' => $spotId],
            [
                'window_start' => $windowStart,
                'window_end' => $windowEnd,
                'verified_count' => $verifiedCount,
                'pending_count' => $pendingCount,
                'computed_at' => now(),
            ]
        );
    }
}

