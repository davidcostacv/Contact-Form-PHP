<?php
declare(strict_types=1);

require __DIR__ . '/../src/contact.php';

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'none'; img-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");

// The PHP development server may route missing files to index.php.
if (isset($_SERVER['PATH_INFO']) && $_SERVER['PATH_INFO'] !== '') {
    http_response_code(404);
    exit('Página no encontrada.');
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Método no permitido.');
}
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();
if (!is_string($_SESSION['csrf'] ?? null) || $_SESSION['csrf'] === '') {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$config = ['mode' => 'demo', 'to' => '', 'from' => ''];
$errors = [];
$values = array_fill_keys(['name', 'email', 'subject', 'message'], '');
$configError = false;
try {
    if (is_file(__DIR__ . '/../config.php')) {
        $loaded = require __DIR__ . '/../config.php';
        if (!is_array($loaded) || !in_array($loaded['mode'] ?? null, ['demo', 'mail'], true)) {
            throw new RuntimeException('Invalid configuration');
        }
        $config = $loaded;
    }
} catch (Throwable $exception) {
    $configError = true;
    http_response_code(503);
    $errors['form'] = 'El formulario no está disponible. Inténtalo más tarde.';
}
$flash = $method === 'GET' ? ($_SESSION['flash'] ?? null) : null;
unset($_SESSION['flash']);
if ($method === 'POST' && !$configError) {
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 131072) {
        http_response_code(413);
        $errors['form'] = 'El formulario supera el tamaño permitido.';
    } else {
        $result = contact_submit($_POST, $_SESSION, $config);
        if ($result['status'] === 200) {
            $_SESSION['flash'] = $result['demo']
                ? 'Formulario validado: no se ha enviado ningún correo porque estás en modo demostración.'
                : 'Tu mensaje ha sido aceptado para envío. Gracias por escribirnos.';
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            header('Location: ./', true, 303);
            exit;
        }
        http_response_code($result['status']);
        if ($result['status'] === 429) {
            header('Retry-After: 30');
        }
        $errors = $result['errors'];
        $values = $result['values'];
    }
}
$demo = ($config['mode'] ?? 'demo') === 'demo';
function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function field_attributes(string $field, array $errors): string
{
    return isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="' . $field . '-error"' : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Un formulario de contacto sencillo para empezar una conversación.">
  <title>Hablemos · Contact Form PHP</title>
  <link rel="icon" href="favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header class="site-header">
    <a class="brand" href="./" aria-label="Contacto, inicio"><span class="brand-mark" aria-hidden="true">c.</span> contacto</a>
    <span class="mode"><?= $demo ? 'Modo demostración' : 'Formulario de contacto' ?></span>
  </header>
  <main class="layout">
    <section class="intro" aria-labelledby="intro-title">
      <p class="eyebrow"><span aria-hidden="true"></span> EMPECEMOS ALGO</p>
      <h1 id="intro-title">Las buenas ideas <br>empiezan con <br><em>un hola.</em></h1>
      <p class="intro-copy">Una pregunta, una propuesta o una idea que quieras compartir. Cuéntanos qué tienes en mente.</p>
      <div class="conversation" aria-hidden="true">
        <div class="orbit orbit-one"></div><div class="orbit orbit-two"></div>
        <div class="note"><span class="note-icon">→</span><span>De una idea<br>a una conversación.</span><span class="note-dot"></span></div>
        <span class="spark">+</span>
      </div>
      <p class="intro-foot">Simple. Directo. De persona a persona.</p>
    </section>
    <section class="form-card" aria-labelledby="form-title">
      <div class="form-heading"><p class="eyebrow">ESTAMOS AL OTRO LADO</p><h2 id="form-title">Hablemos.</h2><p>Completa el formulario para empezar.</p></div>
      <?php if ($demo): ?>
        <p class="demo-note">Esta es una demo. Puedes probar el formulario sin enviar correos.</p>
      <?php endif; ?>
      <?php if (is_string($flash)): ?>
        <div class="notice success" role="status"><?= escape($flash) ?></div>
      <?php endif; ?>
      <?php if ($errors !== []): ?>
        <div class="notice error-summary" role="alert">
          <strong><?= isset($errors['form']) ? escape($errors['form']) : 'Revisa los campos indicados.' ?></strong>
          <?php if (count(array_diff_key($errors, ['form' => true])) > 0): ?>
            <ul><?php foreach ($errors as $field => $error): if ($field === 'form') { continue; } ?><li><a href="#<?= escape($field) ?>"><?= escape(['name' => 'Nombre', 'email' => 'Correo', 'subject' => 'Asunto', 'message' => 'Mensaje'][$field]) ?>: <?= escape($error) ?></a></li><?php endforeach; ?></ul>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <form action="index.php" method="post">
        <input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
        <div class="honeypot" aria-hidden="true"><label for="website">Deja este campo vacío</label><input type="text" id="website" name="website" tabindex="-1" autocomplete="off"></div>
        <div class="field-row">
          <div class="field"><label for="name">Tu nombre <span>*</span></label><input id="name" name="name" autocomplete="name" required maxlength="100" placeholder="¿Cómo te llamas?" value="<?= escape($values['name']) ?>"<?= field_attributes('name', $errors) ?>><?php if (isset($errors['name'])): ?><p class="field-error" id="name-error"><?= escape($errors['name']) ?></p><?php endif; ?></div>
          <div class="field"><label for="email">Tu correo <span>*</span></label><input type="email" id="email" name="email" autocomplete="email" required maxlength="254" placeholder="hola@ejemplo.com" value="<?= escape($values['email']) ?>"<?= field_attributes('email', $errors) ?>><?php if (isset($errors['email'])): ?><p class="field-error" id="email-error"><?= escape($errors['email']) ?></p><?php endif; ?></div>
        </div>
        <div class="field"><label for="subject">Asunto <span>*</span></label><input id="subject" name="subject" required maxlength="150" placeholder="¿De qué te gustaría hablar?" value="<?= escape($values['subject']) ?>"<?= field_attributes('subject', $errors) ?>><?php if (isset($errors['subject'])): ?><p class="field-error" id="subject-error"><?= escape($errors['subject']) ?></p><?php endif; ?></div>
        <div class="field"><label for="message">Tu mensaje <span>*</span></label><textarea id="message" name="message" required maxlength="5000" rows="5" placeholder="Cuéntanos un poco más…"<?= field_attributes('message', $errors) ?>><?= escape($values['message']) ?></textarea><?php if (isset($errors['message'])): ?><p class="field-error" id="message-error"><?= escape($errors['message']) ?></p><?php endif; ?><p class="field-hint">Hasta 5.000 caracteres. Todos los campos son obligatorios.</p></div>
        <button type="submit"<?= $configError ? ' disabled' : '' ?>><?= $demo ? 'Probar formulario' : 'Enviar mensaje' ?><span aria-hidden="true">→</span></button>
        <p class="privacy"><?= $demo ? 'En modo demo, el mensaje no se envía ni se guarda.' : 'Los datos del formulario se enviarán por correo para responder a tu consulta.' ?></p>
      </form>
    </section>
  </main>
  <footer class="site-footer"><span>Contact Form PHP</span><span>Creado por <a href="https://github.com/davidcostacv" rel="noreferrer">David Costa</a> <span aria-hidden="true">↗</span></span></footer>
</body>
</html>
