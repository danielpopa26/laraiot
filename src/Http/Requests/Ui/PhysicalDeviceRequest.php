<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Http\Requests\Ui;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PhysicalDeviceRequest extends FormRequest
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
        $physicalDevice =
            $this->route('physicalDevice');

        return [
            'identifier' => [
                'required',
                'string',
                'max:255',
                Rule::unique(
                    'laraiot_physical_devices',
                    'identifier',
                )->ignore(
                    is_object($physicalDevice)
                        ? $physicalDevice->getKey()
                        : null,
                ),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'ip_address' => [
                'nullable',
                'ip',
            ],
            'mac_address' => [
                'nullable',
                'mac_address',
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
