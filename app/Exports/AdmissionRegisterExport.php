<?php

namespace App\Exports;

use App\Services\AdmissionRegister;

class AdmissionRegisterExport extends StudentExport
{
    public function __construct(private array $registerFilters, array $options) { parent::__construct([], $options); }
    public function query() { return app(AdmissionRegister::class)->query($this->registerFilters); }
}
