<x-filament-panels::page>
    @include('role-matrix::matrix', [
        'payload' => $this->matrixPayload(),
        'saveUrl' => '',
    ])
</x-filament-panels::page>
