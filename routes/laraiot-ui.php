<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Http\Controllers\Ui\ActivityLogController;
use Danpopa\LaraIoT\Http\Controllers\Ui\ApplicationSettingsController;
use Danpopa\LaraIoT\Http\Controllers\Ui\DashboardController;
use Danpopa\LaraIoT\Http\Controllers\Ui\DeviceTypeController;
use Danpopa\LaraIoT\Http\Controllers\Ui\LogicalDeviceController;
use Danpopa\LaraIoT\Http\Controllers\Ui\MqttTopicController;
use Danpopa\LaraIoT\Http\Controllers\Ui\PhysicalDeviceController;
use Illuminate\Support\Facades\Route;

$prefix = trim(
    (string) config('laraiot.ui.prefix', 'laraiot'),
    '/',
);

$middleware = (array) config(
    'laraiot.ui.middleware',
    ['web'],
);

Route::middleware($middleware)
    ->prefix($prefix)
    ->name('laraiot.')
    ->group(function (): void {
        Route::get(
            '/',
            DashboardController::class,
        )->name('dashboard');

        Route::get(
            '/devices/physical',
            [PhysicalDeviceController::class, 'index'],
        )->name('physical-devices.index');

        Route::get(
            '/devices/physical/create',
            [PhysicalDeviceController::class, 'create'],
        )->name('physical-devices.create');

        Route::post(
            '/devices/physical',
            [PhysicalDeviceController::class, 'store'],
        )->name('physical-devices.store');

        Route::get(
            '/devices/physical/{physicalDevice}',
            [PhysicalDeviceController::class, 'show'],
        )->name('physical-devices.show');

        Route::get(
            '/devices/physical/{physicalDevice}/edit',
            [PhysicalDeviceController::class, 'edit'],
        )->name('physical-devices.edit');

        Route::put(
            '/devices/physical/{physicalDevice}',
            [PhysicalDeviceController::class, 'update'],
        )->name('physical-devices.update');

        Route::delete(
            '/devices/physical/{physicalDevice}',
            [PhysicalDeviceController::class, 'destroy'],
        )->name('physical-devices.destroy');

        Route::get(
            '/devices/logical',
            [LogicalDeviceController::class, 'index'],
        )->name('logical-devices.index');

        Route::get(
            '/devices/logical/create',
            [LogicalDeviceController::class, 'create'],
        )->name('logical-devices.create');

        Route::post(
            '/devices/logical',
            [LogicalDeviceController::class, 'store'],
        )->name('logical-devices.store');

        Route::get(
            '/devices/logical/{logicalDevice}',
            [LogicalDeviceController::class, 'show'],
        )->name('logical-devices.show');

        Route::get(
            '/devices/logical/{logicalDevice}/edit',
            [LogicalDeviceController::class, 'edit'],
        )->name('logical-devices.edit');

        Route::put(
            '/devices/logical/{logicalDevice}',
            [LogicalDeviceController::class, 'update'],
        )->name('logical-devices.update');

        Route::delete(
            '/devices/logical/{logicalDevice}',
            [LogicalDeviceController::class, 'destroy'],
        )->name('logical-devices.destroy');

        Route::get(
            '/devices/logical/{logicalDevice}/mqtt-topics/create',
            [MqttTopicController::class, 'create'],
        )->name('mqtt-topics.create');

        Route::post(
            '/devices/logical/{logicalDevice}/mqtt-topics',
            [MqttTopicController::class, 'store'],
        )->name('mqtt-topics.store');

        Route::get(
            '/devices/logical/{logicalDevice}/mqtt-topics/{mqttTopic}/edit',
            [MqttTopicController::class, 'edit'],
        )->name('mqtt-topics.edit');

        Route::put(
            '/devices/logical/{logicalDevice}/mqtt-topics/{mqttTopic}',
            [MqttTopicController::class, 'update'],
        )->name('mqtt-topics.update');

        Route::delete(
            '/devices/logical/{logicalDevice}/mqtt-topics/{mqttTopic}',
            [MqttTopicController::class, 'destroy'],
        )->name('mqtt-topics.destroy');

        Route::post(
            '/devices/logical/{logicalDevice}/mqtt-topics/{mqttTopic}/validate',
            [MqttTopicController::class, 'validateTopic'],
        )->name('mqtt-topics.validate');

        Route::get(
            '/settings',
            [ApplicationSettingsController::class, 'edit'],
        )->name('settings.edit');

        Route::put(
            '/settings',
            [ApplicationSettingsController::class, 'update'],
        )->name('settings.update');

        Route::get(
            '/settings/device-types',
            [DeviceTypeController::class, 'index'],
        )->name('device-types.index');

        Route::get(
            '/settings/device-types/create',
            [DeviceTypeController::class, 'create'],
        )->name('device-types.create');

        Route::post(
            '/settings/device-types',
            [DeviceTypeController::class, 'store'],
        )->name('device-types.store');

        Route::get(
            '/settings/device-types/{deviceType}/edit',
            [DeviceTypeController::class, 'edit'],
        )->name('device-types.edit');

        Route::put(
            '/settings/device-types/{deviceType}',
            [DeviceTypeController::class, 'update'],
        )->name('device-types.update');

        Route::delete(
            '/settings/device-types/{deviceType}',
            [DeviceTypeController::class, 'destroy'],
        )->name('device-types.destroy');

        Route::get(
            '/logs',
            ActivityLogController::class,
        )->name('logs.index');
    });
