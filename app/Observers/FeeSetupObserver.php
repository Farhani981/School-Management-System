<?php

namespace App\Observers;

use App\Models\Discount;
use App\Services\FeeSetupService;

class FeeSetupObserver
{
    public function created(Discount $discount): void
    {
        $this->log($discount, 'created');
    }

    public function updated(Discount $discount): void
    {
        if ($discount->wasChanged()) {
            $this->log($discount, 'updated');
        }
    }

    public function deleted(Discount $discount): void
    {
        $this->log($discount, 'deleted');
    }

    private function log(Discount $discount, string $action): void
    {
        FeeSetupService::log('Discount '.$action.': '.$discount->name.' ('.$discount->type.' '.$discount->value.').', 'discount', 'discounts:'.$discount->id);
    }
}
