<?php

namespace App\DTOs;

use Carbon\Carbon;

readonly class ReportFilterData
{
    public function __construct(
        public Carbon $startDate,
        public Carbon $endDate,
        public ?int $userId = null,
        public ?int $territoryId = null,
        public ?int $principalId = null,
        public ?int $distributorId = null,
        public ?int $customerId = null,
        public ?int $customerGroupId = null,
        public ?int $segmentId = null,
        public ?int $subSegmentId = null,
        public ?int $itemId = null,
        public ?int $leadId = null,
        public ?string $leadStatus = null,
        public ?string $leadSource = null,
        public ?string $leadPriority = null,
        public ?string $cdNcdType = null,
        public array $userIds = [],
    ) {}

    public function getPrimaryDateRangeLabel(): string
    {
        return "{$this->startDate->format('d M Y')} - {$this->endDate->format('d M Y')}";
    }

    public function toCacheKey(): string
    {
        return md5(serialize($this));
    }

    public static function fromArray(array $data): self
    {
        $startDate = Carbon::parse($data['start_date'] ?? now()->startOfYear());
        $endDate = Carbon::parse($data['end_date'] ?? now()->endOfYear());

        return new self(
            startDate: $startDate,
            endDate: $endDate,
            userId: $data['user_id'] ?? null,
            territoryId: $data['territory_id'] ?? null,
            principalId: $data['principal_id'] ?? null,
            distributorId: $data['distributor_id'] ?? null,
            customerId: $data['customer_id'] ?? null,
            customerGroupId: $data['customer_group_id'] ?? null,
            segmentId: $data['segment_id'] ?? null,
            subSegmentId: $data['sub_segment_id'] ?? null,
            itemId: $data['item_id'] ?? null,
            leadId: $data['lead_id'] ?? null,
            leadStatus: $data['lead_status'] ?? null,
            leadSource: $data['lead_source'] ?? null,
            leadPriority: $data['lead_priority'] ?? null,
            cdNcdType: $data['cd_ncd_type'] ?? null,
            userIds: $data['user_ids'] ?? [],
        );
    }
}
