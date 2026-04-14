<?php

declare(strict_types=1);

use App\Services\DisposableEmailService;

function disposable_email_test_cases(): array
{
    $service = new DisposableEmailService();
    $cases = [];

    $cases['detects_tempmail'] = $service->isDisposable('user@tempmail.com');
    $cases['detects_guerrillamail'] = $service->isDisposable('test@guerrillamail.com');
    $cases['detects_mailinator'] = $service->isDisposable('spam@mailinator.com');
    $cases['detects_10minutemail'] = $service->isDisposable('test@10minutemail.net');
    $cases['detects_yopmail'] = $service->isDisposable('user@yopmail.com');
    $cases['detects_trashmail'] = $service->isDisposable('user@trashmail.com');
    $cases['detects_getnada'] = $service->isDisposable('test@getnada.com');
    $cases['detects_temp_mail_org'] = $service->isDisposable('user@temp-mail.org');

    $cases['accepts_gmail'] = !$service->isDisposable('user@gmail.com');
    $cases['accepts_outlook'] = !$service->isDisposable('user@outlook.com');
    $cases['accepts_hotmail'] = !$service->isDisposable('user@hotmail.com');
    $cases['accepts_yahoo'] = !$service->isDisposable('user@yahoo.com.br');
    $cases['accepts_corporate'] = !$service->isDisposable('user@empresa.com.br');
    $cases['accepts_icloud'] = !$service->isDisposable('user@icloud.com');

    $cases['handles_empty_email'] = !$service->isDisposable('');
    $cases['handles_no_at_symbol'] = !$service->isDisposable('invalid-email');
    $cases['normalizes_case'] = $service->isDisposable('USER@TEMPMAIL.COM');

    return normalize_cases($cases);
}
