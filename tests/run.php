<?php
declare(strict_types=1);

if (!is_file(__DIR__ . '/../src/contact.php')) {
    fwrite(STDERR, "FAIL: contact submission module is missing\n");
    exit(1);
}
require __DIR__ . '/../src/contact.php';

$count = 0;
function check(bool $condition, string $label): void
{
    global $count;
    $count++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: $label\n");
        exit(1);
    }
}
function submit(array $changes = [], array $state = [], array $config = [], ?callable $transport = null): array
{
    $session = $state + ['csrf' => 'test-token'];
    $input = array_replace([
        'name' => 'David Costa', 'email' => 'david@example.org',
        'subject' => 'Consulta', 'message' => "Hola, quiero saber más.\nGracias.",
        'csrf' => 'test-token', 'website' => '',
    ], $changes);
    $result = contact_submit($input, $session, $config + ['mode' => 'demo'], $transport, 1000);
    $result['session'] = $session;
    return $result;
}

check(submit()['status'] === 200 && submit()['demo'], 'valid demo submission');
foreach (['name', 'email', 'subject', 'message'] as $field) {
    check(isset(submit([$field => ''])['errors'][$field]), "$field is required");
    check(isset(submit([$field => ['bad']])['errors'][$field]), "$field rejects arrays");
}
check(submit(['email' => 'invalid'])['status'] === 422, 'invalid email');
check(submit(['email' => "david@example.org\r\nBcc: victim@example.org"])['status'] === 422, 'email header injection');
check(submit(['subject' => "Hello\nBcc: victim"])['status'] === 422, 'subject header injection');
check(submit(['name' => "David\0Costa"])['status'] === 422, 'control characters');
check(submit(['message' => "\xff"])['status'] === 422, 'invalid UTF-8');
foreach (['name' => 101, 'email' => 255, 'subject' => 151, 'message' => 5001] as $field => $length) {
    check(isset(submit([$field => str_repeat('x', $length)])['errors'][$field]), "$field length limit");
}
check(submit(['message' => str_repeat('á', 5000)])['status'] === 200, 'Unicode length measured in characters');
check(submit(['csrf' => 'wrong'])['status'] === 403, 'invalid CSRF');
check(submit(['csrf' => ['test-token']])['status'] === 403, 'array CSRF');
check(submit(['website' => 'https://spam.example'])['status'] === 422, 'honeypot');
check(submit(['website' => ['spam']])['status'] === 422, 'malformed honeypot');
check(submit([], ['last_attempt' => 990])['status'] === 429, 'session throttle');
check(submit([], ['last_attempt' => 970])['status'] === 200, 'throttle expires');
check(!isset(submit(['email' => 'bad'])['session']['last_attempt']), 'invalid fields do not consume throttle');
check(submit([], [], ['mode' => 'mail'])['status'] === 503, 'mail requires configured addresses');
check(submit([], [], ['mode' => 'wrong'])['status'] === 503, 'unknown mode fails closed');
$mailConfig = ['mode' => 'mail', 'to' => 'owner@example.org', 'from' => 'website@example.org'];
$captured = [];
$success = submit([], [], $mailConfig, function (...$args) use (&$captured): bool { $captured = $args; return true; });
check($success['status'] === 200 && !$success['demo'], 'successful mail transport');
check($captured[0] === $mailConfig['to'], 'configured recipient');
check($captured[3]['From'] === $mailConfig['from'], 'configured fixed sender');
check($captured[3]['Reply-To'] === 'david@example.org', 'validated reply-to');
check(str_contains(quoted_printable_decode($captured[2]), 'David Costa'), 'mail body contains sender');
check(str_starts_with($captured[1], '=?UTF-8?B?'), 'mail subject encoded');
$longSubject = [];
submit(['subject' => str_repeat('á', 150)], [], $mailConfig, function (...$args) use (&$longSubject): bool { $longSubject = $args; return true; });
check(strlen($longSubject[1]) <= 75, 'encoded subject respects RFC 2047 word limit');
check(str_contains(quoted_printable_decode($longSubject[2]), str_repeat('á', 150)), 'user subject retained in message body');
check(submit([], [], $mailConfig, fn (): bool => false)['status'] === 503, 'transport failure reported');
check(submit([], [], $mailConfig, function (): bool { throw new RuntimeException('private detail'); })['status'] === 503, 'transport exception handled');
check(submit([], [], $mailConfig + [], fn (): bool => false)['session']['last_attempt'] === 1000, 'failed transport also throttled');
check(submit([], [], array_replace($mailConfig, ['from' => "owner@example.org\r\nBcc: victim@example.org"]))['status'] === 503, 'config header injection rejected');
$raw = '<script>alert(1)</script>';
check(submit(['name' => $raw])['values']['name'] === $raw, 'values retained for template escaping');
echo "PASS: $count checks\n";
