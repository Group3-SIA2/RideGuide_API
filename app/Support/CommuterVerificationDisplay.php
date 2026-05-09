<?php

namespace App\Support;

use App\Models\Commuter;
use App\Models\Discount;

final class CommuterVerificationDisplay
{
    /**
     * Regular classification has no discount record; commuter status is verified immediately.
     *
     * @return array{verification_status: string|null, rejection_reason: string|null, discount_verification_status: string|null, discount_rejection_reason: string|null}
     */
    public static function forCommuter(Commuter $commuter): array
    {
        $classificationName = $commuter->discount?->classificationType?->classification_name ?? 'Regular';
        $isRegular = $classificationName === 'Regular';

        if ($isRegular) {
            return [
                'verification_status' => Discount::VERIFICATION_VERIFIED,
                'rejection_reason' => null,
                'discount_verification_status' => Discount::VERIFICATION_VERIFIED,
                'discount_rejection_reason' => null,
            ];
        }

        $vs = $commuter->discount?->verification_status;
        $rr = $commuter->discount?->rejection_reason;

        return [
            'verification_status' => $vs,
            'rejection_reason' => $rr,
            'discount_verification_status' => $vs,
            'discount_rejection_reason' => $rr,
        ];
    }
}
