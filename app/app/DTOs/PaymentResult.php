<?php

namespace App\DTOs;

use App\Models\Payment;

final readonly class PaymentResult
{
    public function __construct(
        public Payment $payment,
        public array $summary,
        public bool $created,
    ) {}
}
