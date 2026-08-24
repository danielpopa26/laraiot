<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Http\Requests\Ui;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LogicalDeviceRequest extends FormRequest
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
        $logicalDevice =
            $this->route('logicalDevice');

        return [
            'physical_device_id' => [
                'required',
                'integer',
                'exists:laraiot_physical_devices,id',
            ],
            'device_type_id' => [
                'required',
                'integer',
                'exists:laraiot_device_types,id',
            ],
            'identifier' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique(
                    'laraiot_logical_devices',
                    'identifier',
                )->ignore(
                    is_object($logicalDevice)
                        ? $logicalDevice->getKey()
                        : null,
                ),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'unit' => [
                'nullable',
                'string',
                'max:50',
            ],
            'is_enabled' => [
                'required',
                'boolean',
            ],
        ];
    }
}
