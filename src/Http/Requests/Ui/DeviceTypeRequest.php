<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Http\Requests\Ui;

use Danpopa\LaraIoT\Models\DeviceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DeviceTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $deviceType = $this->route('deviceType');

        return [
            'identifier' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique(
                    'laraiot_device_types',
                    'identifier',
                )->ignore(
                    $deviceType instanceof DeviceType
                        ? $deviceType->getKey()
                        : null,
                ),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'is_enabled' => [
                'required',
                'boolean',
            ],
        ];
    }
}
