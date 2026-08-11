<?php
declare(strict_types=1);

function cards_render_disappeared(string $reason = 'missing'): never
{
    http_response_code($reason === 'expired' ? 410 : 404);
    $title = $reason === 'expired' ? 'La carte a disparu' : 'Rien dans ma manche';
    $message = $reason === 'expired'
        ? 'C’est de la magie !'
        : '';
    ?>
    <!doctype html>
    <html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#090b11">
        <title><?= cards_h($title) ?></title>
        <style>
            *{box-sizing:border-box}html,body{margin:0;min-height:100%;overflow:hidden}body{min-height:100svh;display:grid;place-items:center;padding:24px;color:#f7f1df;background:radial-gradient(circle at 50% 44%,#282039 0,#11131c 38%,#07090e 78%);font-family:Inter,ui-sans-serif,system-ui,-apple-system,sans-serif;text-align:center}.vanish{position:relative;width:min(78vw,330px);aspect-ratio:5/7;display:grid;place-items:center}.ghost-card{position:absolute;inset:0;border:1px solid rgba(255,255,255,.48);border-radius:24px;background:linear-gradient(145deg,rgba(255,255,255,.16),rgba(255,255,255,.025));box-shadow:0 25px 80px rgba(0,0,0,.5);animation:disappear 2.8s cubic-bezier(.55,.02,.72,.48) .25s both;transform-origin:50% 80%}.ghost-card::before{content:"✦";position:absolute;inset:0;display:grid;place-items:center;font:110px/1 Georgia,serif;color:#c8a8ff;text-shadow:0 0 35px #9068d9}.dust{position:absolute;inset:0;pointer-events:none}.dust i{position:absolute;left:50%;top:48%;width:5px;height:5px;border-radius:50%;background:#efd890;box-shadow:0 0 12px #c6a6ff;animation:spark 2.4s ease-out calc(var(--i)*45ms + .8s) both}.copy{position:relative;z-index:2;max-width:310px;margin-top:min(88vw,390px);opacity:0;animation:copyIn .7s ease 2.4s forwards}.copy h1{margin:0 0 10px;font:600 clamp(28px,8vw,40px)/1.08 Georgia,serif}.copy p{margin:0;color:#bdb7c9;font-size:15px;line-height:1.55}@keyframes disappear{0%{opacity:1;filter:blur(0);transform:none}30%{transform:translateY(-8px) rotate(1deg)}60%{opacity:.75;filter:blur(1px);clip-path:polygon(0 0,100% 0,100% 62%,82% 67%,70% 63%,54% 71%,37% 64%,19% 72%,0 67%)}100%{opacity:0;filter:blur(10px);transform:translateY(-65px) scale(.82) rotate(-4deg);clip-path:polygon(0 0,100% 0,95% 5%,88% 3%,82% 9%,73% 4%,65% 12%,54% 5%,43% 14%,32% 6%,20% 11%,11% 4%,0 10%)}}@keyframes spark{0%{opacity:0;transform:translate(0,0) scale(.3)}20%{opacity:1}100%{opacity:0;transform:translate(calc((var(--x))*1px),calc((var(--y))*1px)) scale(0)}}@keyframes copyIn{to{opacity:1;transform:translateY(-22px)}}@media(prefers-reduced-motion:reduce){.ghost-card{animation:none;opacity:.08}.dust{display:none}.copy{animation:none;opacity:1;transform:translateY(-22px)}}
        </style>
    </head>
    <body>
        <main class="vanish">
            <div class="ghost-card" aria-hidden="true"></div>
            <div class="dust" aria-hidden="true">
                <?php for ($i = 0; $i < 28; $i++): ?>
                    <i style="--i:<?= $i ?>;--x:<?= (($i * 47) % 250) - 125 ?>;--y:<?= -40 - (($i * 71) % 230) ?>"></i>
                <?php endfor; ?>
            </div>
            <div class="copy"><h1><?= cards_h($title) ?></h1><?php if ($message !== ''): ?><p><?= cards_h($message) ?></p><?php endif; ?></div>
        </main>
    </body>
    </html>
    <?php
    exit;
}
