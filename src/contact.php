<?php
declare(strict_types=1);

/** Validate before invoking a transport. Session throttling is intentionally basic. */
function contact_submit(array $input, array &$session, array $config, ?callable $transport = null, ?int $now = null): array
{
    $now ??= time();
    $values = [];
    $errors = [];
    $limits = ['name' => 100, 'email' => 254, 'subject' => 150, 'message' => 5000];
    foreach ($limits as $field => $limit) {
        $raw = $input[$field] ?? '';
        $values[$field] = is_string($raw) ? trim($raw) : '';
        $value = $values[$field];
        if (!is_string($raw) || $value === '') {
            $errors[$field] = 'Completa este campo.';
        } elseif (!preg_match('//u', $value)) {
            $errors[$field] = 'Usa texto UTF-8 válido.';
        } elseif (preg_match_all('/./us', $value) > $limit) {
            $errors[$field] = "Usa como máximo $limit caracteres.";
        } elseif (preg_match($field === 'message' ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/', $raw)) {
            $errors[$field] = 'El texto contiene caracteres no permitidos.';
        }
    }
    if (!isset($errors['email']) && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Introduce un correo electrónico válido.';
    }
    $result = ['status' => 422, 'errors' => $errors, 'values' => $values, 'demo' => false];
    $token = $input['csrf'] ?? null;
    if (!is_string($token) || !is_string($session['csrf'] ?? null) || $session['csrf'] === '' || !hash_equals($session['csrf'], $token)) {
        $result['status'] = 403;
        $result['errors']['form'] = 'La sesión ha caducado. Recarga la página e inténtalo de nuevo.';
        return $result;
    }
    if (($input['website'] ?? '') !== '') {
        $result['errors']['form'] = 'No se pudo validar el formulario.';
        return $result;
    }
    if ($errors !== []) {
        return $result;
    }
    if (isset($session['last_attempt']) && $now - (int) $session['last_attempt'] < 30) {
        $result['status'] = 429;
        $result['errors']['form'] = 'Espera 30 segundos entre envíos e inténtalo de nuevo.';
        return $result;
    }
    $session['last_attempt'] = $now;
    if (($config['mode'] ?? 'demo') === 'demo') {
        $result['status'] = 200;
        $result['demo'] = true;
        return $result;
    }
    foreach (['to', 'from'] as $field) {
        $address = $config[$field] ?? null;
        if (!is_string($address) || preg_match('/[\r\n]/', $address) || !filter_var($address, FILTER_VALIDATE_EMAIL)) {
            $result['status'] = 503;
            $result['errors']['form'] = 'El envío no está disponible. Inténtalo más tarde.';
            return $result;
        }
    }
    if ($config['mode'] !== 'mail') {
        $result['status'] = 503;
        $result['errors']['form'] = 'El envío no está disponible. Inténtalo más tarde.';
        return $result;
    }
    // Keep the encoded header short; the visitor's full subject is in the body.
    $subject = '=?UTF-8?B?' . base64_encode('Nuevo mensaje de contacto') . '?=';
    $body = "Nombre: {$values['name']}\nCorreo: {$values['email']}\nAsunto: {$values['subject']}\n\n{$values['message']}";
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $body = quoted_printable_encode(str_replace("\n", "\r\n", $body));
    $headers = [
        'From' => $config['from'],
        'Reply-To' => $values['email'],
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => 'quoted-printable',
    ];
    $transport ??= static fn (string $to, string $subject, string $body, array $headers): bool => @mail($to, $subject, $body, $headers);
    try {
        $accepted = $transport($config['to'], $subject, $body, $headers);
    } catch (Throwable $exception) {
        $accepted = false;
    }
    $result['status'] = $accepted ? 200 : 503;
    if (!$accepted) {
        $result['errors']['form'] = 'No pudimos enviar el mensaje. Inténtalo más tarde.';
    }
    return $result;
}
